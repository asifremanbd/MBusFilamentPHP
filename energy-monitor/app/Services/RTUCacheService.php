<?php

namespace App\Services;

use App\Models\Gateway;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Carbon\Carbon;

class RTUCacheService
{
    private const CACHE_PREFIX = 'rtu_';
    private const DEFAULT_TTL = 300; // 5 minutes
    private const LONG_TTL = 3600; // 1 hour
    private const SHORT_TTL = 60; // 1 minute

    /**
     * Cache RTU system health data
     */
    public function cacheSystemHealth(Gateway $gateway, array $data): void
    {
        $key = $this->getSystemHealthKey($gateway->id);
        Cache::put($key, $data, self::DEFAULT_TTL);
        
        // Also cache in Redis for real-time updates
        $this->cacheInRedis($key, $data, self::DEFAULT_TTL);
    }

    /**
     * Get cached RTU system health data
     */
    public function getSystemHealth(Gateway $gateway): ?array
    {
        $key = $this->getSystemHealthKey($gateway->id);
        
        // Try Redis first for real-time data
        $data = $this->getFromRedis($key);
        if ($data !== null) {
            return $data;
        }
        
        // Fallback to Laravel cache
        return Cache::get($key);
    }

    /**
     * Cache RTU network status data
     */
    public function cacheNetworkStatus(Gateway $gateway, array $data): void
    {
        $key = $this->getNetworkStatusKey($gateway->id);
        Cache::put($key, $data, self::DEFAULT_TTL);
        $this->cacheInRedis($key, $data, self::DEFAULT_TTL);
    }

    /**
     * Get cached RTU network status data
     */
    public function getNetworkStatus(Gateway $gateway): ?array
    {
        $key = $this->getNetworkStatusKey($gateway->id);
        
        $data = $this->getFromRedis($key);
        if ($data !== null) {
            return $data;
        }
        
        return Cache::get($key);
    }

    /**
     * Cache RTU I/O status data
     */
    public function cacheIOStatus(Gateway $gateway, array $data): void
    {
        $key = $this->getIOStatusKey($gateway->id);
        Cache::put($key, $data, self::SHORT_TTL); // I/O status changes frequently
        $this->cacheInRedis($key, $data, self::SHORT_TTL);
    }

    /**
     * Get cached RTU I/O status data
     */
    public function getIOStatus(Gateway $gateway): ?array
    {
        $key = $this->getIOStatusKey($gateway->id);
        
        $data = $this->getFromRedis($key);
        if ($data !== null) {
            return $data;
        }
        
        return Cache::get($key);
    }

    /**
     * Cache RTU alert data with grouping
     */
    public function cacheAlerts(Gateway $gateway, array $data, array $filters = []): void
    {
        $key = $this->getAlertsKey($gateway->id, $filters);
        Cache::put($key, $data, self::DEFAULT_TTL);
        $this->cacheInRedis($key, $data, self::DEFAULT_TTL);
    }

    /**
     * Get cached RTU alert data
     */
    public function getAlerts(Gateway $gateway, array $filters = []): ?array
    {
        $key = $this->getAlertsKey($gateway->id, $filters);
        
        $data = $this->getFromRedis($key);
        if ($data !== null) {
            return $data;
        }
        
        return Cache::get($key);
    }

    /**
     * Cache RTU trend data
     */
    public function cacheTrendData(Gateway $gateway, string $timeRange, array $data, array $metrics = []): void
    {
        $key = $this->getTrendDataKey($gateway->id, $timeRange, $metrics);
        $ttl = $this->getTrendDataTTL($timeRange);
        
        Cache::put($key, $data, $ttl);
        $this->cacheInRedis($key, $data, $ttl);
    }

    /**
     * Get cached RTU trend data
     */
    public function getTrendData(Gateway $gateway, string $timeRange, array $metrics = []): ?array
    {
        $key = $this->getTrendDataKey($gateway->id, $timeRange, $metrics);
        
        $data = $this->getFromRedis($key);
        if ($data !== null) {
            return $data;
        }
        
        return Cache::get($key);
    }

