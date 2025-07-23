<?php

namespace App\Widgets\Gateway;

use App\Widgets\BaseWidget;
use App\Models\Alert;
use App\Models\Device;
use Illuminate\Support\Facades\DB;

class GatewayAlertsWidget extends BaseWidget
{
    protected function getData(): array
    {
        $authorizedDevices = $this->permissionService->getAuthorizedDevices($this->user, $this->gatewayId);

        return [
            'active_alerts' => $this->getActiveAlerts($authorizedDevices),
            'alert_summary' => $this->getAlertSummary($authorizedDevices),
            'recent_alerts' => $this->getRecentAlerts($authorizedDevices),
            'alert_trends' => $this->getAlertTrends($authorizedDevices),
            'device_alert_status' => $this->getDeviceAlertStatus($authorizedDevices),
            'alert_statistics' => $this->getAlertStatistics($authorizedDevices),
        ];
    }

    protected function getWidgetType(): string
    {
        return 'gateway-alerts';
    }

    protected function getWidgetName(): string
    {
        return 'Gateway Alerts';
    }

    protected function getWidgetDescription(): string
    {
        return 'Gateway-specific alert management and monitoring';
    }

    protected function getWidgetCategory(): string
    {
        return 'alerts';
    }

    protected function getWidgetPriority(): int
    {
        return 25;
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
     * Get active alerts for this gateway
     */
    private function getActiveAlerts($authorizedDevices): array
    {
        if ($authorizedDevices->isEmpty()) {
            return [];
        }

        $deviceIds = $authorizedDevices->pluck('id')->toArray();

        $alerts = Alert::whereIn('device_id', $deviceIds)
            ->where('resolved', false)
            ->with(['device'])
            ->orderByRaw("FIELD(severity, 'critical', 'warning', 'info')")
            ->orderBy('timestamp', 'desc')
            ->get()
            ->map(function ($alert) {
                return [
                    'id' => $alert->id,
                    'device_id' => $alert->device_id,
                    'device_name' => $alert->device->name,
                    'slave_id' => $alert->device->slave_id,
                    'parameter_name' => $alert->parameter_name,
                    'value' => $alert->value,
                    'threshold' => $alert->threshold,
                    'severity' => $alert->severity,
                    'message' => $alert->message,
                    'timestamp' => $alert->timestamp->toISOString(),
                    'age_minutes' => $alert->timestamp->diffInMinutes(now()),
                    'age_formatted' => $this->formatAge($alert->timestamp),
                    'priority_score' => $this->calculateAlertPriority($alert),
                    'escalation_level' => $this->getEscalationLevel($alert),
                    'recommended_action' => $this->getRecommendedAction($alert),
                ];
            })
            ->toArray();

        return $alerts;
    }

    /**
     * Get alert summary statistics
     */
    private function getAlertSummary($authorizedDevices): array
    {
        if ($authorizedDevices->isEmpty()) {
            return [
                'total_active' => 0,
                'critical_count' => 0,
                'warning_count' => 0,
                'info_count' => 0,
                'devices_with_alerts' => 0,
                'most_critical_device' => null,
            ];
        }

        $deviceIds = $authorizedDevices->pluck('id')->toArray();

        // Get alert counts by severity
        $alertCounts = Alert::whereIn('device_id', $deviceIds)
            ->where('resolved', false)
            ->selectRaw('severity, COUNT(*) as count')
            ->groupBy('severity')
            ->pluck('count', 'severity')
            ->toArray();

        $criticalCount = $alertCounts['critical'] ?? 0;
        $warningCount = $alertCounts['warning'] ?? 0;
        $infoCount = $alertCounts['info'] ?? 0;
        $totalActive = $criticalCount + $warningCount + $infoCount;

        // Count devices with alerts
        $devicesWithAlerts = Alert::whereIn('device_id', $deviceIds)
            ->where('resolved', false)
            ->distinct('device_id')
            ->count('device_id');

        // Find most critical device
        $mostCriticalDevice = $this->getMostCriticalDevice($deviceIds);

        return [
            'total_active' => $totalActive,
            'critical_count' => $criticalCount,
            'warning_count' => $warningCount,
            'info_count' => $infoCount,
            'devices_with_alerts' => $devicesWithAlerts,
            'total_devices' => $authorizedDevices->count(),
            'alert_percentage' => $authorizedDevices->count() > 0 ? round(($devicesWithAlerts / $authorizedDevices->count()) * 100, 1) : 0,
            'most_critical_device' => $mostCriticalDevice,
        ];
    }

    /**
     * Get recent alerts (including resolved ones)
     */
    private function getRecentAlerts($authorizedDevices): array
    {
        if ($authorizedDevices->isEmpty()) {
            return [];
        }

        $deviceIds = $authorizedDevices->pluck('id')->toArray();

        $alerts = Alert::whereIn('device_id', $deviceIds)
            ->where('timestamp', '>=', now()->subDays(7))
            ->with(['device'])
            ->orderBy('timestamp', 'desc')
            ->limit(50)
            ->get()
            ->map(function ($alert) {
                return [
                    'id' => $alert->id,
                    'device_id' => $alert->device_id,
                    'device_name' => $alert->device->name,
                    'parameter_name' => $alert->parameter_name,
                    'value' => $alert->value,
                    'severity' => $alert->severity,
                    'message' => $alert->message,
                    'timestamp' => $alert->timestamp->toISOString(),
                    'resolved' => $alert->resolved,
                    'resolved_at' => $alert->resolved_at?->toISOString(),
                    'resolution_time_minutes' => $alert->resolved_at ? 
                        $alert->timestamp->diffInMinutes($alert->resolved_at) : null,
                    'age_formatted' => $this->formatAge($alert->timestamp),
                    'status' => $alert->resolved ? 'resolved' : 'active',
                ];
            })
            ->toArray();

        return $alerts;
    }

    /**
     * Get alert trends over time
     */
    private function getAlertTrends($authorizedDevices): array
    {
        if ($authorizedDevices->isEmpty()) {
            return [
                'daily_trends' => [],
                'hourly_distribution' => [],
                'parameter_trends' => [],
            ];
        }

        $deviceIds = $authorizedDevices->pluck('id')->toArray();

        // Daily trends for the last 7 days
        $dailyTrends = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            
            $dayAlerts = Alert::whereIn('device_id', $deviceIds)
                ->whereDate('timestamp', $date)
                ->selectRaw('severity, COUNT(*) as count')
                ->groupBy('severity')
                ->pluck('count', 'severity')
                ->toArray();

            $dailyTrends[] = [
                'date' => $date->toDateString(),
                'critical' => $dayAlerts['critical'] ?? 0,
                'warning' => $dayAlerts['warning'] ?? 0,
                'info' => $dayAlerts['info'] ?? 0,
                'total' => array_sum($dayAlerts),
            ];
        }

        // Hourly distribution for today
        $hourlyDistribution = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $hourStart = today()->addHours($hour);
            $hourEnd = $hourStart->copy()->addHour();
            
            $hourlyCount = Alert::whereIn('device_id', $deviceIds)
                ->whereBetween('timestamp', [$hourStart, $hourEnd])
                ->count();

            $hourlyDistribution[] = [
                'hour' => $hour,
                'count' => $hourlyCount,
            ];
        }

