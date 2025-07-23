<?php

namespace App\Widgets\Global;

use App\Widgets\BaseWidget;
use App\Models\Alert;
use App\Models\Device;
use App\Models\Gateway;
use Illuminate\Support\Facades\DB;

class CrossGatewayAlertsWidget extends BaseWidget
{
    protected function getData(): array
    {
        $authorizedDevices = $this->permissionService->getAuthorizedDevices($this->user);

        return [
            'critical_alerts' => $this->getAlertsByType($authorizedDevices, 'critical'),
            'warning_alerts' => $this->getAlertsByType($authorizedDevices, 'warning'),
            'info_alerts' => $this->getAlertsByType($authorizedDevices, 'info'),
            'recent_alerts' => $this->getRecentAlerts($authorizedDevices, 10),
            'alert_trends' => $this->getAlertTrends($authorizedDevices),
            'gateway_alert_summary' => $this->getGatewayAlertSummary($authorizedDevices),
            'alert_statistics' => $this->getAlertStatistics($authorizedDevices),
        ];
    }

    protected function getWidgetType(): string
    {
        return 'cross-gateway-alerts';
    }

    protected function getWidgetName(): string
    {
        return 'Cross-Gateway Alerts';
    }

    protected function getWidgetDescription(): string
    {
        return 'Alerts from all authorized gateways and devices';
    }

    protected function getWidgetCategory(): string
    {
        return 'alerts';
    }

