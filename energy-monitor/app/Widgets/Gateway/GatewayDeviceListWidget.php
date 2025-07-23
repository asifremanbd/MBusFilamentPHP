<?php

namespace App\Widgets\Gateway;

use App\Widgets\BaseWidget;
use App\Models\Device;
use App\Models\Reading;
use App\Models\Alert;
use App\Models\Register;
use Illuminate\Support\Facades\DB;

class GatewayDeviceListWidget extends BaseWidget
{
    protected function getData(): array
    {
        $authorizedDevices = $this->permissionService->getAuthorizedDevices($this->user, $this->gatewayId);

        return [
            'devices' => $this->getDeviceList($authorizedDevices),
            'device_summary' => $this->getDeviceSummary($authorizedDevices),
            'device_types' => $this->getDeviceTypes($authorizedDevices),
            'connectivity_status' => $this->getConnectivityStatus($authorizedDevices),
            'performance_metrics' => $this->getPerformanceMetrics($authorizedDevices),
        ];
    }

    protected function getWidgetType(): string
    {
        return 'gateway-device-list';
    }

    protected function getWidgetName(): string
    {
        return 'Gateway Device List';
    }

    protected function getWidgetDescription(): string
    {
        return 'List of devices in this gateway with status and metrics';
    }

    protected function getWidgetCategory(): string
    {
        return 'devices';
    }

    protected function getWidgetPriority(): int
    {
        return 10;
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
        return 30; // Update every 30 seconds
    }

    /**
     * Get detailed device list with status and metrics
     */
    private function getDeviceList($authorizedDevices): array
    {
        if ($authorizedDevices->isEmpty()) {
            return [];
        }

        $devices = [];

        foreach ($authorizedDevices as $device) {
            $lastReading = $this->getLastReading($device);
            $deviceStatus = $this->getDeviceStatus($device);
            $alertCount = $this->getActiveAlertCount($device);
            $registers = $device->registers()->count();

            $devices[] = [
                'id' => $device->id,
                'name' => $device->name,
                'slave_id' => $device->slave_id,
                'location_tag' => $device->location_tag,
                'device_type' => $this->determineDeviceType($device),
                'status' => $deviceStatus,
                'last_reading' => $lastReading ? [
                    'timestamp' => $lastReading->timestamp->toISOString(),
                    'value' => $lastReading->value,
                    'parameter' => $lastReading->register->parameter_name ?? 'Unknown',
                    'unit' => $lastReading->register->unit ?? '',
                    'age_minutes' => $lastReading->timestamp->diffInMinutes(now()),
                ] : null,
                'alert_count' => [
                    'critical' => $alertCount['critical'],
                    'warning' => $alertCount['warning'],
                    'info' => $alertCount['info'],
                    'total' => $alertCount['total'],
                ],
                'register_count' => $registers,
                'health_score' => $this->calculateDeviceHealthScore($device),
                'communication_quality' => $this->calculateCommunicationQuality($device),
                'data_points_today' => $this->getDataPointsToday($device),
                'last_maintenance' => $this->getLastMaintenanceDate($device),
                'next_maintenance' => $this->getNextMaintenanceDate($device),
            ];
        }

        // Sort devices by status priority (critical alerts first, then by name)
        usort($devices, function ($a, $b) {
            if ($a['alert_count']['critical'] !== $b['alert_count']['critical']) {
                return $b['alert_count']['critical'] <=> $a['alert_count']['critical'];
            }
            if ($a['status'] !== $b['status']) {
                $statusPriority = ['offline' => 0, 'error' => 1, 'warning' => 2, 'online' => 3];
                return ($statusPriority[$a['status']] ?? 4) <=> ($statusPriority[$b['status']] ?? 4);
            }
            return strcmp($a['name'], $b['name']);
        });

        return $devices;
    }