        // Parameter trends (most common alert parameters)
        $parameterTrends = Alert::whereIn('device_id', $deviceIds)
            ->where('timestamp', '>=', now()->subDays(7))
            ->selectRaw('parameter_name, severity, COUNT(*) as count')
            ->groupBy('parameter_name', 'severity')
            ->orderByDesc('count')
            ->limit(20)
            ->get()
            ->groupBy('parameter_name')
            ->map(function ($paramAlerts, $paramName) {
                $severityCounts = $paramAlerts->pluck('count', 'severity')->toArray();
                return [
                    'parameter_name' => $paramName,
                    'critical' => $severityCounts['critical'] ?? 0,
                    'warning' => $severityCounts['warning'] ?? 0,
                    'info' => $severityCounts['info'] ?? 0,
                    'total' => array_sum($severityCounts),
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->toArray();

        return [
            'daily_trends' => $dailyTrends,
            'hourly_distribution' => $hourlyDistribution,
            'parameter_trends' => $parameterTrends,
        ];
    }

    /**
     * Get alert status for each device
     */
    private function getDeviceAlertStatus($authorizedDevices): array
    {
        if ($authorizedDevices->isEmpty()) {
            return [];
        }

        $deviceStatus = [];

        foreach ($authorizedDevices as $device) {
            $activeAlerts = Alert::where('device_id', $device->id)
                ->where('resolved', false)
                ->selectRaw('severity, COUNT(*) as count')
                ->groupBy('severity')
                ->pluck('count', 'severity')
                ->toArray();

            $criticalCount = $activeAlerts['critical'] ?? 0;
            $warningCount = $activeAlerts['warning'] ?? 0;
            $infoCount = $activeAlerts['info'] ?? 0;
            $totalActive = $criticalCount + $warningCount + $infoCount;

            // Get most recent alert
            $mostRecentAlert = Alert::where('device_id', $device->id)
                ->orderBy('timestamp', 'desc')
                ->first();

            // Determine device alert status
            $status = 'normal';
            if ($criticalCount > 0) {
                $status = 'critical';
            } elseif ($warningCount > 0) {
                $status = 'warning';
            } elseif ($infoCount > 0) {
                $status = 'info';
            }

            $deviceStatus[] = [
                'device_id' => $device->id,
                'device_name' => $device->name,
                'slave_id' => $device->slave_id,
                'location_tag' => $device->location_tag,
                'status' => $status,
                'active_alerts' => [
                    'critical' => $criticalCount,
                    'warning' => $warningCount,
                    'info' => $infoCount,
                    'total' => $totalActive,
                ],
                'most_recent_alert' => $mostRecentAlert ? [
                    'id' => $mostRecentAlert->id,
                    'parameter_name' => $mostRecentAlert->parameter_name,
                    'severity' => $mostRecentAlert->severity,
                    'timestamp' => $mostRecentAlert->timestamp->toISOString(),
                    'age_formatted' => $this->formatAge($mostRecentAlert->timestamp),
                ] : null,
                'alert_rate_today' => $this->getDeviceAlertRateToday($device->id),
                'last_resolved_alert' => $this->getLastResolvedAlert($device->id),
            ];
        }

        // Sort by status priority (critical first)
        usort($deviceStatus, function ($a, $b) {
            $statusPriority = ['critical' => 0, 'warning' => 1, 'info' => 2, 'normal' => 3];
            $aPriority = $statusPriority[$a['status']] ?? 4;
            $bPriority = $statusPriority[$b['status']] ?? 4;
            
            if ($aPriority === $bPriority) {
                return $b['active_alerts']['total'] <=> $a['active_alerts']['total'];
            }
            
            return $aPriority <=> $bPriority;
        });

        return $deviceStatus;
    }

    /**
     * Get alert statistics
     */
    private function getAlertStatistics($authorizedDevices): array
    {
        if ($authorizedDevices->isEmpty()) {
            return [
                'total_alerts_today' => 0,
                'total_resolved_today' => 0,
                'average_resolution_time' => 0,
                'alert_rate_per_device' => 0,
                'most_common_parameter' => null,
                'peak_alert_hour' => null,
            ];
        }

        $deviceIds = $authorizedDevices->pluck('id')->toArray();

        // Total alerts today
        $totalAlertsToday = Alert::whereIn('device_id', $deviceIds)
            ->whereDate('timestamp', today())
            ->count();

        // Total resolved today
        $totalResolvedToday = Alert::whereIn('device_id', $deviceIds)
            ->where('resolved', true)
            ->whereDate('resolved_at', today())
            ->count();

        // Average resolution time (in minutes)
        $avgResolutionTime = Alert::whereIn('device_id', $deviceIds)
            ->where('resolved', true)
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '>=', now()->subDays(7))
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, timestamp, resolved_at)) as avg_time')
            ->value('avg_time') ?? 0;