    /**
     * Cache RTU dashboard configuration
     */
    public function cacheDashboardConfig(int $userId, string $dashboardType, array $config): void
    {
        $key = $this->getDashboardConfigKey($userId, $dashboardType);
        Cache::put($key, $config, self::LONG_TTL);
    }

    /**
     * Get cached RTU dashboard configuration
     */
    public function getDashboardConfig(int $userId, string $dashboardType): ?array
    {
        $key = $this->getDashboardConfigKey($userId, $dashboardType);
        return Cache::get($key);
    }

    /**
     * Cache RTU gateway list for quick access
     */
    public function cacheRTUGatewayList(array $gateways): void
    {
        $key = $this->getRTUGatewayListKey();
        Cache::put($key, $gateways, self::DEFAULT_TTL);
    }

    /**
     * Get cached RTU gateway list
     */
    public function getRTUGatewayList(): ?array
    {
        $key = $this->getRTUGatewayListKey();
        return Cache::get($key);
    }

    /**
     * Invalidate all caches for a specific RTU gateway
     */
    public function invalidateGatewayCache(Gateway $gateway): void
    {
        $patterns = [
            $this->getSystemHealthKey($gateway->id),
            $this->getNetworkStatusKey($gateway->id),
            $this->getIOStatusKey($gateway->id),
            $this->getAlertsKey($gateway->id) . '*',
            $this->getTrendDataKey($gateway->id) . '*'
        ];

        foreach ($patterns as $pattern) {
            if (str_contains($pattern, '*')) {
                $this->invalidateByPattern($pattern);
            } else {
                Cache::forget($pattern);
                $this->deleteFromRedis($pattern);
            }
        }
    }

    /**
     * Warm up cache for RTU gateway
     */
    public function warmUpGatewayCache(Gateway $gateway, RTUDataService $rtuDataService): void
    {
        // Pre-load frequently accessed data
        $systemHealth = $rtuDataService->getSystemHealth($gateway);
        $this->cacheSystemHealth($gateway, $systemHealth);

        $networkStatus = $rtuDataService->getNetworkStatus($gateway);
        $this->cacheNetworkStatus($gateway, $networkStatus);

        $ioStatus = $rtuDataService->getIOStatus($gateway);
        $this->cacheIOStatus($gateway, $ioStatus);

        // Pre-load common trend data
        $commonTimeRanges = ['1h', '6h', '24h'];
        foreach ($commonTimeRanges as $timeRange) {
            $trendData = $rtuDataService->getTrendData($gateway, $timeRange);
            $this->cacheTrendData($gateway, $timeRange, $trendData);
        }
    }

    /**
     * Get cache statistics for RTU data
     */
    public function getCacheStats(): array
    {
        $stats = [
            'total_keys' => 0,
            'hit_rate' => 0.0,
            'memory_usage' => 0,
            'key_breakdown' => []
        ];

        // Get cache statistics (implementation depends on cache driver)
        if (Cache::getStore() instanceof \Illuminate\Cache\RedisStore) {
            $stats = $this->getRedisStats();
        }

        return $stats;
    }

    /**
     * Implement cache warming strategy for multiple gateways
     */
    public function warmUpMultipleGateways(array $gateways, RTUDataService $rtuDataService): void
    {
        foreach ($gateways as $gateway) {
            try {
                $this->warmUpGatewayCache($gateway, $rtuDataService);
            } catch (\Exception $e) {
                // Log error but continue with other gateways
                \Log::warning("Failed to warm up cache for gateway {$gateway->id}: " . $e->getMessage());
            }
        }
    }