    /**
     * Get device summary statistics
     */
    private function getDeviceSummary($authorizedDevices): array
    {
        if ($authorizedDevices->isEmpty()) {
            return [
                'total_devices' => 0,
                'online_devices' => 0,
                'offline_devices' => 0,
                'devices_with_alerts' => 0,
                'average_health_score' => 0,
            ];
        }

        $totalDevices = $authorizedDevices->count();
        $onlineDevices = 0;
        $devicesWithAlerts = 0;
        $totalHealthScore = 0;

        foreach ($authorizedDevices as $device) {
            $status = $this->getDeviceStatus($device);
            if ($status === 'online') {
                $onlineDevices++;
            }

            $alertCount = $this->getActiveAlertCount($device);
            if ($alertCount['total'] > 0) {
                $devicesWithAlerts++;
            }

            $totalHealthScore += $this->calculateDeviceHealthScore($device);
        }

        return [
            'total_devices' => $totalDevices,
            'online_devices' => $onlineDevices,
            'offline_devices' => $totalDevices - $onlineDevices,
            'devices_with_alerts' => $devicesWithAlerts,
            'average_health_score' => $totalDevices > 0 ? round($totalHealthScore / $totalDevices, 1) : 0,
        ];
    }

    /**
     * Get device types distribution
     */
    private function getDeviceTypes($authorizedDevices): array
    {
        if ($authorizedDevices->isEmpty()) {
            return [];
        }

        $deviceTypes = [];

        foreach ($authorizedDevices as $device) {
            $type = $this->determineDeviceType($device);
            
            if (!isset($deviceTypes[$type])) {
                $deviceTypes[$type] = [
                    'type' => $type,
                    'count' => 0,
                    'online_count' => 0,
                    'devices' => [],
                ];
            }

            $deviceTypes[$type]['count']++;
            
            if ($this->getDeviceStatus($device) === 'online') {
                $deviceTypes[$type]['online_count']++;
            }

            $deviceTypes[$type]['devices'][] = [
                'id' => $device->id,
                'name' => $device->name,
                'status' => $this->getDeviceStatus($device),
            ];
        }

        return array_values($deviceTypes);
    }

    /**
     * Get connectivity status overview
     */
    private function getConnectivityStatus($authorizedDevices): array
    {
        if ($authorizedDevices->isEmpty()) {
            return [
                'overall_status' => 'unknown',
                'connectivity_percentage' => 0,
                'last_communication' => null,
                'communication_issues' => [],
            ];
        }

        $totalDevices = $authorizedDevices->count();
        $connectedDevices = 0;
        $lastCommunication = null;
        $communicationIssues = [];

        foreach ($authorizedDevices as $device) {
            $lastReading = $this->getLastReading($device);
            
            if ($lastReading) {
                if ($lastReading->timestamp->gt(now()->subMinutes(10))) {
                    $connectedDevices++;
                }

                if (!$lastCommunication || $lastReading->timestamp->gt($lastCommunication)) {
                    $lastCommunication = $lastReading->timestamp;
                }

                // Check for communication issues
                if ($lastReading->timestamp->lt(now()->subMinutes(30))) {
                    $communicationIssues[] = [
                        'device_id' => $device->id,
                        'device_name' => $device->name,
                        'issue' => 'No recent data',
                        'last_seen' => $lastReading->timestamp->toISOString(),
                        'minutes_ago' => $lastReading->timestamp->diffInMinutes(now()),
                    ];
                }
            } else {
                $communicationIssues[] = [
                    'device_id' => $device->id,
                    'device_name' => $device->name,
                    'issue' => 'No data available',
                    'last_seen' => null,
                    'minutes_ago' => null,
                ];
            }
        }

        $connectivityPercentage = $totalDevices > 0 ? ($connectedDevices / $totalDevices) * 100 : 0;
        
        $overallStatus = 'good';
        if ($connectivityPercentage < 50) {
            $overallStatus = 'critical';
        } elseif ($connectivityPercentage < 80) {
            $overallStatus = 'warning';
        }

        return [
            'overall_status' => $overallStatus,
            'connectivity_percentage' => round($connectivityPercentage, 1),
            'connected_devices' => $connectedDevices,
            'total_devices' => $totalDevices,
            'last_communication' => $lastCommunication?->toISOString(),
            'communication_issues' => $communicationIssues,
        ];
    }