        // Alert rate per device
        $alertRatePerDevice = $authorizedDevices->count() > 0 ? $totalAlertsToday / $authorizedDevices->count() : 0;

        // Most common parameter causing alerts
        $mostCommonParameter = Alert::whereIn('device_id', $deviceIds)
            ->where('timestamp', '>=', now()->subDays(7))
            ->selectRaw('parameter_name, COUNT(*) as count')
            ->groupBy('parameter_name')
            ->orderByDesc('count')
            ->first();

        // Peak alert hour
        $peakAlertHour = Alert::whereIn('device_id', $deviceIds)
            ->where('timestamp', '>=', now()->subDays(7))
            ->selectRaw('HOUR(timestamp) as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->orderByDesc('count')
            ->first();

        return [
            'total_alerts_today' => $totalAlertsToday,
            'total_resolved_today' => $totalResolvedToday,
            'average_resolution_time' => round($avgResolutionTime, 1),
            'alert_rate_per_device' => round($alertRatePerDevice, 2),
            'most_common_parameter' => $mostCommonParameter ? [
                'parameter' => $mostCommonParameter->parameter_name,
                'count' => $mostCommonParameter->count,
            ] : null,
            'peak_alert_hour' => $peakAlertHour ? [
                'hour' => $peakAlertHour->hour,
                'count' => $peakAlertHour->count,
            ] : null,
            'resolution_rate' => $totalAlertsToday > 0 ? round(($totalResolvedToday / $totalAlertsToday) * 100, 1) : 0,
        ];
    }

    /**
     * Helper methods
     */
    private function formatAge(\Carbon\Carbon $timestamp): string
    {
        $diffInMinutes = $timestamp->diffInMinutes(now());
        
        if ($diffInMinutes < 1) {
            return 'Just now';
        } elseif ($diffInMinutes < 60) {
            return $diffInMinutes . 'm ago';
        } elseif ($diffInMinutes < 1440) {
            $hours = floor($diffInMinutes / 60);
            return $hours . 'h ago';
        } else {
            $days = floor($diffInMinutes / 1440);
            return $days . 'd ago';
        }
    }

