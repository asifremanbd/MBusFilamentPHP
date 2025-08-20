<?php

namespace App\Services;

use App\Models\Gateway;
use App\Models\Alert;
use App\Models\Reading;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class RTUQueryOptimizationService
{
    /**
     * Get RTU gateways with optimized query
     */
    public function getRTUGateways(): Builder
    {
        return Gateway::select([
            'id', 'name', 'fixed_ip', 'gateway_type', 'communication_status',
            'cpu_load', 'memory_usage', 'uptime_hours', 'rssi', 'rsrp', 'rsrq', 'sinr',
            'di1_status', 'di2_status', 'do1_status', 'do2_status', 'analog_input_voltage',
            'last_system_update'
        ])
        ->where('gateway_type', 'teltonika_rut956')
        ->with(['devices' => function ($query) {
            $query->select('id', 'gateway_id', 'name', 'status');
        }]);
    }

    /**
     * Get RTU gateway with related data using optimized queries
     */
    public function getRTUGatewayWithData(int $gatewayId): ?Gateway
    {
        return $this->getRTUGateways()
            ->where('id', $gatewayId)
            ->with([
                'devices.registers' => function ($query) {
                    $query->select('id', 'device_id', 'name', 'address', 'data_type');
                },
                'alerts' => function ($query) {
                    $query->select('id', 'gateway_id', 'device_id', 'type', 'severity', 'message', 'status', 'created_at')
                        ->where('status', 'active')
                        ->orderBy('severity', 'desc')
                        ->orderBy('created_at', 'desc')
                        ->limit(50);
                }
            ])
            ->first();
    }

    /**
     * Get optimized alert data for RTU gateway
     */
    public function getOptimizedAlerts(Gateway $gateway, array $filters = []): array
    {
        $cacheKey = "rtu_alerts_{$gateway->id}_" . md5(serialize($filters));
        
        return Cache::remember($cacheKey, 300, function () use ($gateway, $filters) {
            $query = Alert::select([
                'id', 'gateway_id', 'device_id', 'type', 'severity', 'message', 
                'status', 'created_at', 'updated_at'
            ])
            ->where('gateway_id', $gateway->id)
            ->where('status', 'active');

            // Apply filters efficiently
            if (!empty($filters['severity'])) {
                $query->whereIn('severity', $filters['severity']);
            }

            if (!empty($filters['device_ids'])) {
                $query->whereIn('device_id', $filters['device_ids']);
            }

            if (!empty($filters['time_range'])) {
                $startTime = $this->getTimeRangeStart($filters['time_range']);
                $query->where('created_at', '>=', $startTime);
            }

            // Use database aggregation for grouping
            $alerts = $query->orderBy('severity', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();

            return $this->groupAlertsEfficiently($alerts);
        });
    }

    /**
     * Get optimized trend data using database aggregation
     */
    public function getOptimizedTrendData(Gateway $gateway, string $timeRange, array $metrics = []): array
    {
        $cacheKey = "rtu_trends_{$gateway->id}_{$timeRange}_" . md5(serialize($metrics));
        
        return Cache::remember($cacheKey, 600, function () use ($gateway, $timeRange, $metrics) {
            $endTime = Carbon::now();
            $startTime = $this->getTimeRangeStart($timeRange, $endTime);
            
            // Use database aggregation for better performance
            $interval = $this->getAggregationInterval($timeRange);
            
            $query = Reading::select([
                DB::raw("DATE_FORMAT(timestamp, '{$interval}') as time_bucket"),
                DB::raw('AVG(rssi) as avg_rssi'),
                DB::raw('AVG(cpu_load) as avg_cpu_load'),
                DB::raw('AVG(memory_usage) as avg_memory_usage'),
                DB::raw('AVG(analog_voltage) as avg_analog_voltage'),
                DB::raw('COUNT(*) as reading_count')
            ])
            ->where('gateway_id', $gateway->id)
            ->whereBetween('timestamp', [$startTime, $endTime])
            ->groupBy('time_bucket')
            ->orderBy('time_bucket');

            $readings = $query->get();

            if ($readings->isEmpty()) {
                return [
                    'has_data' => false,
                    'message' => 'No data available for selected period',
                    'available_metrics' => []
                ];
            }

            return [
                'has_data' => true,
                'time_range' => $timeRange,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'metrics' => $this->formatTrendMetrics($readings, $metrics),
                'available_metrics' => ['signal_strength', 'cpu_load', 'memory_usage', 'analog_input']
            ];
        });
    }

    /**
     * Bulk update RTU gateway data efficiently
     */
    public function bulkUpdateRTUData(array $gatewayData): void
    {
        if (empty($gatewayData)) {
            return;
        }

        // Use batch update for better performance
        $cases = [];
        $ids = [];
        $updateTime = Carbon::now();

        foreach ($gatewayData as $gatewayId => $data) {
            $ids[] = $gatewayId;
            
            foreach ($data as $field => $value) {
                if (!isset($cases[$field])) {
                    $cases[$field] = [];
                }
                $cases[$field][] = "WHEN id = {$gatewayId} THEN " . DB::getPdo()->quote($value);
            }
        }

        if (!empty($cases)) {
            $updateQuery = "UPDATE gateways SET ";
            $setClauses = [];

            foreach ($cases as $field => $whenClauses) {
                $setClauses[] = "{$field} = CASE " . implode(' ', $whenClauses) . " ELSE {$field} END";
            }

            $setClauses[] = "last_system_update = " . DB::getPdo()->quote($updateTime);
            $updateQuery .= implode(', ', $setClauses);
            $updateQuery .= " WHERE id IN (" . implode(',', $ids) . ")";

            DB::statement($updateQuery);
        }

        // Clear related caches
        foreach ($ids as $gatewayId) {
            $this->clearGatewayCaches($gatewayId);
        }
    }

    /**
     * Get optimized device readings with pagination
     */
    public function getOptimizedReadings(Gateway $gateway, array $options = []): array
    {
        $limit = $options['limit'] ?? 1000;
        $offset = $options['offset'] ?? 0;
        $timeRange = $options['time_range'] ?? '24h';

        $cacheKey = "rtu_readings_{$gateway->id}_{$timeRange}_{$limit}_{$offset}";
        
        return Cache::remember($cacheKey, 300, function () use ($gateway, $limit, $offset, $timeRange) {
            $startTime = $this->getTimeRangeStart($timeRange);
            
            $readings = Reading::select([
                'id', 'gateway_id', 'device_id', 'register_id', 'value', 'timestamp'
            ])
            ->where('gateway_id', $gateway->id)
            ->where('timestamp', '>=', $startTime)
            ->with(['device:id,name', 'register:id,name,unit'])
            ->orderBy('timestamp', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get();

            return [
                'readings' => $readings,
                'total_count' => $this->getReadingsCount($gateway, $startTime),
                'has_more' => $readings->count() === $limit
            ];
        });
    }

    /**
     * Optimize database indexes for RTU queries
     */
    public function ensureOptimalIndexes(): array
    {
        $indexes = [];

        // Check and create composite indexes for common RTU queries
        $requiredIndexes = [
            'gateways' => [
                ['gateway_type', 'communication_status'],
                ['gateway_type', 'last_system_update'],
                ['communication_status', 'created_at']
            ],
            'alerts' => [
                ['gateway_id', 'status', 'severity'],
                ['gateway_id', 'created_at', 'status'],
                ['severity', 'created_at']
            ],
            'readings' => [
                ['gateway_id', 'timestamp'],
                ['gateway_id', 'device_id', 'timestamp'],
                ['timestamp', 'gateway_id']
            ]
        ];

        foreach ($requiredIndexes as $table => $tableIndexes) {
            foreach ($tableIndexes as $columns) {
                $indexName = $table . '_' . implode('_', $columns) . '_idx';
                
                if (!$this->indexExists($table, $indexName)) {
                    $this->createIndex($table, $columns, $indexName);
                    $indexes[] = "Created index: {$indexName} on {$table}";
                }
            }
        }

        return $indexes;
    }

    /**
     * Get database performance statistics for RTU queries
     */
    public function getPerformanceStats(): array
    {
        return [
            'slow_queries' => $this->getSlowQueries(),
            'index_usage' => $this->getIndexUsage(),
            'cache_hit_rate' => $this->getCacheHitRate(),
            'query_recommendations' => $this->getQueryRecommendations()
        ];
    }

    /**
     * Clear all RTU-related caches
     */
    public function clearAllRTUCaches(): void
    {
        $patterns = [
            'rtu_alerts_*',
            'rtu_trends_*',
            'rtu_readings_*',
            'rtu_system_health_*',
            'rtu_network_status_*',
            'rtu_io_status_*'
        ];

        foreach ($patterns as $pattern) {
            Cache::flush(); // In production, use more specific cache clearing
        }
    }

    /**
     * Private helper methods
     */
    private function getTimeRangeStart(string $timeRange, Carbon $endTime = null): Carbon
    {
        $endTime = $endTime ?? Carbon::now();
        
        return match($timeRange) {
            '1h' => $endTime->copy()->subHour(),
            '6h' => $endTime->copy()->subHours(6),
            '24h' => $endTime->copy()->subDay(),
            '7d' => $endTime->copy()->subWeek(),
            '30d' => $endTime->copy()->subMonth(),
            default => $endTime->copy()->subDay()
        };
    }

    private function getAggregationInterval(string $timeRange): string
    {
        return match($timeRange) {
            '1h' => '%Y-%m-%d %H:%i:00',
            '6h' => '%Y-%m-%d %H:00:00',
            '24h' => '%Y-%m-%d %H:00:00',
            '7d' => '%Y-%m-%d 00:00:00',
            '30d' => '%Y-%m-%d 00:00:00',
            default => '%Y-%m-%d %H:00:00'
        };
    }

    private function groupAlertsEfficiently($alerts): array
    {
        $grouped = [];
        $counts = ['critical' => 0, 'warning' => 0, 'info' => 0];

        foreach ($alerts as $alert) {
            $type = $this->normalizeAlertType($alert->type);
            $counts[$alert->severity]++;

            if (!isset($grouped[$type])) {
                $grouped[$type] = (object) [
                    'type' => $type,
                    'message' => $alert->message,
                    'severity' => $alert->severity,
                    'count' => 1,
                    'latest_timestamp' => $alert->created_at,
                    'first_occurrence' => $alert->created_at,
                    'is_grouped' => false
                ];
            } else {
                $grouped[$type]->count++;
                $grouped[$type]->is_grouped = true;
                if ($alert->created_at > $grouped[$type]->latest_timestamp) {
                    $grouped[$type]->latest_timestamp = $alert->created_at;
                    $grouped[$type]->message = $alert->message;
                }
            }
        }

        return [
            'critical_count' => $counts['critical'],
            'warning_count' => $counts['warning'],
            'info_count' => $counts['info'],
            'grouped_alerts' => array_values($grouped),
            'has_alerts' => !empty($grouped),
            'status_summary' => $this->getStatusSummary($counts)
        ];
    }

    private function formatTrendMetrics($readings, array $requestedMetrics): array
    {
        $metrics = [];
        
        foreach ($readings as $reading) {
            $timestamp = $reading->time_bucket;
            
            if (empty($requestedMetrics) || in_array('signal_strength', $requestedMetrics)) {
                $metrics['signal_strength'][] = [
                    'timestamp' => $timestamp,
                    'value' => $reading->avg_rssi
                ];
            }
            
            if (empty($requestedMetrics) || in_array('cpu_load', $requestedMetrics)) {
                $metrics['cpu_load'][] = [
                    'timestamp' => $timestamp,
                    'value' => $reading->avg_cpu_load
                ];
            }
            
            if (empty($requestedMetrics) || in_array('memory_usage', $requestedMetrics)) {
                $metrics['memory_usage'][] = [
                    'timestamp' => $timestamp,
                    'value' => $reading->avg_memory_usage
                ];
            }
            
            if (empty($requestedMetrics) || in_array('analog_input', $requestedMetrics)) {
                $metrics['analog_input'][] = [
                    'timestamp' => $timestamp,
                    'value' => $reading->avg_analog_voltage
                ];
            }
        }

        return $metrics;
    }

    private function clearGatewayCaches(int $gatewayId): void
    {
        $patterns = [
            "rtu_alerts_{$gatewayId}_*",
            "rtu_trends_{$gatewayId}_*",
            "rtu_readings_{$gatewayId}_*",
            "rtu_system_health_{$gatewayId}",
            "rtu_network_status_{$gatewayId}",
            "rtu_io_status_{$gatewayId}"
        ];

        // In production, implement pattern-based cache clearing
        Cache::flush();
    }

    private function getReadingsCount(Gateway $gateway, Carbon $startTime): int
    {
        return Reading::where('gateway_id', $gateway->id)
            ->where('timestamp', '>=', $startTime)
            ->count();
    }

    private function normalizeAlertType(string $type): string
    {
        return trim(strtolower(preg_replace('/[^a-zA-Z0-9\s]/', '', $type)));
    }

    private function getStatusSummary(array $counts): string
    {
        if ($counts['critical'] > 0) {
            return $counts['critical'] === 1 ? '1 Critical Alert' : "{$counts['critical']} Critical Alerts";
        }

        if ($counts['warning'] > 0) {
            return $counts['warning'] === 1 ? '1 Warning' : "{$counts['warning']} Warnings";
        }

        return 'All Systems OK';
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $result = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
        return !empty($result);
    }

    private function createIndex(string $table, array $columns, string $indexName): void
    {
        $columnList = implode(', ', $columns);
        DB::statement("CREATE INDEX {$indexName} ON {$table} ({$columnList})");
    }

    private function getSlowQueries(): array
    {
        // Implementation depends on database configuration
        return [];
    }

    private function getIndexUsage(): array
    {
        // Implementation depends on database configuration
        return [];
    }

    private function getCacheHitRate(): float
    {
        // Implementation depends on cache configuration
        return 0.0;
    }

    private function getQueryRecommendations(): array
    {
        return [
            'Consider adding composite indexes for frequently queried columns',
            'Use database aggregation instead of application-level calculations',
            'Implement proper caching for frequently accessed data',
            'Use pagination for large result sets'
        ];
    }
}