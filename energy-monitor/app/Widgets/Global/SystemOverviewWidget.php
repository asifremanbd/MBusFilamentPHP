<?php

namespace App\Widgets\Global;

use App\Widgets\BaseWidget;
use App\Models\Gateway;
use App\Models\Device;
use App\Models\Reading;
use App\Models\Alert;
use Illuminate\Support\Facades\DB;

class SystemOverviewWidget extends BaseWidget
{
    protected function getData(): array
    {
        $authorizedGateways = $this->permissionService->getAuthorizedGateways($this->user);
        $authorizedDevices = $this->permissionService->getAuthorizedDevices($this->user);

        return [
            'total_energy_consumption' => $this->calculateTotalConsumption($authorizedDevices),
            'active_devices_count' => $this->getActiveDevicesCount($authorizedDevices),
            'critical_alerts_count' => $this->getCriticalAlertsCount($authorizedDevices),
            'system_health_score' => $this->calculateSystemHealth($authorizedGateways, $authorizedDevices),
            'top_consuming_gateways' => $this->getTopConsumingGateways($authorizedGateways, 5),
            'energy_trend' => $this->getEnergyTrend($authorizedDevices),
            'gateway_summary' => $this->getGatewaySummary($authorizedGateways),
        ];
    }

    protected function getWidgetType(): string
    {
        return 'system-overview';
    }

    protected function getWidgetName(): string
    {
        return 'System Overview';
    }

    protected function getWidgetDescription(): string
    {
        return 'Overall system statistics and energy consumption summary';
    }

    protected function getWidgetCategory(): string
    {
        return 'overview';
    }

