<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserDeviceAssignment;
use App\Models\UserGatewayAssignment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class PermissionCacheService
{
    protected int $cacheTtl = 3600; // 1 hour default
    protected string $cachePrefix = 'user_permissions';

    /**
     * Get cached user permissions or fetch and cache them
     */
    public function getUserPermissions(User $user): array
    {
        $cacheKey = $this->getUserPermissionsCacheKey($user->id);
        
        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($user) {
            return $this->fetchUserPermissions($user);
        });
    }

    /**
     * Get cached authorized gateways for user
     */
    public function getAuthorizedGateways(User $user): Collection
    {
        $cacheKey = $this->getAuthorizedGatewaysCacheKey($user->id);
        
        $gatewayIds = Cache::remember($cacheKey, $this->cacheTtl, function () use ($user) {
            if ($user->isAdmin()) {
                return \App\Models\Gateway::pluck('id')->toArray();
            }
            
            return UserGatewayAssignment::where('user_id', $user->id)
                ->pluck('gateway_id')
                ->toArray();
        });

        return \App\Models\Gateway::whereIn('id', $gatewayIds)->get();
    }

    /**
     * Get cached authorized devices for user
     */
    public function getAuthorizedDevices(User $user, ?int $gatewayId = null): Collection
    {
        $cacheKey = $this->getAuthorizedDevicesCacheKey($user->id, $gatewayId);
        
        $deviceIds = Cache::remember($cacheKey, $this->cacheTtl, function () use ($user, $gatewayId) {
            if ($user->isAdmin()) {
                $query = \App\Models\Device::query();
                if ($gatewayId) {
                    $query->where('gateway_id', $gatewayId);
                }
                return $query->pluck('id')->toArray();
            }
            
            $query = UserDeviceAssignment::where('user_id', $user->id);
            
            if ($gatewayId) {
                $query->whereHas('device', function ($q) use ($gatewayId) {
                    $q->where('gateway_id', $gatewayId);
                });
            }
            
            return $query->pluck('device_id')->toArray();
        });

        return \App\Models\Device::whereIn('id', $deviceIds)->get();
    }

    /**
     * Check if user can access a specific widget (cached)
     */
    public function canAccessWidget(User $user, string $widgetType, array $config = []): bool
    {
        $cacheKey = $this->getWidgetAccessCacheKey($user->id, $widgetType, $config);
        
        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($user, $widgetType, $config) {
            return $this->checkWidgetAccess($user, $widgetType, $config);
        });
    }

    /**
     * Invalidate all permission caches for a user
     */
    public function invalidateUserPermissions(User $user): void
    {
        $patterns = [
            $this->getUserPermissionsCacheKey($user->id),
            $this->getAuthorizedGatewaysCacheKey($user->id),
            $this->getAuthorizedDevicesCacheKey($user->id, '*'),
            $this->getWidgetAccessCacheKey($user->id, '*', []),
        ];

        foreach ($patterns as $pattern) {
            if (str_contains($pattern, '*')) {
                $this->invalidateByPattern($pattern);
            } else {
                Cache::forget($pattern);
            }
        }

        Log::info('Permission cache invalidated for user', [
            'user_id' => $user->id,
            'user_email' => $user->email,
        ]);
    }

    /**
     * Invalidate permission caches for multiple users
     */
    public function invalidateMultipleUsers(array $userIds): void
    {
        foreach ($userIds as $userId) {
            $user = User::find($userId);
            if ($user) {
                $this->invalidateUserPermissions($user);
            }
        }
    }

    /**
     * Invalidate all permission caches (use with caution)
     */
    public function invalidateAllPermissions(): void
    {
        $this->invalidateByPattern("{$this->cachePrefix}:*");
        
        Log::warning('All permission caches invalidated', [
            'invalidated_by' => auth()->user()?->id,
        ]);
    }

    /**
     * Warm up permission cache for a user
     */
    public function warmUpUserCache(User $user): void
    {
        // Pre-load common permission queries
        $this->getUserPermissions($user);
        $this->getAuthorizedGateways($user);
        $this->getAuthorizedDevices($user);

        // Pre-load common widget access checks
        $commonWidgets = [
            'system-overview',
            'cross-gateway-alerts',
            'gateway-device-list',
            'real-time-readings',
        ];

        foreach ($commonWidgets as $widgetType) {
            $this->canAccessWidget($user, $widgetType);
        }

        Log::info('Permission cache warmed up for user', [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Get cache statistics for monitoring
     */
    public function getCacheStatistics(): array
    {
        $stats = [
            'total_cached_users' => 0,
            'cache_hit_rate' => 0,
            'cache_size_estimate' => 0,
            'oldest_cache_entry' => null,
            'newest_cache_entry' => null,
        ];

        // This would require Redis or a cache driver that supports pattern scanning
        // For now, return basic stats
        return $stats;
    }

    /**
     * Set cache TTL
     */
    public function setCacheTtl(int $seconds): self
    {
        $this->cacheTtl = $seconds;
        return $this;
    }

    /**
     * Get current cache TTL
     */
    public function getCacheTtl(): int
    {
        return $this->cacheTtl;
    }

    /**
     * Fetch user permissions from database
     */
    protected function fetchUserPermissions(User $user): array
    {
        $permissions = [
            'user_id' => $user->id,
            'role' => $user->role,
            'is_admin' => $user->isAdmin(),
            'assigned_gateways' => [],
            'assigned_devices' => [],
            'cached_at' => now()->toISOString(),
        ];

        if (!$user->isAdmin()) {
            $permissions['assigned_gateways'] = UserGatewayAssignment::where('user_id', $user->id)
                ->with('gateway:id,name')
                ->get()
                ->map(function ($assignment) {
                    return [
                        'id' => $assignment->gateway_id,
                        'name' => $assignment->gateway->name,
                        'assigned_at' => $assignment->assigned_at->toISOString(),
                    ];
                })
                ->toArray();

            $permissions['assigned_devices'] = UserDeviceAssignment::where('user_id', $user->id)
                ->with('device:id,name,gateway_id')
                ->get()
                ->map(function ($assignment) {
                    return [
                        'id' => $assignment->device_id,
                        'name' => $assignment->device->name,
                        'gateway_id' => $assignment->device->gateway_id,
                        'assigned_at' => $assignment->assigned_at->toISOString(),
                    ];
                })
                ->toArray();
        }

        return $permissions;
    }

    /**
     * Check widget access without caching
     */
    protected function checkWidgetAccess(User $user, string $widgetType, array $config): bool
    {
        // Admin users have access to all widgets
        if ($user->isAdmin()) {
            return true;
        }

        // Check widget-specific access rules
        return match($widgetType) {
            'system-overview' => $this->hasSystemOverviewAccess($user),
            'cross-gateway-alerts' => $this->hasAlertAccess($user),
            'top-consuming-gateways' => $this->hasGatewayAccess($user),
            'system-health' => $this->hasSystemHealthAccess($user),
            'gateway-device-list' => $this->hasGatewayDeviceAccess($user, $config),
            'real-time-readings' => $this->hasReadingsAccess($user, $config),
            'gateway-stats' => $this->hasGatewayStatsAccess($user, $config),
            'gateway-alerts' => $this->hasGatewayAlertAccess($user, $config),
            default => false,
        };
    }

    /**
     * Widget-specific access checks
     */
    protected function hasSystemOverviewAccess(User $user): bool
    {
        return UserGatewayAssignment::where('user_id', $user->id)->exists() ||
               UserDeviceAssignment::where('user_id', $user->id)->exists();
    }

    protected function hasAlertAccess(User $user): bool
    {
        return UserDeviceAssignment::where('user_id', $user->id)->exists();
    }

    protected function hasGatewayAccess(User $user): bool
    {
        return UserGatewayAssignment::where('user_id', $user->id)->exists();
    }

    protected function hasSystemHealthAccess(User $user): bool
    {
        return $this->hasSystemOverviewAccess($user);
    }

    protected function hasGatewayDeviceAccess(User $user, array $config): bool
    {
        $gatewayId = $config['gateway_id'] ?? null;
        if (!$gatewayId) {
            return false;
        }

        return UserGatewayAssignment::where('user_id', $user->id)
            ->where('gateway_id', $gatewayId)
            ->exists();
    }

    protected function hasReadingsAccess(User $user, array $config): bool
    {
        return $this->hasGatewayDeviceAccess($user, $config);
    }

    protected function hasGatewayStatsAccess(User $user, array $config): bool
    {
        return $this->hasGatewayDeviceAccess($user, $config);
    }

    protected function hasGatewayAlertAccess(User $user, array $config): bool
    {
        return $this->hasGatewayDeviceAccess($user, $config);
    }

    /**
     * Cache key generators
     */
    protected function getUserPermissionsCacheKey(int $userId): string
    {
        return "{$this->cachePrefix}:user:{$userId}:permissions";
    }

    protected function getAuthorizedGatewaysCacheKey(int $userId): string
    {
        return "{$this->cachePrefix}:user:{$userId}:gateways";
    }

    protected function getAuthorizedDevicesCacheKey(int $userId, ?int $gatewayId): string
    {
        $suffix = $gatewayId ? "gateway:{$gatewayId}" : 'all';
        return "{$this->cachePrefix}:user:{$userId}:devices:{$suffix}";
    }

    protected function getWidgetAccessCacheKey(int $userId, string $widgetType, array $config): string
    {
        $configHash = md5(serialize($config));
        return "{$this->cachePrefix}:user:{$userId}:widget:{$widgetType}:{$configHash}";
    }

    /**
     * Invalidate cache entries by pattern
     */
    protected function invalidateByPattern(string $pattern): void
    {
        // This implementation depends on the cache driver
        // For Redis, you could use SCAN with pattern matching
        // For file/database cache, you'd need a different approach
        
        if (config('cache.default') === 'redis') {
            $this->invalidateRedisPattern($pattern);
        } else {
            // For other cache drivers, we'll need to track keys manually
            // or use a more sophisticated cache tagging system
            Log::warning('Pattern-based cache invalidation not fully supported for current cache driver', [
                'pattern' => $pattern,
                'cache_driver' => config('cache.default'),
            ]);
        }
    }

    /**
     * Invalidate Redis cache entries by pattern
     */
    protected function invalidateRedisPattern(string $pattern): void
    {
        try {
            $redis = Cache::getRedis();
            $keys = $redis->keys($pattern);
            
            if (!empty($keys)) {
                $redis->del($keys);
                Log::info('Redis cache keys invalidated', [
                    'pattern' => $pattern,
                    'keys_deleted' => count($keys),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to invalidate Redis cache pattern', [
                'pattern' => $pattern,
                'error' => $e->getMessage(),
            ]);
        }
    }
}