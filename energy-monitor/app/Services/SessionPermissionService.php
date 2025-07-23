<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SessionPermissionService
{
    protected PermissionCacheService $cacheService;
    protected int $refreshInterval = 300; // 5 minutes
    protected string $sessionKey = 'user_permissions';
    protected string $lastRefreshKey = 'permissions_last_refresh';

    public function __construct(PermissionCacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    /**
     * Get user permissions from session or refresh if needed
     */
    public function getSessionPermissions(User $user): array
    {
        $sessionPermissions = Session::get($this->sessionKey);
        $lastRefresh = Session::get($this->lastRefreshKey);

        // Check if permissions need refresh
        if ($this->shouldRefreshPermissions($sessionPermissions, $lastRefresh, $user)) {
            $sessionPermissions = $this->refreshSessionPermissions($user);
        }

        return $sessionPermissions ?? [];
    }

    /**
     * Force refresh of session permissions
     */
    public function refreshSessionPermissions(User $user): array
    {
        try {
            // Get fresh permissions from cache service
            $permissions = $this->cacheService->getUserPermissions($user);
            
            // Add session-specific metadata
            $sessionPermissions = array_merge($permissions, [
                'session_id' => Session::getId(),
                'refreshed_at' => now()->toISOString(),
                'expires_at' => now()->addSeconds($this->refreshInterval)->toISOString(),
            ]);

            // Store in session
            Session::put($this->sessionKey, $sessionPermissions);
            Session::put($this->lastRefreshKey, now()->timestamp);

            Log::debug('Session permissions refreshed', [
                'user_id' => $user->id,
                'session_id' => Session::getId(),
            ]);

            return $sessionPermissions;

        } catch (\Exception $e) {
            Log::error('Failed to refresh session permissions', [
                'user_id' => $user->id,
                'session_id' => Session::getId(),
                'error' => $e->getMessage(),
            ]);

            // Return empty permissions on error to be safe
            return [];
        }
    }

    /**
     * Invalidate session permissions for current user
     */
    public function invalidateSessionPermissions(): void
    {
        Session::forget($this->sessionKey);
        Session::forget($this->lastRefreshKey);

        Log::debug('Session permissions invalidated', [
            'session_id' => Session::getId(),
        ]);
    }

    /**
     * Check if user has permission to access a resource (session-based)
     */
    public function hasSessionPermission(User $user, string $permission, array $context = []): bool
    {
        $sessionPermissions = $this->getSessionPermissions($user);

        if (empty($sessionPermissions)) {
            return false;
        }

        // Admin users have all permissions
        if ($sessionPermissions['is_admin'] ?? false) {
            return true;
        }

        return match($permission) {
            'view_gateway' => $this->hasGatewayPermission($sessionPermissions, $context['gateway_id'] ?? null),
            'view_device' => $this->hasDevicePermission($sessionPermissions, $context['device_id'] ?? null),
            'view_alerts' => $this->hasAlertPermission($sessionPermissions, $context),
            'access_dashboard' => $this->hasDashboardPermission($sessionPermissions, $context['dashboard_type'] ?? 'global'),
            default => false,
        };
    }

    /**
     * Get session permission summary for debugging
     */
    public function getSessionPermissionSummary(User $user): array
    {
        $sessionPermissions = Session::get($this->sessionKey, []);
        $lastRefresh = Session::get($this->lastRefreshKey);

        return [
            'user_id' => $user->id,
            'session_id' => Session::getId(),
            'has_session_permissions' => !empty($sessionPermissions),
            'last_refresh' => $lastRefresh ? Carbon::createFromTimestamp($lastRefresh)->toISOString() : null,
            'next_refresh_due' => $lastRefresh ? Carbon::createFromTimestamp($lastRefresh + $this->refreshInterval)->toISOString() : null,
            'permissions_count' => [
                'gateways' => count($sessionPermissions['assigned_gateways'] ?? []),
                'devices' => count($sessionPermissions['assigned_devices'] ?? []),
            ],
            'is_admin' => $sessionPermissions['is_admin'] ?? false,
        ];
    }

    /**
     * Set refresh interval
     */
    public function setRefreshInterval(int $seconds): self
    {
        $this->refreshInterval = $seconds;
        return $this;
    }

    /**
     * Get refresh interval
     */
    public function getRefreshInterval(): int
    {
        return $this->refreshInterval;
    }

    /**
     * Check if permissions should be refreshed
     */
    protected function shouldRefreshPermissions(?array $sessionPermissions, ?int $lastRefresh, User $user): bool
    {
        // No session permissions exist
        if (empty($sessionPermissions)) {
            return true;
        }

        // No last refresh timestamp
        if (!$lastRefresh) {
            return true;
        }

        // Permissions are expired
        if (now()->timestamp - $lastRefresh > $this->refreshInterval) {
            return true;
        }

        // User ID mismatch (session hijacking protection)
        if (($sessionPermissions['user_id'] ?? null) !== $user->id) {
            Log::warning('Session permission user ID mismatch detected', [
                'session_user_id' => $sessionPermissions['user_id'] ?? null,
                'actual_user_id' => $user->id,
                'session_id' => Session::getId(),
            ]);
            return true;
        }

        return false;
    }

    /**
     * Check gateway permission from session data
     */
    protected function hasGatewayPermission(array $sessionPermissions, ?int $gatewayId): bool
    {
        if (!$gatewayId) {
            return false;
        }

        $assignedGateways = $sessionPermissions['assigned_gateways'] ?? [];
        
        foreach ($assignedGateways as $gateway) {
            if ($gateway['id'] === $gatewayId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check device permission from session data
     */
    protected function hasDevicePermission(array $sessionPermissions, ?int $deviceId): bool
    {
        if (!$deviceId) {
            return false;
        }

        $assignedDevices = $sessionPermissions['assigned_devices'] ?? [];
        
        foreach ($assignedDevices as $device) {
            if ($device['id'] === $deviceId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check alert permission from session data
     */
    protected function hasAlertPermission(array $sessionPermissions, array $context): bool
    {
        // Users can view alerts for devices they have access to
        $assignedDevices = $sessionPermissions['assigned_devices'] ?? [];
        
        if (empty($assignedDevices)) {
            return false;
        }

        // If specific device context is provided, check that device
        if (isset($context['device_id'])) {
            return $this->hasDevicePermission($sessionPermissions, $context['device_id']);
        }

        // If gateway context is provided, check if user has any devices in that gateway
        if (isset($context['gateway_id'])) {
            foreach ($assignedDevices as $device) {
                if ($device['gateway_id'] === $context['gateway_id']) {
                    return true;
                }
            }
            return false;
        }

        // General alert access - user has at least one device
        return true;
    }

    /**
     * Check dashboard permission from session data
     */
    protected function hasDashboardPermission(array $sessionPermissions, string $dashboardType): bool
    {
        return match($dashboardType) {
            'global' => !empty($sessionPermissions['assigned_gateways']) || !empty($sessionPermissions['assigned_devices']),
            'gateway' => !empty($sessionPermissions['assigned_gateways']),
            default => false,
        };
    }

    /**
     * Middleware helper to validate session permissions
     */
    public function validateSessionPermissions(User $user, string $permission, array $context = []): bool
    {
        try {
            return $this->hasSessionPermission($user, $permission, $context);
        } catch (\Exception $e) {
            Log::error('Session permission validation failed', [
                'user_id' => $user->id,
                'permission' => $permission,
                'context' => $context,
                'error' => $e->getMessage(),
            ]);

            // Fail securely - deny access on error
            return false;
        }
    }

    /**
     * Clean up expired session permissions (for scheduled cleanup)
     */
    public function cleanupExpiredSessions(): int
    {
        // This would typically be implemented with a custom session handler
        // or by scanning active sessions in the database
        
        // For now, just log that cleanup was requested
        Log::info('Session permission cleanup requested');
        
        return 0; // Return count of cleaned up sessions
    }
}