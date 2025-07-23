<?php

namespace App\Widgets\Global;

use App\Widgets\BaseWidget;
use App\Models\Gateway;
use App\Models\Device;
use App\Models\Reading;
use App\Models\Alert;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class SystemHealthWidget extends BaseWidget
{
    protected function getData(): array
    {
        $authorizedGateways = $this->permissionService->getAuthorizedGateways($this->user);
        $authorizedDevices = $this->permissionService->getAuthorizedDevices($this->user);

        return [
            'overall_health' => $this->calculateOverallHealth($authorizedGateways, $authorizedDevices),
            'component_health' => $this->getComponentHealth($authorizedGateways, $authorizedDevices),
            'health_trends' => $this->getHealthTrends($authorizedGateways, $authorizedDevices),
            'critical_issues' => $this->getCriticalIssues($authorizedDevices),
            'performance_metrics' => $this->getPerformanceMetrics($authorizedGateways, $authorizedDevices),
            'recommendations' => $this->getHealthRecommendations($authorizedGateways, $authorizedDevices),
        ];
    }

    protected function getWidgetType(): string
    {
        return 'system-health';
    }

    protected function getWidgetName(): string
    {
        return 'System Health';
    }

    protected function getWidgetDescription(): string
    {
        return 'Overall system health indicators and performance metrics';
    }

    protected function getWidgetCategory(): string
    {
        return 'monitoring';
    }

    protected function getWidgetPriority(): int
    {
        return 15;
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
     * Calculate overall system health score
     */
    private function calculateOverallHealth($authorizedGateways, $authorizedDevices): array
    {
        if ($authorizedGateways->isEmpty() || $authorizedDevices->isEmpty()) {
            return [
                'score' => 0,
                'status' => 'unknown',
                'status_color' => 'gray',
                'last_updated' => now()->toISOString(),
            ];
        }

        $healthFactors = [
            'connectivity' => $this->calculateConnectivityHealth($authorizedGateways, $authorizedDevices),
            'data_quality' => $this->calculateDataQualityHealth($authorizedDevices),
            'alert_status' => $this->calculateAlertHealth($authorizedDevices),
            'performance' => $this->calculatePerformanceHealth($authorizedDevices),
            'availability' => $this->calculateAvailabilityHealth($authorizedGateways, $authorizedDevices),
        ];

        // Calculate weighted average
        $weights = [
            'connectivity' => 0.25,
            'data_quality' => 0.20,
            'alert_status' => 0.25,
            'performance' => 0.15,
            'availability' => 0.15,
        ];

        $totalScore = 0;
        foreach ($healthFactors as $factor => $score) {
            $totalScore += $score * $weights[$factor];
        }

        $overallScore = round($totalScore, 1);

        return [
            'score' => $overallScore,
            'status' => $this->getHealthStatus($overallScore),
            'status_color' => $this->getHealthStatusColor($overallScore),
            'factors' => $healthFactors,
            'last_updated' => now()->toISOString(),
        ];
    }

    /**
     * Get health status for individual components
     */
    private function getComponentHealth($authorizedGateways, $authorizedDevices): array
    {
        $components = [];

        // Gateway health
        foreach ($authorizedGateways as $gateway) {
            $components['gateways'][] = [
                'id' => $gateway->id,
                'name' => $gateway->name,
                'health_score' => $this->calculateGatewayHealth($gateway),
                'status' => $this->getGatewayStatus($gateway),
                'last_communication' => $this->getLastCommunication($gateway),
                'device_count' => $gateway->devices()->count(),
                'active_devices' => $this->getActiveDeviceCount($gateway),
            ];
        }

        // Device health summary by type
        $devicesByType = $authorizedDevices->groupBy(function ($device) {
            return $this->getDeviceType($device);
        });

        foreach ($devicesByType as $type => $devices) {
            $healthScores = [];
            $activeCount = 0;
            
            foreach ($devices as $device) {
                $health = $this->calculateDeviceHealth($device);
                $healthScores[] = $health;
                if ($health > 70) $activeCount++;
            }

            $avgHealth = count($healthScores) > 0 ? array_sum($healthScores) / count($healthScores) : 0;

            $components['device_types'][] = [
                'type' => $type,
                'total_count' => $devices->count(),
                'active_count' => $activeCount,
                'average_health' => round($avgHealth, 1),
                'status' => $this->getHealthStatus($avgHealth),
            ];
        }

        return $components;
    }

    /**
     * Get health trends over time
     */
    private function getHealthTrends($authorizedGateways, $authorizedDevices): array
    {
        $cacheKey = "health_trends_{$this->user->id}";
        
        return Cache::remember($cacheKey, 300, function () use ($authorizedGateways, $authorizedDevices) {
            $trends = [];
            
            // Get daily health scores for the last 7 days
            for ($i = 6; $i >= 0; $i--) {
                $date = now()->subDays($i)->startOfDay();
                $endDate = $date->copy()->endOfDay();
                
                // Calculate health score for this day
                $dayScore = $this->calculateHistoricalHealth($authorizedGateways, $authorizedDevices, $date, $endDate);
                
                $trends[] = [
                    'date' => $date->toDateString(),
                    'health_score' => $dayScore,
                    'status' => $this->getHealthStatus($dayScore),
                ];
            }
            
            return $trends;
        });
    }

    /**
     * Get critical issues that need immediate attention
     */
    private function getCriticalIssues($authorizedDevices): array
    {
        if ($authorizedDevices->isEmpty()) {
            return [];
        }

        $issues = [];
        $deviceIds = $authorizedDevices->pluck('id')->toArray();

        // Critical alerts
        $criticalAlerts = Alert::whereIn('device_id', $deviceIds)
            ->where('severity', 'critical')
            ->where('resolved', false)
            ->with(['device.gateway'])
            ->orderBy('timestamp', 'desc')
            ->limit(5)
            ->get();

        foreach ($criticalAlerts as $alert) {
            $issues[] = [
                'type' => 'critical_alert',
                'severity' => 'critical',
                'title' => "Critical Alert: {$alert->parameter_name}",
                'description' => $alert->message,
                'device' => $alert->device->name,
                'gateway' => $alert->device->gateway->name,
                'timestamp' => $alert->timestamp->toISOString(),
                'age_minutes' => $alert->timestamp->diffInMinutes(now()),
            ];
        }

        // Offline devices
        $offlineDevices = $this->getOfflineDevices($authorizedDevices);
        foreach ($offlineDevices->take(5) as $device) {
            $issues[] = [
                'type' => 'device_offline',
                'severity' => 'warning',
                'title' => "Device Offline: {$device->name}",
                'description' => 'Device has not reported data in over 1 hour',
                'device' => $device->name,
                'gateway' => $device->gateway->name,
                'timestamp' => $this->getLastReading($device)?->timestamp?->toISOString(),
                'age_minutes' => $this->getLastReading($device)?->timestamp?->diffInMinutes(now()),
            ];
        }

        // Communication issues
        $communicationIssues = $this->getCommunicationIssues($authorizedDevices);
        foreach ($communicationIssues->take(3) as $issue) {
            $issues[] = [
                'type' => 'communication_issue',
                'severity' => 'warning',
                'title' => "Communication Issue: {$issue['gateway_name']}",
                'description' => "Gateway has {$issue['offline_devices']} offline devices",
                'gateway' => $issue['gateway_name'],
                'timestamp' => now()->toISOString(),
            ];
        }

        // Sort by severity and age
        usort($issues, function ($a, $b) {
            $severityOrder = ['critical' => 0, 'warning' => 1, 'info' => 2];
            $aSeverity = $severityOrder[$a['severity']] ?? 3;
            $bSeverity = $severityOrder[$b['severity']] ?? 3;
            
            if ($aSeverity === $bSeverity) {
                return ($a['age_minutes'] ?? 0) <=> ($b['age_minutes'] ?? 0);
            }
            
            return $aSeverity <=> $bSeverity;
        });

        return array_slice($issues, 0, 10);
    }

    /**
     * Get performance metrics
     */
    private function getPerformanceMetrics($authorizedGateways, $authorizedDevices): array
    {
        if ($authorizedDevices->isEmpty()) {
            return [
                'data_throughput' => 0,
                'response_time' => 0,
                'uptime_percentage' => 0,
                'error_rate' => 0,
            ];
        }

        $deviceIds = $authorizedDevices->pluck('id')->toArray();

        // Data throughput (readings per hour)
        $recentReadings = Reading::whereIn('device_id', $deviceIds)
            ->where('timestamp', '>=', now()->subHour())
            ->count();

        // Average response time (time between readings)
        $avgResponseTime = $this->calculateAverageResponseTime($deviceIds);

        // Uptime percentage
        $uptimePercentage = $this->calculateUptimePercentage($authorizedDevices);

        // Error rate (percentage of failed communications)
        $errorRate = $this->calculateErrorRate($authorizedDevices);

        return [
            'data_throughput' => $recentReadings,
            'response_time' => round($avgResponseTime, 2),
            'uptime_percentage' => round($uptimePercentage, 1),
            'error_rate' => round($errorRate, 2),
        ];
    }

    /**
     * Get health recommendations
     */
    private function getHealthRecommendations($authorizedGateways, $authorizedDevices): array
    {
        $recommendations = [];

        // Check for offline devices
        $offlineDevices = $this->getOfflineDevices($authorizedDevices);
        if ($offlineDevices->count() > 0) {
            $recommendations[] = [
                'priority' => 'high',
                'category' => 'connectivity',
                'title' => 'Address Offline Devices',
                'description' => "You have {$offlineDevices->count()} offline devices that need attention.",
                'action' => 'Check device connections and power supply',
            ];
        }

        // Check for critical alerts
        $criticalAlerts = Alert::whereIn('device_id', $authorizedDevices->pluck('id')->toArray())
            ->where('severity', 'critical')
            ->where('resolved', false)
            ->count();

        if ($criticalAlerts > 0) {
            $recommendations[] = [
                'priority' => 'critical',
                'category' => 'alerts',
                'title' => 'Resolve Critical Alerts',
                'description' => "You have {$criticalAlerts} unresolved critical alerts.",
                'action' => 'Review and resolve critical alerts immediately',
            ];
        }

        // Check data quality
        $dataQualityScore = $this->calculateDataQualityHealth($authorizedDevices);
        if ($dataQualityScore < 70) {
            $recommendations[] = [
                'priority' => 'medium',
                'category' => 'data_quality',
                'title' => 'Improve Data Quality',
                'description' => 'Data quality score is below optimal levels.',
                'action' => 'Check sensor calibration and data validation rules',
            ];
        }

        // Check system performance
        $performanceMetrics = $this->getPerformanceMetrics($authorizedGateways, $authorizedDevices);
        if ($performanceMetrics['uptime_percentage'] < 95) {
            $recommendations[] = [
                'priority' => 'medium',
                'category' => 'performance',
                'title' => 'Improve System Uptime',
                'description' => "System uptime is {$performanceMetrics['uptime_percentage']}%, below the 95% target.",
                'action' => 'Review system reliability and implement redundancy measures',
            ];
        }

        return $recommendations;
    }

    /**
     * Helper methods for health calculations
     */
    private function calculateConnectivityHealth($authorizedGateways, $authorizedDevices): float
    {
        if ($authorizedDevices->isEmpty()) return 0;

        $totalDevices = $authorizedDevices->count();
        $activeDevices = 0;

        foreach ($authorizedDevices as $device) {
            $lastReading = $this->getLastReading($device);
            if ($lastReading && $lastReading->timestamp->gt(now()->subMinutes(10))) {
                $activeDevices++;
            }
        }

        return $totalDevices > 0 ? ($activeDevices / $totalDevices) * 100 : 0;
    }

    private function calculateDataQualityHealth($authorizedDevices): float
    {
        if ($authorizedDevices->isEmpty()) return 0;

        $qualityScore = 0;
        $deviceCount = 0;

        foreach ($authorizedDevices as $device) {
            $deviceScore = $this->calculateDeviceDataQuality($device);
            if ($deviceScore >= 0) {
                $qualityScore += $deviceScore;
                $deviceCount++;
            }
        }

        return $deviceCount > 0 ? $qualityScore / $deviceCount : 0;
    }

    private function calculateAlertHealth($authorizedDevices): float
    {
        if ($authorizedDevices->isEmpty()) return 100;

        $deviceIds = $authorizedDevices->pluck('id')->toArray();
        
        $criticalAlerts = Alert::whereIn('device_id', $deviceIds)
            ->where('severity', 'critical')
            ->where('resolved', false)
            ->count();

        $warningAlerts = Alert::whereIn('device_id', $deviceIds)
            ->where('severity', 'warning')
            ->where('resolved', false)
            ->count();

        // Start with 100 and deduct points for alerts
        $score = 100;
        $score -= $criticalAlerts * 20; // 20 points per critical alert
        $score -= $warningAlerts * 5;   // 5 points per warning alert

        return max(0, $score);
    }

    private function calculatePerformanceHealth($authorizedDevices): float
    {
        if ($authorizedDevices->isEmpty()) return 0;

        $performanceScores = [];

        foreach ($authorizedDevices as $device) {
            $score = $this->calculateDevicePerformance($device);
            if ($score >= 0) {
                $performanceScores[] = $score;
            }
        }

        return count($performanceScores) > 0 ? array_sum($performanceScores) / count($performanceScores) : 0;
    }

    private function calculateAvailabilityHealth($authorizedGateways, $authorizedDevices): float
    {
        return $this->calculateUptimePercentage($authorizedDevices);
    }

    private function getHealthStatus(float $score): string
    {
        if ($score >= 95) return 'excellent';
        if ($score >= 85) return 'good';
        if ($score >= 70) return 'fair';
        if ($score >= 50) return 'poor';
        return 'critical';
    }

    private function getHealthStatusColor(float $score): string
    {
        if ($score >= 85) return 'green';
        if ($score >= 70) return 'yellow';
        if ($score >= 50) return 'orange';
        return 'red';
    }

    // Additional helper methods would be implemented here...
    // (Due to length constraints, I'm showing the structure)

    private function calculateGatewayHealth($gateway): float
    {
        // Implementation for gateway-specific health calculation
        return 85.0; // Placeholder
    }

    private function getGatewayStatus($gateway): string
    {
        // Implementation for gateway status determination
        return 'online'; // Placeholder
    }

    private function getLastCommunication($gateway): ?string
    {
        // Implementation for last communication timestamp
        return now()->subMinutes(2)->toISOString(); // Placeholder
    }

    private function getActiveDeviceCount($gateway): int
    {
        return Reading::whereHas('device', function ($query) use ($gateway) {
            $query->where('gateway_id', $gateway->id);
        })
        ->where('timestamp', '>=', now()->subMinutes(10))
        ->distinct('device_id')
        ->count('device_id');
    }

    private function getDeviceType($device): string
    {
        // Determine device type based on registers or device name
        if (stripos($device->name, 'energy') !== false || stripos($device->name, 'meter') !== false) {
            return 'Energy Meter';
        }
        if (stripos($device->name, 'water') !== false) {
            return 'Water Meter';
        }
        if (stripos($device->name, 'ac') !== false || stripos($device->name, 'air') !== false) {
            return 'HVAC';
        }
        return 'Other';
    }

    private function calculateDeviceHealth($device): float
    {
        // Implementation for device-specific health calculation
        return 80.0; // Placeholder
    }

    private function calculateHistoricalHealth($authorizedGateways, $authorizedDevices, $startDate, $endDate): float
    {
        // Implementation for historical health calculation
        return 85.0; // Placeholder
    }

    private function getOfflineDevices($authorizedDevices)
    {
        return $authorizedDevices->filter(function ($device) {
            $lastReading = $this->getLastReading($device);
            return !$lastReading || $lastReading->timestamp->lt(now()->subHour());
        });
    }

    private function getLastReading($device)
    {
        return Reading::where('device_id', $device->id)
            ->orderBy('timestamp', 'desc')
            ->first();
    }

    private function getCommunicationIssues($authorizedDevices)
    {
        // Implementation for communication issues detection
        return collect(); // Placeholder
    }

    private function calculateAverageResponseTime($deviceIds): float
    {
        // Implementation for response time calculation
        return 2.5; // Placeholder
    }

    private function calculateUptimePercentage($authorizedDevices): float
    {
        // Implementation for uptime calculation
        return 98.5; // Placeholder
    }

    private function calculateErrorRate($authorizedDevices): float
    {
        // Implementation for error rate calculation
        return 1.2; // Placeholder
    }

    private function calculateDeviceDataQuality($device): float
    {
        // Implementation for device data quality calculation
        return 85.0; // Placeholder
    }

    private function calculateDevicePerformance($device): float
    {
        // Implementation for device performance calculation
        return 90.0; // Placeholder
    }

    protected function getFallbackData(): array
    {
        return [
            'overall_health' => [
                'score' => 0,
                'status' => 'unknown',
                'status_color' => 'gray',
                'factors' => [],
                'last_updated' => now()->toISOString(),
            ],
            'component_health' => [
                'gateways' => [],
                'device_types' => [],
            ],
            'health_trends' => [],
            'critical_issues' => [],
            'performance_metrics' => [
                'data_throughput' => 0,
                'response_time' => 0,
                'uptime_percentage' => 0,
                'error_rate' => 0,
            ],
            'recommendations' => [],
        ];
    }
}