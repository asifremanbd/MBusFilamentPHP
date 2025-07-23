<?php

namespace App\Widgets\Gateway;

use App\Widgets\BaseWidget;
use App\Models\Gateway;
use App\Models\Device;
use App\Models\Reading;
use App\Models\Alert;
use Illuminate\Support\Facades\DB;

class GatewayStatsWidget extends BaseWidget
{
    protected function getData(): array
    {
        $gateway = $this->getGateway();
        $authorizedDevices = $this->permissionService->getAuthorizedDevices($this->user, $this->gatewayId);

        return [
            'gateway_info' => $this->getGatewayInfo($gateway),
            'communication_status' => $this->getCommunicationStatus($gateway, $authorizedDevices),
            'device_health_indicators' => $this->getDeviceHealthIndicators($authorizedDevices),
            'performance_metrics' => $this->getPerformanceMetrics($gateway, $authorizedDevices),
            'operational_statistics' => $this->getOperationalStatistics($gateway, $authorizedDevices),
            'historical_trends' => $this->getHistoricalTrends($gateway, $authorizedDevices),
        ];
    }

    protected function getWidgetType(): string
    {
        return 'gateway-stats';
    }

    protected function getWidgetName(): string
    {
        return 'Gateway Statistics';
    }

    protected function getWidgetDescription(): string
    {
        return 'Gateway communication status and device health indicators';
    }

    protected function getWidgetCategory(): string
    {
        return 'overview';
    }

    protected function getWidgetPriority(): int
    {
        return 15;
    }

    protected function requiresGateway(): bool
    {
        return true;
    }

    protected function supportsRealTimeUpdates(): bool
    {
        return true;
    }

    protected function getRealTimeUpdateInterval(): int
    {
        return 60; // Update every minute
    }

    /**
     * Get gateway information
     */
    private function getGatewayInfo($gateway): array
    {
        if (!$gateway) {
            return [
                'id' => null,
                'name' => 'Unknown Gateway',
                'location' => null,
                'status' => 'unknown',
            ];
        }

        return [
            'id' => $gateway->id,
            'name' => $gateway->name,
            'location' => $gateway->gnss_location,
            'ip_address' => $gateway->ip_address ?? 'Unknown',
            'port' => $gateway->port ?? 502,
            'status' => $this->getGatewayStatus($gateway),
            'last_seen' => $this->getLastCommunication($gateway),
            'uptime' => $this->calculateUptime($gateway),
            'firmware_version' => $gateway->firmware_version ?? 'Unknown',
            'installation_date' => $gateway->created_at?->toDateString(),
        ];
    }

    /**
     * Get communication status details
     */
    private function getCommunicationStatus($gateway, $authorizedDevices): array
    {
        if (!$gateway || $authorizedDevices->isEmpty()) {
            return [
                'overall_status' => 'unknown',
                'connection_quality' => 0,
                'response_time' => 0,
                'success_rate' => 0,
                'last_successful_poll' => null,
                'communication_errors' => [],
            ];
        }

        $deviceIds = $authorizedDevices->pluck('id')->toArray();
        
        // Calculate communication metrics
        $recentReadings = Reading::whereIn('device_id', $deviceIds)
            ->where('timestamp', '>=', now()->subHour())
            ->get();

        $totalAttempts = $this->getExpectedReadingCount($authorizedDevices);
        $successfulReadings = $recentReadings->count();
        $successRate = $totalAttempts > 0 ? ($successfulReadings / $totalAttempts) * 100 : 0;

        $avgResponseTime = $this->calculateAverageResponseTime($deviceIds);
        $connectionQuality = $this->calculateConnectionQuality($gateway, $authorizedDevices);
        
        $lastSuccessfulPoll = $recentReadings->max('timestamp');
        $communicationErrors = $this->getCommunicationErrors($gateway, $authorizedDevices);

        $overallStatus = $this->determineOverallStatus($successRate, $connectionQuality, $lastSuccessfulPoll);

        return [
            'overall_status' => $overallStatus,
            'connection_quality' => round($connectionQuality, 1),
            'response_time' => round($avgResponseTime, 2),
            'success_rate' => round($successRate, 1),
            'successful_readings' => $successfulReadings,
            'expected_readings' => $totalAttempts,
            'last_successful_poll' => $lastSuccessfulPoll?->toISOString(),
            'communication_errors' => $communicationErrors,
        ];
    }