    private function calculateAlertPriority($alert): int
    {
        $score = 0;

        // Severity weight
        $severityWeights = [
            'critical' => 100,
            'warning' => 50,
            'info' => 10,
        ];
        $score += $severityWeights[$alert->severity] ?? 0;

        // Age weight (older alerts get higher priority)
        $ageMinutes = $alert->timestamp->diffInMinutes(now());
        if ($ageMinutes > 60) {
            $score += min(50, floor($ageMinutes / 60) * 5);
        }

        // Parameter criticality weight
        $criticalParameters = ['temperature', 'pressure', 'voltage', 'current'];
        foreach ($criticalParameters as $param) {
            if (stripos($alert->parameter_name, $param) !== false) {
                $score += 25;
                break;
            }
        }

        return $score;
    }

    private function getEscalationLevel($alert): string
    {
        $ageMinutes = $alert->timestamp->diffInMinutes(now());
        
        if ($alert->severity === 'critical') {
            if ($ageMinutes > 120) return 'high';
            if ($ageMinutes > 60) return 'medium';
            return 'low';
        }
        
        if ($alert->severity === 'warning') {
            if ($ageMinutes > 240) return 'medium';
            return 'low';
        }
        
        return 'none';
    }

    private function getRecommendedAction($alert): string
    {
        $parameterName = strtolower($alert->parameter_name);
        
        if (stripos($parameterName, 'temperature') !== false) {
            return $alert->severity === 'critical' ? 
                'Check cooling system immediately' : 
                'Monitor temperature trends';
        }
        
        if (stripos($parameterName, 'voltage') !== false) {
            return $alert->severity === 'critical' ? 
                'Check electrical connections' : 
                'Verify power supply stability';
        }
        
        if (stripos($parameterName, 'current') !== false) {
            return $alert->severity === 'critical' ? 
                'Check for electrical faults' : 
                'Monitor load conditions';
        }
        
        if (stripos($parameterName, 'power') !== false) {
            return $alert->severity === 'critical' ? 
                'Investigate power anomaly' : 
                'Review power consumption patterns';
        }
        
        return $alert->severity === 'critical' ? 
            'Investigate immediately' : 
            'Monitor and review';
    }

    private function getMostCriticalDevice($deviceIds): ?array
    {
        $deviceAlertCounts = Alert::whereIn('device_id', $deviceIds)
            ->where('resolved', false)
            ->selectRaw('
                device_id,
                SUM(CASE WHEN severity = "critical" THEN 1 ELSE 0 END) as critical_count,
                SUM(CASE WHEN severity = "warning" THEN 1 ELSE 0 END) as warning_count,
                COUNT(*) as total_count
            ')
            ->groupBy('device_id')
            ->orderByDesc('critical_count')
            ->orderByDesc('warning_count')
            ->orderByDesc('total_count')
            ->first();

        if (!$deviceAlertCounts) {
            return null;
        }

        $device = Device::find($deviceAlertCounts->device_id);
        
        return [
            'device_id' => $device->id,
            'device_name' => $device->name,
            'critical_alerts' => $deviceAlertCounts->critical_count,
            'warning_alerts' => $deviceAlertCounts->warning_count,
            'total_alerts' => $deviceAlertCounts->total_count,
        ];
    }

    private function getDeviceAlertRateToday($deviceId): float
    {
        $alertsToday = Alert::where('device_id', $deviceId)
            ->whereDate('timestamp', today())
            ->count();

        // Calculate rate per hour (24 hours in a day)
        return round($alertsToday / 24, 2);
    }

    private function getLastResolvedAlert($deviceId): ?array
    {
        $lastResolved = Alert::where('device_id', $deviceId)
            ->where('resolved', true)
            ->orderBy('resolved_at', 'desc')
            ->first();

        if (!$lastResolved) {
            return null;
        }

        return [
            'id' => $lastResolved->id,
            'parameter_name' => $lastResolved->parameter_name,
            'severity' => $lastResolved->severity,
            'resolved_at' => $lastResolved->resolved_at->toISOString(),
            'resolution_time_minutes' => $lastResolved->timestamp->diffInMinutes($lastResolved->resolved_at),
        ];
    }

    protected function getFallbackData(): array
    {
        return [
            'active_alerts' => [],
            'alert_summary' => [
                'total_active' => 0,
                'critical_count' => 0,
                'warning_count' => 0,
                'info_count' => 0,
                'devices_with_alerts' => 0,
                'most_critical_device' => null,
            ],
            'recent_alerts' => [],
            'alert_trends' => [
                'daily_trends' => [],
                'hourly_distribution' => [],
                'parameter_trends' => [],
            ],
            'device_alert_status' => [],
            'alert_statistics' => [
                'total_alerts_today' => 0,
                'total_resolved_today' => 0,
                'average_resolution_time' => 0,
                'alert_rate_per_device' => 0,
                'most_common_parameter' => null,
                'peak_alert_hour' => null,
            ],
        ];
    }
}