    /**
     * Get performance metrics for devices
     */
    private function getPerformanceMetrics($authorizedDevices): array
    {
        if ($authorizedDevices->isEmpty()) {
            return [
                'data_throughput' => 0,
                'average_response_time' => 0,
                'error_rate' => 0,
                'uptime_percentage' => 0,
            ];
        }

        $deviceIds = $authorizedDevices->pluck('id')->toArray();

        // Data throughput (readings per hour)
        $recentReadings = Reading::whereIn('device_id', $deviceIds)
            ->where('timestamp', '>=', now()->subHour())
            ->count();

        // Calculate average response time between readings
        $avgResponseTime = $this->calculateAverageResponseTime($deviceIds);

        // Error rate (devices with communication issues)
        $devicesWithIssues = 0;
        foreach ($authorizedDevices as $device) {
            $lastReading = $this->getLastReading($device);
            if (!$lastReading || $lastReading->timestamp->lt(now()->subMinutes(30))) {
                $devicesWithIssues++;
            }
        }
        $errorRate = $authorizedDevices->count() > 0 ? ($devicesWithIssues / $authorizedDevices->count()) * 100 : 0;

        // Uptime percentage (devices that have communicated in last 24 hours)
        $devicesWithRecentData = Reading::whereIn('device_id', $deviceIds)
            ->where('timestamp', '>=', now()->subDay())
            ->distinct('device_id')
            ->count('device_id');
        $uptimePercentage = $authorizedDevices->count() > 0 ? ($devicesWithRecentData / $authorizedDevices->count()) * 100 : 0;

        return [
            'data_throughput' => $recentReadings,
            'average_response_time' => round($avgResponseTime, 2),
            'error_rate' => round($errorRate, 1),
            'uptime_percentage' => round($uptimePercentage, 1),
        ];
    }

    /**
     * Helper methods
     */
    private function getLastReading($device)
    {
        return Reading::where('device_id', $device->id)
            ->with('register')
            ->orderBy('timestamp', 'desc')
            ->first();
    }

    private function getDeviceStatus($device): string
    {
        $lastReading = $this->getLastReading($device);
        
        if (!$lastReading) {
            return 'offline';
        }

        $minutesSinceLastReading = $lastReading->timestamp->diffInMinutes(now());

        if ($minutesSinceLastReading <= 5) {
            return 'online';
        } elseif ($minutesSinceLastReading <= 30) {
            return 'warning';
        } else {
            return 'offline';
        }
    }

    private function getActiveAlertCount($device): array
    {
        $alerts = Alert::where('device_id', $device->id)
            ->where('resolved', false)
            ->selectRaw('severity, COUNT(*) as count')
            ->groupBy('severity')
            ->pluck('count', 'severity')
            ->toArray();

        return [
            'critical' => $alerts['critical'] ?? 0,
            'warning' => $alerts['warning'] ?? 0,
            'info' => $alerts['info'] ?? 0,
            'total' => array_sum($alerts),
        ];
    }

    private function determineDeviceType($device): string
    {
        $name = strtolower($device->name);
        
        if (strpos($name, 'energy') !== false || strpos($name, 'meter') !== false) {
            return 'Energy Meter';
        }
        if (strpos($name, 'water') !== false) {
            return 'Water Meter';
        }
        if (strpos($name, 'ac') !== false || strpos($name, 'air') !== false || strpos($name, 'hvac') !== false) {
            return 'HVAC';
        }
        if (strpos($name, 'heater') !== false || strpos($name, 'heating') !== false) {
            return 'Heating';
        }
        if (strpos($name, 'sensor') !== false) {
            return 'Sensor';
        }
        
        return 'Other';
    }