    /**
     * Get device health indicators
     */
    private function getDeviceHealthIndicators($authorizedDevices): array
    {
        if ($authorizedDevices->isEmpty()) {
            return [
                'total_devices' => 0,
                'healthy_devices' => 0,
                'warning_devices' => 0,
                'critical_devices' => 0,
                'offline_devices' => 0,
                'health_distribution' => [],
                'device_details' => [],
            ];
        }

        $healthStats = [
            'healthy' => 0,
            'warning' => 0,
            'critical' => 0,
            'offline' => 0,
        ];

        $deviceDetails = [];

        foreach ($authorizedDevices as $device) {
            $healthScore = $this->calculateDeviceHealthScore($device);
            $status = $this->getDeviceHealthStatus($healthScore);
            $healthStats[$status]++;

            $deviceDetails[] = [
                'id' => $device->id,
                'name' => $device->name,
                'slave_id' => $device->slave_id,
                'health_score' => $healthScore,
                'status' => $status,
                'last_reading' => $this->getLastReadingTime($device),
                'alert_count' => $this->getActiveAlertCount($device),
                'data_quality' => $this->getDeviceDataQuality($device),
            ];
        }

        // Sort by health score (worst first)
        usort($deviceDetails, function ($a, $b) {
            return $a['health_score'] <=> $b['health_score'];
        });

        return [
            'total_devices' => $authorizedDevices->count(),
            'healthy_devices' => $healthStats['healthy'],
            'warning_devices' => $healthStats['warning'],
            'critical_devices' => $healthStats['critical'],
            'offline_devices' => $healthStats['offline'],
            'health_distribution' => $healthStats,
            'device_details' => $deviceDetails,
            'average_health_score' => $this->calculateAverageHealthScore($deviceDetails),
        ];
    }

    /**
     * Get performance metrics
     */
    private function getPerformanceMetrics($gateway, $authorizedDevices): array
    {
        if (!$gateway || $authorizedDevices->isEmpty()) {
            return [
                'data_throughput' => 0,
                'polling_efficiency' => 0,
                'error_rate' => 0,
                'availability' => 0,
                'bandwidth_utilization' => 0,
            ];
        }

        $deviceIds = $authorizedDevices->pluck('id')->toArray();

        // Data throughput (readings per minute)
        $recentReadings = Reading::whereIn('device_id', $deviceIds)
            ->where('timestamp', '>=', now()->subHour())
            ->count();
        $dataThroughput = $recentReadings / 60;

        // Polling efficiency (successful polls / total attempts)
        $expectedPolls = $this->getExpectedReadingCount($authorizedDevices);
        $pollingEfficiency = $expectedPolls > 0 ? ($recentReadings / $expectedPolls) * 100 : 0;

        // Error rate
        $errorRate = $this->calculateErrorRate($gateway, $authorizedDevices);

        // Availability (percentage of time gateway was responsive)
        $availability = $this->calculateAvailability($gateway);

        // Bandwidth utilization (estimated)
        $bandwidthUtilization = $this->estimateBandwidthUtilization($recentReadings);

        return [
            'data_throughput' => round($dataThroughput, 2),
            'polling_efficiency' => round($pollingEfficiency, 1),
            'error_rate' => round($errorRate, 2),
            'availability' => round($availability, 1),
            'bandwidth_utilization' => round($bandwidthUtilization, 1),
            'readings_per_hour' => $recentReadings,
            'expected_readings_per_hour' => $expectedPolls,
        ];
    }