    protected function getWidgetPriority(): int
    {
        return 10; // High priority
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
     * Calculate total energy consumption from authorized devices
     */
    private function calculateTotalConsumption($authorizedDevices): array
    {
        if ($authorizedDevices->isEmpty()) {
            return [
                'current_kw' => 0,
                'total_kwh' => 0,
                'daily_kwh' => 0,
                'monthly_kwh' => 0,
            ];
        }

        $deviceIds = $authorizedDevices->pluck('id')->toArray();

        // Get latest power readings
        $currentPower = Reading::whereIn('device_id', $deviceIds)
            ->whereHas('register', function ($query) {
                $query->where('parameter_name', 'LIKE', '%Power%')
                      ->orWhere('parameter_name', 'LIKE', '%Active Power%');
            })
            ->where('timestamp', '>=', now()->subMinutes(5))
            ->sum('value');

        // Get total energy readings
        $totalEnergy = Reading::whereIn('device_id', $deviceIds)
            ->whereHas('register', function ($query) {
                $query->where('parameter_name', 'LIKE', '%Total Energy%')
                      ->orWhere('parameter_name', 'LIKE', '%Energy%');
            })
            ->orderBy('timestamp', 'desc')
            ->limit(count($deviceIds))
            ->sum('value');

        // Get daily energy consumption
        $dailyEnergy = Reading::whereIn('device_id', $deviceIds)
            ->whereHas('register', function ($query) {
                $query->where('parameter_name', 'LIKE', '%Power%')
                      ->orWhere('parameter_name', 'LIKE', '%Active Power%');
            })
            ->where('timestamp', '>=', now()->startOfDay())
            ->avg('value') * 24; // Approximate daily consumption

        // Get monthly energy consumption
        $monthlyEnergy = Reading::whereIn('device_id', $deviceIds)
            ->whereHas('register', function ($query) {
                $query->where('parameter_name', 'LIKE', '%Power%')
                      ->orWhere('parameter_name', 'LIKE', '%Active Power%');
            })
            ->where('timestamp', '>=', now()->startOfMonth())
            ->avg('value') * 24 * 30; // Approximate monthly consumption

        return [
            'current_kw' => round($currentPower, 2),
            'total_kwh' => round($totalEnergy, 2),
            'daily_kwh' => round($dailyEnergy, 2),
            'monthly_kwh' => round($monthlyEnergy, 2),
        ];
    }

    /**
     * Get count of active devices
     */
    private function getActiveDevicesCount($authorizedDevices): array
    {
        if ($authorizedDevices->isEmpty()) {
            return [
                'total' => 0,
                'active' => 0,
                'inactive' => 0,
                'offline' => 0,
            ];
        }

        $deviceIds = $authorizedDevices->pluck('id')->toArray();
        $total = count($deviceIds);

        // Devices with recent readings (last 10 minutes) are considered active
        $activeDevices = Reading::whereIn('device_id', $deviceIds)
            ->where('timestamp', '>=', now()->subMinutes(10))
            ->distinct('device_id')
            ->count('device_id');

        // Devices with readings in last hour but not last 10 minutes are inactive
        $inactiveDevices = Reading::whereIn('device_id', $deviceIds)
            ->where('timestamp', '>=', now()->subHour())
            ->where('timestamp', '<', now()->subMinutes(10))
            ->distinct('device_id')
            ->count('device_id');

        $offline = $total - $activeDevices - $inactiveDevices;

        return [
            'total' => $total,
            'active' => $activeDevices,
            'inactive' => $inactiveDevices,
            'offline' => max(0, $offline),
        ];
    }

    /**
     * Get count of critical alerts
     */
    private function getCriticalAlertsCount($authorizedDevices): array
    {
        if ($authorizedDevices->isEmpty()) {
            return [
                'critical' => 0,
                'warning' => 0,
                'info' => 0,
                'total_unresolved' => 0,
            ];
        }

        $deviceIds = $authorizedDevices->pluck('id')->toArray();

        $alerts = Alert::whereIn('device_id', $deviceIds)
            ->where('resolved', false)
            ->selectRaw('severity, COUNT(*) as count')
            ->groupBy('severity')
            ->pluck('count', 'severity')
            ->toArray();

        return [
            'critical' => $alerts['critical'] ?? 0,
            'warning' => $alerts['warning'] ?? 0,
            'info' => $alerts['info'] ?? 0,
            'total_unresolved' => array_sum($alerts),
        ];
    }

    /**
     * Calculate overall system health score
     */
    private function calculateSystemHealth($authorizedGateways, $authorizedDevices): array
    {
        if ($authorizedGateways->isEmpty() || $authorizedDevices->isEmpty()) {
            return [
                'score' => 0,
                'status' => 'unknown',
                'factors' => [],
            ];
        }

        $factors = [];
        $totalScore = 0;
        $maxScore = 0;

        // Device connectivity factor (40% weight)
        $deviceStats = $this->getActiveDevicesCount($authorizedDevices);
        $deviceConnectivity = $deviceStats['total'] > 0 
            ? ($deviceStats['active'] / $deviceStats['total']) * 100 
            : 0;
        $factors['device_connectivity'] = [
            'score' => $deviceConnectivity,
            'weight' => 40,
            'description' => 'Percentage of devices actively reporting'
        ];
        $totalScore += $deviceConnectivity * 0.4;
        $maxScore += 40;

        // Alert severity factor (30% weight)
        $alertStats = $this->getCriticalAlertsCount($authorizedDevices);
        $alertScore = 100;
        if ($alertStats['critical'] > 0) {
            $alertScore -= min(50, $alertStats['critical'] * 10);
        }
        if ($alertStats['warning'] > 0) {
            $alertScore -= min(30, $alertStats['warning'] * 5);
        }
        $factors['alert_status'] = [
            'score' => max(0, $alertScore),
            'weight' => 30,
            'description' => 'System alert status impact'
        ];
        $totalScore += max(0, $alertScore) * 0.3;
        $maxScore += 30;

        // Gateway communication factor (30% weight)
        $gatewayScore = $this->calculateGatewayHealthScore($authorizedGateways);
        $factors['gateway_communication'] = [
            'score' => $gatewayScore,
            'weight' => 30,
            'description' => 'Gateway communication reliability'
        ];
        $totalScore += $gatewayScore * 0.3;
        $maxScore += 30;

        $finalScore = $maxScore > 0 ? round(($totalScore / $maxScore) * 100, 1) : 0;

        return [
            'score' => $finalScore,
            'status' => $this->getHealthStatus($finalScore),
            'factors' => $factors,
        ];
    }

    /**
     * Calculate gateway health score
     */
    private function calculateGatewayHealthScore($authorizedGateways): float
    {
        if ($authorizedGateways->isEmpty()) {
            return 0;
        }

        $totalScore = 0;
        $gatewayCount = $authorizedGateways->count();

        foreach ($authorizedGateways as $gateway) {
            $score = 100;
            
            // Check if gateway has recent device readings
            $recentReadings = Reading::whereHas('device', function ($query) use ($gateway) {
                $query->where('gateway_id', $gateway->id);
            })->where('timestamp', '>=', now()->subMinutes(15))->count();

            if ($recentReadings === 0) {
                $score = 0; // No recent data
            } elseif ($recentReadings < 5) {
                $score = 50; // Limited data
            }

            $totalScore += $score;
        }

        return $gatewayCount > 0 ? $totalScore / $gatewayCount : 0;
    }

    /**
     * Get health status based on score
     */
    private function getHealthStatus(float $score): string
    {
        if ($score >= 90) return 'excellent';
        if ($score >= 75) return 'good';
        if ($score >= 60) return 'fair';
        if ($score >= 40) return 'poor';
        return 'critical';
    }

    /**
     * Get top consuming gateways
     */
    private function getTopConsumingGateways($authorizedGateways, int $limit): array
    {
        if ($authorizedGateways->isEmpty()) {
            return [];
        }

        $gatewayConsumption = [];

        foreach ($authorizedGateways as $gateway) {
            $consumption = Reading::whereHas('device', function ($query) use ($gateway) {
                $query->where('gateway_id', $gateway->id);
            })
            ->whereHas('register', function ($query) {
                $query->where('parameter_name', 'LIKE', '%Power%')
                      ->orWhere('parameter_name', 'LIKE', '%Active Power%');
            })
            ->where('timestamp', '>=', now()->subHour())
            ->avg('value') ?? 0;

            $gatewayConsumption[] = [
                'id' => $gateway->id,
                'name' => $gateway->name,
                'consumption_kw' => round($consumption, 2),
                'device_count' => $gateway->devices()->count(),
            ];
        }

        // Sort by consumption and return top N
        usort($gatewayConsumption, function ($a, $b) {
            return $b['consumption_kw'] <=> $a['consumption_kw'];
        });

        return array_slice($gatewayConsumption, 0, $limit);
    }

    /**
     * Get energy consumption trend
     */
    private function getEnergyTrend($authorizedDevices): array
    {
        if ($authorizedDevices->isEmpty()) {
            return [];
        }

        $deviceIds = $authorizedDevices->pluck('id')->toArray();

        // Get hourly consumption for the last 24 hours
        $trend = Reading::whereIn('device_id', $deviceIds)
            ->whereHas('register', function ($query) {
                $query->where('parameter_name', 'LIKE', '%Power%')
                      ->orWhere('parameter_name', 'LIKE', '%Active Power%');
            })
            ->where('timestamp', '>=', now()->subDay())
            ->selectRaw('DATE_FORMAT(timestamp, "%Y-%m-%d %H:00:00") as hour, AVG(value) as avg_consumption')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->map(function ($item) {
                return [
                    'timestamp' => $item->hour,
                    'consumption' => round($item->avg_consumption, 2),
                ];
            })
            ->toArray();

        return $trend;
    }

    /**
     * Get gateway summary
     */
    private function getGatewaySummary($authorizedGateways): array
    {
        return [
            'total_gateways' => $authorizedGateways->count(),
            'online_gateways' => $this->getOnlineGatewayCount($authorizedGateways),
            'total_devices' => $authorizedGateways->sum(function ($gateway) {
                return $gateway->devices()->count();
            }),
        ];
    }

    /**
     * Get count of online gateways
     */
    private function getOnlineGatewayCount($authorizedGateways): int
    {
        $onlineCount = 0;

        foreach ($authorizedGateways as $gateway) {
            $recentReadings = Reading::whereHas('device', function ($query) use ($gateway) {
                $query->where('gateway_id', $gateway->id);
            })->where('timestamp', '>=', now()->subMinutes(10))->count();

            if ($recentReadings > 0) {
                $onlineCount++;
            }
        }

        return $onlineCount;
    }

    protected function getFallbackData(): array
    {
        return [
            'total_energy_consumption' => [
                'current_kw' => 0,
                'total_kwh' => 0,
                'daily_kwh' => 0,
                'monthly_kwh' => 0,
            ],
            'active_devices_count' => [
                'total' => 0,
                'active' => 0,
                'inactive' => 0,
                'offline' => 0,
            ],
            'critical_alerts_count' => [
                'critical' => 0,
                'warning' => 0,
                'info' => 0,
                'total_unresolved' => 0,
            ],
            'system_health_score' => [
                'score' => 0,
                'status' => 'unknown',
                'factors' => [],
            ],
            'top_consuming_gateways' => [],
            'energy_trend' => [],
            'gateway_summary' => [
                'total_gateways' => 0,
                'online_gateways' => 0,
                'total_devices' => 0,
            ],
        ];
    }
}