    /**
     * Implement cache refresh strategy
     */
    public function refreshExpiredCaches(): int
    {
        $refreshed = 0;
        $expiredKeys = $this->getExpiredKeys();

        foreach ($expiredKeys as $key) {
            if ($this->shouldRefreshKey($key)) {
                $this->refreshCacheKey($key);
                $refreshed++;
            }
        }

        return $refreshed;
    }

    /**
     * Implement intelligent cache preloading
     */
    public function preloadFrequentlyAccessedData(): void
    {
        // Get most frequently accessed gateways
        $frequentGateways = $this->getFrequentlyAccessedGateways();
        
        foreach ($frequentGateways as $gatewayId) {
            $gateway = Gateway::find($gatewayId);
            if ($gateway && $gateway->isRTUGateway()) {
                $this->preloadGatewayData($gateway);
            }
        }
    }

    /**
     * Cache layer for real-time updates
     */
    public function cacheRealTimeUpdate(Gateway $gateway, string $dataType, array $data): void
    {
        $key = $this->getRealTimeKey($gateway->id, $dataType);
        
        // Use short TTL for real-time data
        Cache::put($key, $data, self::SHORT_TTL);
        $this->cacheInRedis($key, $data, self::SHORT_TTL);
        
        // Publish to Redis channel for WebSocket updates
        $this->publishRealTimeUpdate($gateway->id, $dataType, $data);
    }

    /**
     * Get real-time cached data
     */
    public function getRealTimeData(Gateway $gateway, string $dataType): ?array
    {
        $key = $this->getRealTimeKey($gateway->id, $dataType);
        
        $data = $this->getFromRedis($key);
        if ($data !== null) {
            return $data;
        }
        
        return Cache::get($key);
    }

    /**
     * Private helper methods for cache key generation
     */
    private function getSystemHealthKey(int $gatewayId): string
    {
        return self::CACHE_PREFIX . "system_health_{$gatewayId}";
    }

    private function getNetworkStatusKey(int $gatewayId): string
    {
        return self::CACHE_PREFIX . "network_status_{$gatewayId}";
    }

    private function getIOStatusKey(int $gatewayId): string
    {
        return self::CACHE_PREFIX . "io_status_{$gatewayId}";
    }

    private function getAlertsKey(int $gatewayId, array $filters = []): string
    {
        $filterHash = empty($filters) ? 'default' : md5(serialize($filters));
        return self::CACHE_PREFIX . "alerts_{$gatewayId}_{$filterHash}";
    }

    private function getTrendDataKey(int $gatewayId, string $timeRange = '24h', array $metrics = []): string
    {
        $metricsHash = empty($metrics) ? 'all' : md5(serialize($metrics));
        return self::CACHE_PREFIX . "trends_{$gatewayId}_{$timeRange}_{$metricsHash}";
    }

    private function getDashboardConfigKey(int $userId, string $dashboardType): string
    {
        return self::CACHE_PREFIX . "config_{$userId}_{$dashboardType}";
    }

    private function getRTUGatewayListKey(): string
    {
        return self::CACHE_PREFIX . "gateway_list";
    }

    private function getRealTimeKey(int $gatewayId, string $dataType): string
    {
        return self::CACHE_PREFIX . "realtime_{$gatewayId}_{$dataType}";
    }

    /**
     * Redis-specific caching methods
     */
    private function cacheInRedis(string $key, array $data, int $ttl): void
    {
        try {
            Redis::setex($key, $ttl, json_encode($data));
        } catch (\Exception $e) {
            // Redis not available, continue with Laravel cache only
            \Log::debug("Redis caching failed for key {$key}: " . $e->getMessage());
        }
    }

    private function getFromRedis(string $key): ?array
    {
        try {
            $data = Redis::get($key);
            return $data ? json_decode($data, true) : null;
        } catch (\Exception $e) {
            // Redis not available, return null to fallback to Laravel cache
            return null;
        }
    }

    private function deleteFromRedis(string $key): void
    {
        try {
            Redis::del($key);
        } catch (\Exception $e) {
            // Redis not available, ignore
        }
    }