    /**
     * Get operational statistics
     */
    private function getOperationalStatistics($gateway, $authorizedDevices): array
    {
        if (!$gateway || $authorizedDevices->isEmpty()) {
            return [
                'total_readings_today' => 0,
                'peak_reading_rate' => 0,
                'average_reading_interval' => 0,
                'data_volume_mb' => 0,
                'operational_hours' => 0,
            ];
        }

        $deviceIds = $authorizedDevices->pluck('id')->toArray();

        // Total readings today
        $totalReadingsToday = Reading::whereIn('device_id', $deviceIds)
            ->whereDate('timestamp', today())
            ->count();

        // Peak reading rate (highest readings per hour in last 24 hours)
        $peakReadingRate = $this->calculatePeakReadingRate($deviceIds);

        // Average reading interval
        $avgReadingInterval = $this->calculateAverageReadingInterval($deviceIds);

        // Estimated data volume (assuming ~100 bytes per reading)
        $dataVolumeMb = ($totalReadingsToday * 100) / (1024 * 1024);

        // Operational hours today
        $operationalHours = $this->calculateOperationalHours($gateway);

        return [
            'total_readings_today' => $totalReadingsToday,
            'peak_reading_rate' => round($peakReadingRate, 1),
            'average_reading_interval' => round($avgReadingInterval, 1),
            'data_volume_mb' => round($dataVolumeMb, 2),
            'operational_hours' => round($operationalHours, 1),
            'readings_per_device' => $authorizedDevices->count() > 0 ? round($totalReadingsToday / $authorizedDevices->count(), 1) : 0,
        ];
    }

    /**
     * Get historical trends
     */
    private function getHistoricalTrends($gateway, $authorizedDevices): array
    {
        if (!$gateway || $authorizedDevices->isEmpty()) {
            return [
                'daily_trends' => [],
                'performance_trend' => 'stable',
                'reliability_trend' => 'stable',
            ];
        }

        $deviceIds = $authorizedDevices->pluck('id')->toArray();

        // Get daily reading counts for the last 7 days
        $dailyTrends = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $readingCount = Reading::whereIn('device_id', $deviceIds)
                ->whereDate('timestamp', $date)
                ->count();

            $dailyTrends[] = [
                'date' => $date->toDateString(),
                'reading_count' => $readingCount,
                'availability' => $this->calculateDailyAvailability($gateway, $date),
                'error_rate' => $this->calculateDailyErrorRate($gateway, $authorizedDevices, $date),
            ];
        }

        // Calculate trends
        $performanceTrend = $this->calculatePerformanceTrend($dailyTrends);
        $reliabilityTrend = $this->calculateReliabilityTrend($dailyTrends);

