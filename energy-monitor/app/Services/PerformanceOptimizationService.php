<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Gateway;
use App\Models\Device;
use App\Models\Reading;
use App\Models\Alert;

class PerformanceOptimizationService
{
    protected const CACHE_TTL = 300; // 5 minutes
    protected const CACHE_PREFIX = 'dashboard_perf_';

    /**
     * Optimize database queries with eager loading and indexing
     */
    public function optimizeQueries(): void
    {
        // Add database indexes for better performance
        $this->ensureDatabaseIndexes();
        
        // Configure query optimization settings
        $this->configureQueryOptimization();
    }

    /**
     * Ensure critical database indexes exist
     */
    protected function ensureDatabaseIndexes(): void
    {
        try {
            // Check and create indexes if they don't exist
            $indexes = [
                'user_gateway_assignments' => [
                    ['user_id'],
                    ['gateway_id'],
                    ['user_id', 'gateway_id']
                ],
                'user_device_assignments' => [
                    ['user_id'],
                    ['device_id'],
                    ['user_id', 'device_id']
                ],
                'readings' => [
                    ['device_id'],
                    ['timestamp'],
                    ['device_id', 'timestamp'],
                    ['parameter_name']
                ],
                'alerts' => [
                    ['device_id'],
                    ['timestamp'],
                    ['severity'],
                    ['resolved'],
                    ['device_id', 'resolved']
                ],
                'devices' => [
                    ['gateway_id'],
                    ['status']
                ],
                'gateways' => [
                    ['communication_status'],
                    ['last_communication']
                ]
            ];

            foreach ($indexes as $table => $tableIndexes) {
                foreach ($tableIndexes as $columns) {
                    $this->createIndexIfNotExists($table, $columns);
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to create database indexes', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Create database index if it doesn't exist
     */
    protected function createIndexIfNotExists(string $table, array $columns): void
    {
        $indexName = $table . '_' . implode('_', $columns) . '_index';
        
        try {
            $exists = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
            
            if (empty($exists)) {
                $columnList = implode(', ', $columns);
                DB::statement("CREATE INDEX {$indexName} ON {$table} ({$columnList})");
                Log::info("Created database index: {$indexName}");
            }
        } catch (\Exception $e) {
            Log::debug("Index creation skipped for {$indexName}: " . $e->getMessage());
        }
    }

    /**
     * Configure query optimization settings
     */
    protected function configureQueryOptimization(): void
    {
        // Set MySQL query cache settings if using MySQL
        try {
            if (config('database.default') === 'mysql') {
                DB::statement('SET SESSION query_cache_type = ON');
                DB::statement('SET SESSION query_cache_size = 67108864'); // 64MB
            }
        } catch (\Exception $e) {
            Log::debug('Query cache configuration skipped: ' . $e->getMessage());
        }
    }

    /**
     * Get cached user permissions with optimized queries
     */
    public function getCachedUserPermissions(int $userId): array
    {
        $cacheKey = self::CACHE_PREFIX . "user_permissions_{$userId}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($userId) {
            return [
                'gateways' => $this->getOptimizedUserGateways($userId),
                'devices' => $this->getOptimizedUserDevices($userId),
                'timestamp' => now()->toISOString()
            ];
        });
    }

    /**
     * Get user gateways with optimized query
     */
    protected function getOptimizedUserGateways(int $userId): array
    {
        $user = User::find($userId);
        
        if ($user->role === 'admin') {
            // Admin gets all gateways - use simple query
            return Gateway::select('id', 'name', 'communication_status', 'ip_address', 'port', 'gnss_location', 'last_communication')
                ->with(['devices:id,gateway_id,name,status'])
                ->get()
                ->toArray();
        }

        // Operator gets assigned gateways - use join for better performance
        return DB::table('gateways')
            ->select('gateways.id', 'gateways.name', 'gateways.communication_status', 
                    'gateways.ip_address', 'gateways.port', 'gateways.gnss_location', 
                    'gateways.last_communication')
            ->join('user_gateway_assignments', 'gateways.id', '=', 'user_gateway_assignments.gateway_id')
            ->where('user_gateway_assignments.user_id', $userId)
            ->get()
            ->toArray();
    }

    /**
     * Get user devices with optimized query
     */
    protected function getOptimizedUserDevices(int $userId): array
    {
        $user = User::find($userId);
        
        if ($user->role === 'admin') {
            // Admin gets all devices
            return Device::select('id', 'name', 'gateway_id', 'slave_id', 'status', 'location_tag')
                ->with('gateway:id,name')
                ->get()
                ->toArray();
        }

        // Operator gets assigned devices
        return DB::table('devices')
            ->select('devices.id', 'devices.name', 'devices.gateway_id', 
                    'devices.slave_id', 'devices.status', 'devices.location_tag')
            ->join('user_device_assignments', 'devices.id', '=', 'user_device_assignments.device_id')
            ->where('user_device_assignments.user_id', $userId)
            ->get()
            ->toArray();
    }

    /**
     * Get cached dashboard statistics
     */
    public function getCachedDashboardStats(int $userId, string $dashboardType, ?int $gatewayId = null): array
    {
        $cacheKey = self::CACHE_PREFIX . "dashboard_stats_{$userId}_{$dashboardType}";
        if ($gatewayId) {
            $cacheKey .= "_{$gatewayId}";
        }
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($userId, $dashboardType, $gatewayId) {
            return $this->calculateDashboardStats($userId, $dashboardType, $gatewayId);
        });
    }

    /**
     * Calculate dashboard statistics with optimized queries
     */
    protected function calculateDashboardStats(int $userId, string $dashboardType, ?int $gatewayId = null): array
    {
        $permissions = $this->getCachedUserPermissions($userId);
        $authorizedGatewayIds = collect($permissions['gateways'])->pluck('id')->toArray();
        $authorizedDeviceIds = collect($permissions['devices'])->pluck('id')->toArray();

        if (empty($authorizedDeviceIds)) {
            return $this->getEmptyStats();
        }

        if ($dashboardType === 'gateway' && $gatewayId) {
            // Filter to specific gateway
            $gatewayDeviceIds = collect($permissions['devices'])
                ->where('gateway_id', $gatewayId)
                ->pluck('id')
                ->toArray();
            
            if (empty($gatewayDeviceIds)) {
                return $this->getEmptyStats();
            }
            
            return $this->calculateGatewayStats($gatewayDeviceIds, $gatewayId);
        }

        return $this->calculateGlobalStats($authorizedDeviceIds, $authorizedGatewayIds);
    }

    /**
     * Calculate global dashboard statistics
     */
    protected function calculateGlobalStats(array $deviceIds, array $gatewayIds): array
    {
        // Use single queries with aggregation for better performance
        $deviceStats = DB::table('devices')
            ->selectRaw('
                COUNT(*) as total_devices,
                SUM(CASE WHEN status = "online" THEN 1 ELSE 0 END) as online_devices,
                SUM(CASE WHEN status = "offline" THEN 1 ELSE 0 END) as offline_devices,
                SUM(CASE WHEN status = "warning" THEN 1 ELSE 0 END) as warning_devices
            ')
            ->whereIn('id', $deviceIds)
            ->first();

        $gatewayStats = DB::table('gateways')
            ->selectRaw('
                COUNT(*) as total_gateways,
                SUM(CASE WHEN communication_status = "online" THEN 1 ELSE 0 END) as online_gateways,
                SUM(CASE WHEN communication_status = "offline" THEN 1 ELSE 0 END) as offline_gateways
            ')
            ->whereIn('id', $gatewayIds)
            ->first();

        $alertStats = DB::table('alerts')
            ->selectRaw('
                COUNT(*) as total_alerts,
                SUM(CASE WHEN severity = "critical" AND resolved = 0 THEN 1 ELSE 0 END) as critical_alerts,
                SUM(CASE WHEN severity = "warning" AND resolved = 0 THEN 1 ELSE 0 END) as warning_alerts,
                SUM(CASE WHEN resolved = 0 THEN 1 ELSE 0 END) as active_alerts
            ')
            ->whereIn('device_id', $deviceIds)
            ->where('timestamp', '>=', now()->subDays(7))
            ->first();

        // Get recent readings count
        $recentReadings = DB::table('readings')
            ->whereIn('device_id', $deviceIds)
            ->where('timestamp', '>=', now()->subHour())
            ->count();

        return [
            'devices' => (array) $deviceStats,
            'gateways' => (array) $gatewayStats,
            'alerts' => (array) $alertStats,
            'recent_readings' => $recentReadings,
            'system_health' => $this->calculateSystemHealth($deviceStats, $gatewayStats, $alertStats),
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * Calculate gateway-specific statistics
     */
    protected function calculateGatewayStats(array $deviceIds, int $gatewayId): array
    {
        $deviceStats = DB::table('devices')
            ->selectRaw('
                COUNT(*) as total_devices,
                SUM(CASE WHEN status = "online" THEN 1 ELSE 0 END) as online_devices,
                SUM(CASE WHEN status = "offline" THEN 1 ELSE 0 END) as offline_devices
            ')
            ->whereIn('id', $deviceIds)
            ->first();

        $alertStats = DB::table('alerts')
            ->selectRaw('
                COUNT(*) as total_alerts,
                SUM(CASE WHEN resolved = 0 THEN 1 ELSE 0 END) as active_alerts
            ')
            ->whereIn('device_id', $deviceIds)
            ->where('timestamp', '>=', now()->subDays(1))
            ->first();

        $recentReadings = DB::table('readings')
            ->whereIn('device_id', $deviceIds)
            ->where('timestamp', '>=', now()->subMinutes(30))
            ->count();

        return [
            'gateway_id' => $gatewayId,
            'devices' => (array) $deviceStats,
            'alerts' => (array) $alertStats,
            'recent_readings' => $recentReadings,
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * Calculate system health score
     */
    protected function calculateSystemHealth($deviceStats, $gatewayStats, $alertStats): array
    {
        $totalDevices = $deviceStats->total_devices ?? 0;
        $onlineDevices = $deviceStats->online_devices ?? 0;
        $totalGateways = $gatewayStats->total_gateways ?? 0;
        $onlineGateways = $gatewayStats->online_gateways ?? 0;
        $criticalAlerts = $alertStats->critical_alerts ?? 0;

        if ($totalDevices === 0 || $totalGateways === 0) {
            return ['score' => 0, 'status' => 'unknown'];
        }

        $deviceHealth = $totalDevices > 0 ? ($onlineDevices / $totalDevices) * 100 : 0;
        $gatewayHealth = $totalGateways > 0 ? ($onlineGateways / $totalGateways) * 100 : 0;
        $alertPenalty = min($criticalAlerts * 10, 50); // Max 50% penalty for alerts

        $overallHealth = (($deviceHealth + $gatewayHealth) / 2) - $alertPenalty;
        $overallHealth = max(0, min(100, $overallHealth));

        $status = 'excellent';
        if ($overallHealth < 90) $status = 'good';
        if ($overallHealth < 70) $status = 'warning';
        if ($overallHealth < 50) $status = 'critical';

        return [
            'score' => round($overallHealth, 1),
            'status' => $status,
            'device_health' => round($deviceHealth, 1),
            'gateway_health' => round($gatewayHealth, 1),
            'alert_impact' => $alertPenalty
        ];
    }

    /**
     * Get empty statistics structure
     */
    protected function getEmptyStats(): array
    {
        return [
            'devices' => ['total_devices' => 0, 'online_devices' => 0, 'offline_devices' => 0],
            'gateways' => ['total_gateways' => 0, 'online_gateways' => 0, 'offline_gateways' => 0],
            'alerts' => ['total_alerts' => 0, 'critical_alerts' => 0, 'active_alerts' => 0],
            'recent_readings' => 0,
            'system_health' => ['score' => 0, 'status' => 'unknown'],
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * Invalidate user permission cache
     */
    public function invalidateUserPermissionCache(int $userId): void
    {
        $patterns = [
            self::CACHE_PREFIX . "user_permissions_{$userId}",
            self::CACHE_PREFIX . "dashboard_stats_{$userId}_*"
        ];

        foreach ($patterns as $pattern) {
            if (str_contains($pattern, '*')) {
                // For wildcard patterns, we need to clear all matching keys
                $this->clearCachePattern($pattern);
            } else {
                Cache::forget($pattern);
            }
        }
    }

    /**
     * Clear cache keys matching a pattern
     */
    protected function clearCachePattern(string $pattern): void
    {
        try {
            // This is a simplified implementation
            // In production, you might want to use Redis SCAN or similar
            $prefix = str_replace('*', '', $pattern);
            
            // Get all cache keys (this is Redis-specific)
            if (Cache::getStore() instanceof \Illuminate\Cache\RedisStore) {
                $redis = Cache::getStore()->getRedis();
                $keys = $redis->keys($prefix . '*');
                
                if (!empty($keys)) {
                    $redis->del($keys);
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to clear cache pattern', [
                'pattern' => $pattern,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get performance metrics
     */
    public function getPerformanceMetrics(): array
    {
        return [
            'cache_hit_rate' => $this->getCacheHitRate(),
            'average_query_time' => $this->getAverageQueryTime(),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'active_connections' => $this->getActiveConnections(),
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * Get cache hit rate (simplified implementation)
     */
    protected function getCacheHitRate(): float
    {
        // This would need to be implemented based on your cache driver
        // For now, return a placeholder
        return 85.5;
    }

    /**
     * Get average query time
     */
    protected function getAverageQueryTime(): float
    {
        // This would need to be implemented with query logging
        // For now, return a placeholder
        return 0.025; // 25ms average
    }

    /**
     * Get active database connections
     */
    protected function getActiveConnections(): int
    {
        try {
            $result = DB::select('SHOW STATUS LIKE "Threads_connected"');
            return $result[0]->Value ?? 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Warm up caches for better performance
     */
    public function warmUpCaches(): void
    {
        Log::info('Starting cache warm-up process');

        // Get all active users
        $users = User::where('role', '!=', 'inactive')->pluck('id');

        foreach ($users as $userId) {
            try {
                // Warm up user permissions cache
                $this->getCachedUserPermissions($userId);
                
                // Warm up dashboard stats cache
                $this->getCachedDashboardStats($userId, 'global');
                
                // Small delay to prevent overwhelming the system
                usleep(10000); // 10ms
            } catch (\Exception $e) {
                Log::warning('Failed to warm up cache for user', [
                    'user_id' => $userId,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('Cache warm-up process completed');
    }
}