    private function publishRealTimeUpdate(int $gatewayId, string $dataType, array $data): void
    {
        try {
            $channel = "rtu_updates_{$gatewayId}";
            $message = json_encode([
                'gateway_id' => $gatewayId,
                'data_type' => $dataType,
                'data' => $data,
                'timestamp' => Carbon::now()->toISOString()
            ]);
            
            Redis::publish($channel, $message);
        } catch (\Exception $e) {
            // Redis not available, skip real-time updates
        }
    }

    /**
     * Cache management helper methods
     */
    private function getTrendDataTTL(string $timeRange): int
    {
        return match($timeRange) {
            '1h' => self::SHORT_TTL,
            '6h' => self::DEFAULT_TTL,
            '24h' => self::DEFAULT_TTL,
            '7d' => self::LONG_TTL,
            '30d' => self::LONG_TTL,
            default => self::DEFAULT_TTL
        };
    }

    private function invalidateByPattern(string $pattern): void
    {
        // Implementation depends on cache driver
        // For Redis, use SCAN with pattern matching
        try {
            $keys = Redis::keys(str_replace('*', '*', $pattern));
            if (!empty($keys)) {
                Redis::del($keys);
            }
        } catch (\Exception $e) {
            // Fallback to cache flush if pattern matching not available
            Cache::flush();
        }
    }

    private function getExpiredKeys(): array
    {
        // Implementation depends on cache driver
        return [];
    }

    private function shouldRefreshKey(string $key): bool
    {
        // Implement logic to determine if key should be refreshed
        return str_contains($key, self::CACHE_PREFIX);
    }

    private function refreshCacheKey(string $key): void
    {
        // Implementation depends on specific key type
        Cache::forget($key);
    }

    private function getFrequentlyAccessedGateways(): array
    {
        // Implementation could use analytics or access logs
        return Gateway::where('gateway_type', 'teltonika_rut956')
            ->where('communication_status', 'online')
            ->pluck('id')
            ->toArray();
    }

    private function preloadGatewayData(Gateway $gateway): void
    {
        // Preload essential data without full service calls
        $this->warmUpGatewayCache($gateway, app(RTUDataService::class));
    }

    private function getRedisStats(): array
    {
        try {
            $info = Redis::info();
            return [
                'total_keys' => $info['db0']['keys'] ?? 0,
                'memory_usage' => $info['used_memory'] ?? 0,
                'hit_rate' => $this->calculateHitRate($info),
                'key_breakdown' => $this->getKeyBreakdown()
            ];
        } catch (\Exception $e) {
            return [
                'total_keys' => 0,
                'memory_usage' => 0,
                'hit_rate' => 0.0,
                'key_breakdown' => []
            ];
        }
    }

    private function calculateHitRate(array $info): float
    {
        $hits = $info['keyspace_hits'] ?? 0;
        $misses = $info['keyspace_misses'] ?? 0;
        $total = $hits + $misses;
        
        return $total > 0 ? ($hits / $total) * 100 : 0.0;
    }

    private function getKeyBreakdown(): array
    {
        try {
            $keys = Redis::keys(self::CACHE_PREFIX . '*');
            $breakdown = [];
            
            foreach ($keys as $key) {
                $type = $this->extractKeyType($key);
                $breakdown[$type] = ($breakdown[$type] ?? 0) + 1;
            }
            
            return $breakdown;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function extractKeyType(string $key): string
    {
        if (str_contains($key, 'system_health')) return 'system_health';
        if (str_contains($key, 'network_status')) return 'network_status';
        if (str_contains($key, 'io_status')) return 'io_status';
        if (str_contains($key, 'alerts')) return 'alerts';
        if (str_contains($key, 'trends')) return 'trends';
        if (str_contains($key, 'config')) return 'config';
        if (str_contains($key, 'realtime')) return 'realtime';
        
        return 'other';
    }
}