        return [
            'daily_trends' => $dailyTrends,
            'performance_trend' => $performanceTrend,
            'reliability_trend' => $reliabilityTrend,
            'trend_analysis' => $this->analyzeTrends($dailyTrends),
        ];
    }

    /**
     * Helper methods
     */
    private function getGateway(): ?Gateway
    {
        return $this->gatewayId ? Gateway::find($this->gatewayId) : null;
    }

    private function getGatewayStatus($gateway): string
    {
        $lastCommunication = $this->getLastCommunication($gateway);
        
        if (!$lastCommunication) {
            return 'offline';
        }

        $minutesSinceLastComm = $lastCommunication->diffInMinutes(now());
        
        if ($minutesSinceLastComm <= 5) return 'online';
        if ($minutesSinceLastComm <= 15) return 'warning';
        return 'offline';
    }

    private function getLastCommunication($gateway): ?\Carbon\Carbon
    {
        $lastReading = Reading::whereHas('device', function ($query) use ($gateway) {
            $query->where('gateway_id', $gateway->id);
        })
        ->orderBy('timestamp', 'desc')
        ->first();

        return $lastReading?->timestamp;
    }

    private function calculateUptime($gateway): float
    {
        // Calculate uptime percentage for the last 24 hours
        $totalMinutes = 24 * 60;
        $readingIntervals = Reading::whereHas('device', function ($query) use ($gateway) {
            $query->where('gateway_id', $gateway->id);
        })
        ->where('timestamp', '>=', now()->subDay())
        ->selectRaw('TIMESTAMPDIFF(MINUTE, LAG(timestamp) OVER (ORDER BY timestamp), timestamp) as interval_minutes')
        ->get()
        ->pluck('interval_minutes')
        ->filter()
        ->toArray();

        if (empty($readingIntervals)) {
            return 0;
        }

        // Consider gaps > 10 minutes as downtime
        $downtimeMinutes = array_sum(array_filter($readingIntervals, function($interval) {
            return $interval > 10;
        }));

        return max(0, (($totalMinutes - $downtimeMinutes) / $totalMinutes) * 100);
    }

    private function getExpectedReadingCount($authorizedDevices): int
    {
        // Assume each device should report every 5 minutes (12 times per hour)
        return $authorizedDevices->count() * 12;
    }

    private function calculateAverageResponseTime($deviceIds): float
    {
        // This would typically measure actual response times
        // For now, estimate based on reading frequency
        $avgInterval = DB::table('readings')
            ->whereIn('device_id', $deviceIds)
            ->where('timestamp', '>=', now()->subHour())
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, LAG(timestamp) OVER (PARTITION BY device_id ORDER BY timestamp), timestamp)) as avg_interval')
            ->value('avg_interval');

        return $avgInterval ? $avgInterval / 1000 : 0; // Convert to milliseconds
    }

    private function calculateConnectionQuality($gateway, $authorizedDevices): float
    {
        $deviceIds = $authorizedDevices->pluck('id')->toArray();
        
        // Base quality on reading consistency and frequency
        $recentReadings = Reading::whereIn('device_id', $deviceIds)
            ->where('timestamp', '>=', now()->subHour())
            ->count();

        $expectedReadings = $this->getExpectedReadingCount($authorizedDevices);
        $frequency = $expectedReadings > 0 ? ($recentReadings / $expectedReadings) * 100 : 0;

        // Factor in reading distribution across devices
        $devicesWithRecentData = Reading::whereIn('device_id', $deviceIds)
            ->where('timestamp', '>=', now()->subMinutes(10))
            ->distinct('device_id')
            ->count('device_id');

        $distribution = $authorizedDevices->count() > 0 ? ($devicesWithRecentData / $authorizedDevices->count()) * 100 : 0;

        return ($frequency * 0.6) + ($distribution * 0.4);
    }

    private function getCommunicationErrors($gateway, $authorizedDevices): array
    {
        $errors = [];

        // Check for devices with no recent data
        foreach ($authorizedDevices as $device) {
            $lastReading = Reading::where('device_id', $device->id)
                ->orderBy('timestamp', 'desc')
                ->first();

            if (!$lastReading || $lastReading->timestamp->lt(now()->subMinutes(30))) {
                $errors[] = [
                    'type' => 'communication_timeout',
                    'device_id' => $device->id,
                    'device_name' => $device->name,
                    'description' => 'Device not responding',
                    'last_seen' => $lastReading?->timestamp?->toISOString(),
                    'duration_minutes' => $lastReading ? $lastReading->timestamp->diffInMinutes(now()) : null,
                ];
            }
        }

        return $errors;
    }

    private function determineOverallStatus(float $successRate, float $connectionQuality, ?\Carbon\Carbon $lastPoll): string
    {
        if (!$lastPoll || $lastPoll->lt(now()->subMinutes(15))) {
            return 'offline';
        }

        if ($successRate >= 90 && $connectionQuality >= 80) return 'excellent';
        if ($successRate >= 75 && $connectionQuality >= 60) return 'good';
        if ($successRate >= 50 && $connectionQuality >= 40) return 'fair';
        return 'poor';
    }

    private function calculateDeviceHealthScore($device): float
    {
        $score = 100;

        // Communication health
        $lastReading = Reading::where('device_id', $device->id)
            ->orderBy('timestamp', 'desc')
            ->first();

        if (!$lastReading) {
            return 0;
        }

        $minutesSinceLastReading = $lastReading->timestamp->diffInMinutes(now());
        if ($minutesSinceLastReading > 60) {
            $score -= 50;
        } elseif ($minutesSinceLastReading > 30) {
            $score -= 25;
        } elseif ($minutesSinceLastReading > 10) {
            $score -= 10;
        }

        // Alert health
        $activeAlerts = Alert::where('device_id', $device->id)
            ->where('resolved', false)
            ->get();

        foreach ($activeAlerts as $alert) {
            $score -= match($alert->severity) {
                'critical' => 20,
                'warning' => 10,
                'info' => 5,
                default => 0,
            };
        }

        return max(0, $score);
    }

    private function getDeviceHealthStatus(float $healthScore): string
    {
        if ($healthScore >= 90) return 'healthy';
        if ($healthScore >= 70) return 'warning';
        if ($healthScore >= 30) return 'critical';
        return 'offline';
    }

    private function getLastReadingTime($device): ?string
    {
        $lastReading = Reading::where('device_id', $device->id)
            ->orderBy('timestamp', 'desc')
            ->first();

        return $lastReading?->timestamp?->toISOString();
    }

    private function getActiveAlertCount($device): int
    {
        return Alert::where('device_id', $device->id)
            ->where('resolved', false)
            ->count();
    }

    private function getDeviceDataQuality($device): float
    {
        $recentReadings = Reading::where('device_id', $device->id)
            ->where('timestamp', '>=', now()->subHour())
            ->get();

        if ($recentReadings->isEmpty()) {
            return 0;
        }

        $validReadings = $recentReadings->filter(function ($reading) {
            return $reading->value !== null && $reading->value >= 0;
        })->count();

        return ($validReadings / $recentReadings->count()) * 100;
    }

    private function calculateAverageHealthScore($deviceDetails): float
    {
        if (empty($deviceDetails)) {
            return 0;
        }

        $totalScore = array_sum(array_column($deviceDetails, 'health_score'));
        return $totalScore / count($deviceDetails);
    }

    private function calculateErrorRate($gateway, $authorizedDevices): float
    {
        $deviceIds = $authorizedDevices->pluck('id')->toArray();
        $expectedReadings = $this->getExpectedReadingCount($authorizedDevices);
        $actualReadings = Reading::whereIn('device_id', $deviceIds)
            ->where('timestamp', '>=', now()->subHour())
            ->count();

        $missedReadings = max(0, $expectedReadings - $actualReadings);
        return $expectedReadings > 0 ? ($missedReadings / $expectedReadings) * 100 : 0;
    }

    private function calculateAvailability($gateway): float
    {
        return $this->calculateUptime($gateway);
    }

    private function estimateBandwidthUtilization($readingCount): float
    {
        // Estimate bandwidth usage (assuming ~100 bytes per reading)
        $bytesPerHour = $readingCount * 100;
        $kbpsUsed = ($bytesPerHour * 8) / 3600 / 1000; // Convert to Kbps
        
        // Assume typical Modbus connection has ~10 Kbps capacity
        $totalCapacity = 10;
        
        return min(100, ($kbpsUsed / $totalCapacity) * 100);
    }

    private function calculatePeakReadingRate($deviceIds): float
    {
        $peakRate = 0;
        
        for ($hour = 0; $hour < 24; $hour++) {
            $hourStart = now()->subHours($hour);
            $hourEnd = $hourStart->copy()->addHour();
            
            $hourlyReadings = Reading::whereIn('device_id', $deviceIds)
                ->whereBetween('timestamp', [$hourStart, $hourEnd])
                ->count();
            
            $peakRate = max($peakRate, $hourlyReadings);
        }
        
        return $peakRate;
    }

    private function calculateAverageReadingInterval($deviceIds): float
    {
        $avgInterval = DB::table('readings')
            ->whereIn('device_id', $deviceIds)
            ->where('timestamp', '>=', now()->subDay())
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, LAG(timestamp) OVER (PARTITION BY device_id ORDER BY timestamp), timestamp)) as avg_interval')
            ->value('avg_interval');

        return $avgInterval ?? 0;
    }

    private function calculateOperationalHours($gateway): float
    {
        // Calculate hours with active communication today
        $readingTimes = Reading::whereHas('device', function ($query) use ($gateway) {
            $query->where('gateway_id', $gateway->id);
        })
        ->whereDate('timestamp', today())
        ->selectRaw('HOUR(timestamp) as hour')
        ->distinct()
        ->count();

        return $readingTimes;
    }

    private function calculateDailyAvailability($gateway, $date): float
    {
        $dayStart = $date->copy()->startOfDay();
        $dayEnd = $date->copy()->endOfDay();
        
        $readingCount = Reading::whereHas('device', function ($query) use ($gateway) {
            $query->where('gateway_id', $gateway->id);
        })
        ->whereBetween('timestamp', [$dayStart, $dayEnd])
        ->count();

        // Assume 288 readings per day (every 5 minutes) as 100% availability
        $expectedReadings = 288;
        return min(100, ($readingCount / $expectedReadings) * 100);
    }

    private function calculateDailyErrorRate($gateway, $authorizedDevices, $date): float
    {
        $dayStart = $date->copy()->startOfDay();
        $dayEnd = $date->copy()->endOfDay();
        
        $deviceIds = $authorizedDevices->pluck('id')->toArray();
        $expectedReadings = $authorizedDevices->count() * 288; // 288 readings per device per day
        $actualReadings = Reading::whereIn('device_id', $deviceIds)
            ->whereBetween('timestamp', [$dayStart, $dayEnd])
            ->count();

        $missedReadings = max(0, $expectedReadings - $actualReadings);
        return $expectedReadings > 0 ? ($missedReadings / $expectedReadings) * 100 : 0;
    }

    private function calculatePerformanceTrend($dailyTrends): string
    {
        if (count($dailyTrends) < 2) {
            return 'stable';
        }

        $recent = array_slice($dailyTrends, -3);
        $older = array_slice($dailyTrends, 0, 3);

        $recentAvg = array_sum(array_column($recent, 'reading_count')) / count($recent);
        $olderAvg = array_sum(array_column($older, 'reading_count')) / count($older);

        $change = (($recentAvg - $olderAvg) / $olderAvg) * 100;

        if (abs($change) < 10) return 'stable';
        return $change > 0 ? 'improving' : 'declining';
    }

    private function calculateReliabilityTrend($dailyTrends): string
    {
        if (count($dailyTrends) < 2) {
            return 'stable';
        }

        $recent = array_slice($dailyTrends, -3);
        $older = array_slice($dailyTrends, 0, 3);

        $recentAvg = array_sum(array_column($recent, 'availability')) / count($recent);
        $olderAvg = array_sum(array_column($older, 'availability')) / count($older);

        $change = $recentAvg - $olderAvg;

        if (abs($change) < 5) return 'stable';
        return $change > 0 ? 'improving' : 'declining';
    }

    private function analyzeTrends($dailyTrends): array
    {
        $analysis = [];

        // Analyze reading count trend
        $readingCounts = array_column($dailyTrends, 'reading_count');
        $analysis['reading_trend'] = $this->analyzeTrendArray($readingCounts);

        // Analyze availability trend
        $availabilities = array_column($dailyTrends, 'availability');
        $analysis['availability_trend'] = $this->analyzeTrendArray($availabilities);

        // Analyze error rate trend
        $errorRates = array_column($dailyTrends, 'error_rate');
        $analysis['error_trend'] = $this->analyzeTrendArray($errorRates, true); // Lower is better

        return $analysis;
    }

    private function analyzeTrendArray($values, $lowerIsBetter = false): array
    {
        if (count($values) < 2) {
            return ['direction' => 'stable', 'change_percent' => 0];
        }

        $first = array_sum(array_slice($values, 0, 2)) / 2;
        $last = array_sum(array_slice($values, -2)) / 2;

        $changePercent = $first != 0 ? (($last - $first) / $first) * 100 : 0;

        $direction = 'stable';
        if (abs($changePercent) > 10) {
            if ($lowerIsBetter) {
                $direction = $changePercent > 0 ? 'worsening' : 'improving';
            } else {
                $direction = $changePercent > 0 ? 'improving' : 'declining';
            }
        }

        return [
            'direction' => $direction,
            'change_percent' => round($changePercent, 1),
        ];
    }

    protected function getFallbackData(): array
    {
        return [
            'gateway_info' => [
                'id' => null,
                'name' => 'Unknown Gateway',
                'location' => null,
                'status' => 'unknown',
            ],
            'communication_status' => [
                'overall_status' => 'unknown',
                'connection_quality' => 0,
                'response_time' => 0,
                'success_rate' => 0,
                'last_successful_poll' => null,
                'communication_errors' => [],
            ],
            'device_health_indicators' => [
                'total_devices' => 0,
                'healthy_devices' => 0,
                'warning_devices' => 0,
                'critical_devices' => 0,
                'offline_devices' => 0,
                'health_distribution' => [],
                'device_details' => [],
            ],
            'performance_metrics' => [
                'data_throughput' => 0,
                'polling_efficiency' => 0,
                'error_rate' => 0,
                'availability' => 0,
                'bandwidth_utilization' => 0,
            ],
            'operational_statistics' => [
                'total_readings_today' => 0,
                'peak_reading_rate' => 0,
                'average_reading_interval' => 0,
                'data_volume_mb' => 0,
                'operational_hours' => 0,
            ],
            'historical_trends' => [
                'daily_trends' => [],
                'performance_trend' => 'stable',
                'reliability_trend' => 'stable',
            ],
        ];
    }
}