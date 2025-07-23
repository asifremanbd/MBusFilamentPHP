<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\SessionPermissionService;
use App\Services\PermissionCacheService;
use App\Events\UserPermissionsChanged;
use Illuminate\Support\Facades\Log;

class PermissionController extends Controller
{
    protected SessionPermissionService $sessionService;
    protected PermissionCacheService $cacheService;

    public function __construct(
        SessionPermissionService $sessionService,
        PermissionCacheService $cacheService
    ) {
        $this->sessionService = $sessionService;
        $this->cacheService = $cacheService;
    }

    /**
     * Get current user's permission status
     */
    public function getPermissionStatus(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $user = $request->user();
            $permissionSummary = $this->sessionService->getSessionPermissionSummary($user);
            $sessionPermissions = $this->sessionService->getSessionPermissions($user);

            return response()->json([
                'success' => true,
                'user_id' => $user->id,
                'permission_summary' => $permissionSummary,
                'permissions' => [
                    'gateways' => $sessionPermissions['assigned_gateways'] ?? [],
                    'devices' => $sessionPermissions['assigned_devices'] ?? [],
                    'is_admin' => $sessionPermissions['is_admin'] ?? false,
                ],
                'refresh_info' => [
                    'last_refresh' => $permissionSummary['last_refresh'],
                    'next_refresh_due' => $permissionSummary['next_refresh_due'],
                    'refresh_interval' => $this->sessionService->getRefreshInterval(),
                ],
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get permission status', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to get permission status',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Refresh current user's permissions
     */
    public function refreshPermissions(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $user = $request->user();
            $refreshedPermissions = $this->sessionService->refreshSessionPermissions($user);

            return response()->json([
                'success' => true,
                'message' => 'Permissions refreshed successfully',
                'permissions' => [
                    'gateways' => $refreshedPermissions['assigned_gateways'] ?? [],
                    'devices' => $refreshedPermissions['assigned_devices'] ?? [],
                    'is_admin' => $refreshedPermissions['is_admin'] ?? false,
                ],
                'refreshed_at' => $refreshedPermissions['refreshed_at'] ?? now()->toISOString(),
                'expires_at' => $refreshedPermissions['expires_at'] ?? null,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to refresh permissions', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to refresh permissions',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check specific permission
     */
    public function checkPermission(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $request->validate([
                'permission' => 'required|string',
                'context' => 'sometimes|array',
            ]);

            $user = $request->user();
            $permission = $request->input('permission');
            $context = $request->input('context', []);

            $hasPermission = $this->sessionService->hasSessionPermission($user, $permission, $context);

            return response()->json([
                'success' => true,
                'permission' => $permission,
                'context' => $context,
                'has_permission' => $hasPermission,
                'checked_at' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to check permission', [
                'user_id' => $request->user()?->id,
                'permission' => $request->input('permission'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to check permission',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get permission changes for current user (polling endpoint)
     */
    public function getPermissionChanges(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $user = $request->user();
            $lastCheck = $request->input('last_check');
            
            // Get current permission fingerprint
            $currentPermissions = $this->sessionService->getSessionPermissions($user);
            $currentFingerprint = md5(serialize($currentPermissions));
            
            // Get last known fingerprint
            $lastFingerprint = $request->input('fingerprint');
            
            $hasChanges = $lastFingerprint && $lastFingerprint !== $currentFingerprint;
            
            $response = [
                'success' => true,
                'has_changes' => $hasChanges,
                'current_fingerprint' => $currentFingerprint,
                'checked_at' => now()->toISOString(),
            ];

            if ($hasChanges) {
                $response['permissions'] = [
                    'gateways' => $currentPermissions['assigned_gateways'] ?? [],
                    'devices' => $currentPermissions['assigned_devices'] ?? [],
                    'is_admin' => $currentPermissions['is_admin'] ?? false,
                ];
                $response['refreshed_at'] = $currentPermissions['refreshed_at'] ?? null;
            }

            return response()->json($response);

        } catch (\Exception $e) {
            Log::error('Failed to get permission changes', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to get permission changes',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Invalidate current user's session permissions
     */
    public function invalidatePermissions(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $user = $request->user();
            
            // Invalidate session permissions
            $this->sessionService->invalidateSessionPermissions();
            
            // Invalidate cache permissions
            $this->cacheService->invalidateUserPermissions($user);

            return response()->json([
                'success' => true,
                'message' => 'Permissions invalidated successfully',
                'invalidated_at' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to invalidate permissions', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to invalidate permissions',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get permission cache statistics (admin only)
     */
    public function getCacheStatistics(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user->isAdmin()) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'Admin access required',
                ], 403);
            }

            $stats = $this->cacheService->getCacheStatistics();

            return response()->json([
                'success' => true,
                'cache_statistics' => $stats,
                'generated_at' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get cache statistics', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to get cache statistics',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Warm up permission cache for current user
     */
    public function warmUpCache(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $user = $request->user();
            
            $this->cacheService->warmUpUserCache($user);

            return response()->json([
                'success' => true,
                'message' => 'Permission cache warmed up successfully',
                'warmed_up_at' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to warm up cache', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to warm up cache',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Trigger permission change event (for testing)
     */
    public function triggerPermissionChange(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            if (!app()->environment(['local', 'testing'])) {
                return response()->json([
                    'error' => 'Not available in production',
                ], 403);
            }

            $user = $request->user();
            $changes = $request->input('changes', ['test' => 'change']);

            event(new UserPermissionsChanged($user, $changes, $user, 'test'));

            return response()->json([
                'success' => true,
                'message' => 'Permission change event triggered',
                'triggered_at' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to trigger permission change', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to trigger permission change',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}