    private function calculateDeviceHealthScore($device): float
    {
        $score = 100;

        // Deduct points for communication issues
        $lastReading = $this->getLastReading($device);
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

        // Deduct points for active alerts
        $alertCount = $this->getActiveAlertCount($device);
        $score -= $alertCount['critical'] * 20;
        $score -= $alertCount['warning'] * 10;
        $score -= $alertCount['info'] * 5;

        // Deduct points for data quality issues
        $dataQuality = $this->assessDataQuality($device);
        $score -= (100 - $dataQuality) * 0.2;

        return max(0, round($score, 1));
    }

    private function calculateCommunicationQuality($device): string
    {
        $lastReading = $this->getLastReading($device);
        
        if (!$lastReading) {
            return 'no_data';
        }

        // Check consistency of readings over the last hour
        $recentReadings = Reading::where('device_id', $device->id)
            ->where('timestamp', '>=', now()->subHour())
            ->count();

        if ($recentReadings >= 10) {
            return 'excellent';
        } elseif ($recentReadings >= 5) {
            return 'good';
        } elseif ($recentReadings >= 2) {
            return 'fair';
        } else {
            return 'poor';
        }
    }

    private function getDataPointsToday($device): int
    {
        return Reading::where('device_id', $device->id)
            ->whereDate('timestamp', today())
            ->count();
    }

    private function getLastMaintenanceDate($device): ?string
    {
        // This would typically come from a maintenance log table
        // For now, return null as placeholder
        return null;
    }

    private function getNextMaintenanceDate($device): ?string
    {
        // This would typically be calculated based on maintenance schedule
        // For now, return null as placeholder
        return null;
    }

    private function calculateAverageResponseTime($deviceIds): float
    {
        // Calculate average time between consecutive readings
        $avgTime = DB::table('readings')
            ->whereIn('device_id', $deviceIds)
            ->where('timestamp', '>=', now()->subDay())
            ->selectRaw('device_id, AVG(TIMESTAMPDIFF(SECOND, LAG(timestamp) OVER (PARTITION BY device_id ORDER BY timestamp), timestamp)) as avg_interval')
            ->groupBy('device_id')
            ->get()
            ->avg('avg_interval');

        return $avgTime ? $avgTime / 60 : 0; // Convert to minutes
    }

    private function assessDataQuality($device): float
    {
        // Assess data quality based on reading consistency and validity
        $recentReadings = Reading::where('device_id', $device->id)
            ->where('timestamp', '>=', now()->subHour())
            ->get();

        if ($recentReadings->isEmpty()) {
            return 0;
        }

        $qualityScore = 100;

        // Check for null or invalid values
        $invalidReadings = $recentReadings->filter(function ($reading) {
            return $reading->value === null || $reading->value < 0;
        })->count();

        if ($invalidReadings > 0) {
            $qualityScore -= ($invalidReadings / $recentReadings->count()) * 50;
        }

        // Check for data consistency (no extreme outliers)
        $values = $recentReadings->pluck('value')->filter()->toArray();
        if (count($values) > 2) {
            $mean = array_sum($values) / count($values);
            $outliers = array_filter($values, function ($value) use ($mean) {
                return abs($value - $mean) > ($mean * 2); // Values more than 200% of mean
            });

            if (count($outliers) > 0) {
                $qualityScore -= (count($outliers) / count($values)) * 30;
            }
        }

        return max(0, $qualityScore);
    }

    protected function getFallbackData(): array
    {
        return [
            'devices' => [],
            'device_summary' => [
                'total_devices' => 0,
                'online_devices' => 0,
                'offline_devices' => 0,
                'devices_with_alerts' => 0,
                'average_health_score' => 0,
            ],
            'device_types' => [],
            'connectivity_status' => [
                'overall_status' => 'unknown',
                'connectivity_percentage' => 0,
                'connected_devices' => 0,
                'total_devices' => 0,
                'last_communication' => null,
                'communication_issues' => [],
            ],
            'performance_metrics' => [
                'data_throughput' => 0,
                'average_response_time' => 0,
                'error_rate' => 0,
                'uptime_percentage' => 0,
            ],
        ];
    }
}