    protected function getWidgetPriority(): int
    {
        return 20; // High priority
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
     * Get alerts by severity type
     */
    private function getAlertsByType($authorizedDevices, string $severity): array
    {
        if ($authorizedDevices->isEmpty()) {
            return [];
        }

        $deviceIds = $authorizedDevices->pluck('id')->toArray();

        $alerts = Alert::whereIn('device_id', $deviceIds)
            ->where('severity', $severity)
            ->where('resolved', false)
            ->with(['device.gateway'])
            ->orderBy('timestamp', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($alert) {
                return [
                    'id' => $alert->id,
                    'device_id' => $alert->device_id,
                    'device_name' => $alert->device->name,
                    'gateway_id' => $alert->device->gateway_id,
                    'gateway_name' => $alert->device->gateway->name,
                    'parameter_name' => $alert->parameter_name,
                    'value' => $alert->value,
                    'message' => $alert->message,
                    'timestamp' => $alert->timestamp->toISOString(),
                    'age_minutes' => $alert->timestamp->diffInMinutes(now()),
                ];
            })
            ->toArray();

        return $alerts;
    }

    /**
     * Get recent alerts across all severities
     */
    private function getRecentAlerts($authorizedDevices, int $limit): array
    {
        if ($authorizedDevices->isEmpty()) {
            return [];
        }

        $deviceIds = $authorizedDevices->pluck('id')->toArray();

        $alerts = Alert::whereIn('device_id', $deviceIds)
            ->with(['device.gateway'])
            ->orderBy('timestamp', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($alert) {
                return [
                    'id' => $alert->id,
                    'device_id' => $alert->device_id,
                    'device_name' => $alert->device->name,
                    'gateway_id' => $alert->device->gateway_id,
                    'gateway_name' => $alert->device->gateway->name,
                    'parameter_name' => $alert->parameter_name,
                    'value' => $alert->value,
                    'severity' => $alert->severity,
                    'message' => $alert->message,
                    'timestamp' => $alert->timestamp->toISOString(),
                    'resolved' => $alert->resolved,
                    'resolved_at' => $alert->resolved_at?->toISOString(),
                    'age_minutes' => $alert->timestamp->diffInMinutes(now()),
                    'priority_score' => $this->calculateAlertPriority($alert),
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
            return [];
        }

        $deviceIds = $authorizedDevices->pluck('id')->toArray();

        // Get daily alert counts for the last 7 days
        $trends = Alert::whereIn('device_id', $deviceIds)
            ->where('timestamp', '>=', now()->subDays(7))
            ->selectRaw('DATE(timestamp) as date, severity, COUNT(*) as count')
            ->groupBy('date', 'severity')
            ->orderBy('date')
            ->get()
            ->groupBy('date')
            ->map(function ($dayAlerts, $date) {
                $severityCounts = $dayAlerts->pluck('count', 'severity')->toArray();
                return [
                    'date' => $date,
                    'critical' => $severityCounts['critical'] ?? 0,
                    'warning' => $severityCounts['warning'] ?? 0,
                    'info' => $severityCounts['info'] ?? 0,
                    'total' => array_sum($severityCounts),
                ];
            })
            ->values()
            ->toArray();

        return $trends;
    }

    /**
     * Get alert summary by gateway
     */
    private function getGatewayAlertSummary($authorizedDevices): array
    {
        if ($authorizedDevices->isEmpty()) {
            return [];
        }

        $deviceIds = $authorizedDevices->pluck('id')->toArray();

        $gatewaySummary = Alert::whereIn('device_id', $deviceIds)
            ->where('resolved', false)
            ->join('devices', 'alerts.device_id', '=', 'devices.id')
            ->join('gateways', 'devices.gateway_id', '=', 'gateways.id')
            ->selectRaw('
                gateways.id as gateway_id,
                gateways.name as gateway_name,
                alerts.severity,
                COUNT(*) as count
            ')
            ->groupBy('gateways.id', 'gateways.name', 'alerts.severity')
            ->get()
            ->groupBy('gateway_id')
            ->map(function ($gatewayAlerts, $gatewayId) {
                $severityCounts = $gatewayAlerts->pluck('count', 'severity')->toArray();
                $gatewayName = $gatewayAlerts->first()->gateway_name;
                
                return [
                    'gateway_id' => $gatewayId,
                    'gateway_name' => $gatewayName,
                    'critical' => $severityCounts['critical'] ?? 0,
                    'warning' => $severityCounts['warning'] ?? 0,
                    'info' => $severityCounts['info'] ?? 0,
                    'total' => array_sum($severityCounts),
                    'priority_score' => $this->calculateGatewayPriority($severityCounts),
                ];
            })
            ->sortByDesc('priority_score')
            ->values()
            ->toArray();

        return $gatewaySummary;
    }

    /**
     * Get overall alert statistics
     */
    private function getAlertStatistics($authorizedDevices): array
    {
        if ($authorizedDevices->isEmpty()) {
            return [
                'total_active' => 0,
                'total_resolved_today' => 0,
                'average_resolution_time' => 0,
                'most_common_parameter' => null,
                'busiest_gateway' => null,
            ];
        }

        $deviceIds = $authorizedDevices->pluck('id')->toArray();

        // Total active alerts
        $totalActive = Alert::whereIn('device_id', $deviceIds)
            ->where('resolved', false)
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
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, timestamp, resolved_at)) as avg_time')
            ->value('avg_time') ?? 0;

        // Most common parameter causing alerts
        $mostCommonParameter = Alert::whereIn('device_id', $deviceIds)
            ->where('timestamp', '>=', now()->subDays(7))
            ->selectRaw('parameter_name, COUNT(*) as count')
            ->groupBy('parameter_name')
            ->orderByDesc('count')
            ->first();

        // Gateway with most alerts
        $busiestGateway = Alert::whereIn('device_id', $deviceIds)
            ->where('timestamp', '>=', now()->subDays(7))
            ->join('devices', 'alerts.device_id', '=', 'devices.id')
            ->join('gateways', 'devices.gateway_id', '=', 'gateways.id')
            ->selectRaw('gateways.id, gateways.name, COUNT(*) as alert_count')
            ->groupBy('gateways.id', 'gateways.name')
            ->orderByDesc('alert_count')
            ->first();

        return [
            'total_active' => $totalActive,
            'total_resolved_today' => $totalResolvedToday,
            'average_resolution_time' => round($avgResolutionTime, 1),
            'most_common_parameter' => $mostCommonParameter ? [
                'parameter' => $mostCommonParameter->parameter_name,
                'count' => $mostCommonParameter->count,
            ] : null,
            'busiest_gateway' => $busiestGateway ? [
                'id' => $busiestGateway->id,
                'name' => $busiestGateway->name,
                'alert_count' => $busiestGateway->alert_count,
            ] : null,
        ];
    }

    /**
     * Calculate alert priority score
     */
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

        // Unresolved weight
        if (!$alert->resolved) {
            $score += 25;
        }

        return $score;
    }

    /**
     * Calculate gateway priority based on alert severity distribution
     */
    private function calculateGatewayPriority(array $severityCounts): int
    {
        $critical = $severityCounts['critical'] ?? 0;
        $warning = $severityCounts['warning'] ?? 0;
        $info = $severityCounts['info'] ?? 0;

        return ($critical * 100) + ($warning * 50) + ($info * 10);
    }

    protected function getFallbackData(): array
    {
        return [
            'critical_alerts' => [],
            'warning_alerts' => [],
            'info_alerts' => [],
            'recent_alerts' => [],
            'alert_trends' => [],
            'gateway_alert_summary' => [],
            'alert_statistics' => [
                'total_active' => 0,
                'total_resolved_today' => 0,
                'average_resolution_time' => 0,
                'most_common_parameter' => null,
                'busiest_gateway' => null,
            ],
        ];
    }
}