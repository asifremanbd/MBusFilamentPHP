<?php

namespace App\Widgets\Global;

use App\Widgets\BaseWidget;
use App\Models\Gateway;
use App\Models\Device;
use App\Models\Reading;
use App\Models\Register;
use Illuminate\Support\Facades\DB;

class TopConsumingGatewaysWidget extends BaseWidget
{
    protected function getData(): array
    {
        $authorizedGateways = $this->permissionService->getAuthorizedGateways($this->user);
        $limit = $this->config['limit'] ?? 10;
        $timeRange = $this->config['time_range'] ?? '24h';

        return [
            'top_gateways' => $this->getTopConsumingGateways($authorizedGateways, $limit, $timeRange),
            'consumption_comparison' => $this->getConsumptionComparison($authorizedGateways, $timeRange),
            'efficiency_metrics' => $this->getEfficiencyMetrics($authorizedGateways, $timeRange),
            'consumption_trends' => $this->getConsumptionTrends($authorizedGateways),
            'summary_statistics' => $this->getSummaryStatistics($authorizedGateways, $timeRange),
        ];
    }

    protected function getWidgetType(): string
    {
        return 'top-consuming-gateways';
    }

    protected function getWidgetName(): string
    {
        return 'Top Consuming Gateways';
    }

    protected function getWidgetDescription(): string
    {
        return 'Gateways with highest energy consumption and efficiency metrics';
    }

    protected function getWidgetCategory(): string
    {
        return 'analytics';
    }

    protected function getWidgetPriority(): int
    {
        return 30;
    }

    protected function supportsRealTimeUpdates(): bool
    {
        return true;
    }

    protected function getRealTimeUpdateInterval(): int
    {
        return 300; // Update every 5 minutes
    }

    /**
     * Get top consuming gateways with detailed metrics
     */
    private function getTopConsumingGateways($authorizedGateways, int $limit, string $timeRange): array
    {
        if ($authorizedGateways->isEmpty()) {
            return [];
        }

        $timeFilter = $this->getTimeFilter($timeRange);
        $gatewayConsumption = [];

        foreach ($authorizedGateways as $gateway) {
            $consumption = $this->calculateGatewayConsumption($gateway, $timeFilter);
            $efficiency = $this->calculateGatewayEfficiency($gateway, $timeFilter);
            
            $gatewayConsumption[] = [
                'id' => $gateway->id,
                'name' => $gateway->name,
                'location' => $gateway->gnss_location,
                'consumption' => $consumption,
                'efficiency' => $efficiency,
                'device_count' => $gateway->devices()->count(),
                'active_devices' => $this->getActiveDeviceCount($gateway),
                'cost_estimate' => $this->calculateCostEstimate($consumption['total_kwh']),
                'performance_score' => $this->calculatePerformanceScore($consumption, $efficiency),
            ];
        }

        // Sort by total consumption and return top N
        usort($gatewayConsumption, function ($a, $b) {
            return $b['consumption']['total_kwh'] <=> $a['consumption']['total_kwh'];
        });

        return array_slice($gatewayConsumption, 0, $limit);
    }

    /**
     * Calculate gateway consumption metrics
     */
    private function calculateGatewayConsumption($gateway, $timeFilter): array
    {
        $deviceIds = $gateway->devices()->pluck('id')->toArray();
        
        if (empty($deviceIds)) {
            return [
                'current_kw' => 0,
                'average_kw' => 0,
                'peak_kw' => 0,
                'total_kwh' => 0,
                'consumption_pattern' => 'no_data',
            ];
        }

        // Get power readings
        $powerReadings = Reading::whereIn('device_id', $deviceIds)
            ->whereHas('register', function ($query) {
                $query->where('parameter_name', 'LIKE', '%Power%')
                      ->orWhere('parameter_name', 'LIKE', '%Active Power%');
            })
            ->where('timestamp', '>=', $timeFilter)
            ->get();

        if ($powerReadings->isEmpty()) {
            return [
                'current_kw' => 0,
                'average_kw' => 0,
                'peak_kw' => 0,
                'total_kwh' => 0,
                'consumption_pattern' => 'no_data',
            ];
        }

        $currentKw = $powerReadings->where('timestamp', '>=', now()->subMinutes(5))->avg('value') ?? 0;
        $averageKw = $powerReadings->avg('value') ?? 0;
        $peakKw = $powerReadings->max('value') ?? 0;
        
        // Estimate total kWh based on average consumption and time period
        $hours = $this->getHoursFromTimeRange($this->config['time_range'] ?? '24h');
        $totalKwh = $averageKw * $hours;

        return [
            'current_kw' => round($currentKw, 2),
            'average_kw' => round($averageKw, 2),
            'peak_kw' => round($peakKw, 2),
            'total_kwh' => round($totalKwh, 2),
            'consumption_pattern' => $this->analyzeConsumptionPattern($powerReadings),
        ];
    }

    /**
     * Calculate gateway efficiency metrics
     */
    private function calculateGatewayEfficiency($gateway, $timeFilter): array
    {
        $deviceIds = $gateway->devices()->pluck('id')->toArray();
        
        if (empty($deviceIds)) {
            return [
                'efficiency_score' => 0,
                'load_factor' => 0,
                'utilization_rate' => 0,
                'efficiency_trend' => 'stable',
            ];
        }

        // Get power readings for efficiency calculation
        $powerReadings = Reading::whereIn('device_id', $deviceIds)
            ->whereHas('register', function ($query) {
                $query->where('parameter_name', 'LIKE', '%Power%');
            })
            ->where('timestamp', '>=', $timeFilter)
            ->get();

        if ($powerReadings->isEmpty()) {
            return [
                'efficiency_score' => 0,
                'load_factor' => 0,
                'utilization_rate' => 0,
                'efficiency_trend' => 'no_data',
            ];
        }

        $averagePower = $powerReadings->avg('value') ?? 0;
        $peakPower = $powerReadings->max('value') ?? 0;
        
        // Load factor: average load / peak load
        $loadFactor = $peakPower > 0 ? ($averagePower / $peakPower) * 100 : 0;
        
        // Utilization rate based on device activity
        $activeDevices = $this->getActiveDeviceCount($gateway);
        $totalDevices = $gateway->devices()->count();
        $utilizationRate = $totalDevices > 0 ? ($activeDevices / $totalDevices) * 100 : 0;
        
        // Efficiency score (composite metric)
        $efficiencyScore = ($loadFactor * 0.6) + ($utilizationRate * 0.4);
        
        return [
            'efficiency_score' => round($efficiencyScore, 1),
            'load_factor' => round($loadFactor, 1),
            'utilization_rate' => round($utilizationRate, 1),
            'efficiency_trend' => $this->calculateEfficiencyTrend($gateway, $timeFilter),
        ];
    }

    /**
     * Get consumption comparison between gateways
     */
    private function getConsumptionComparison($authorizedGateways, string $timeRange): array
    {
        if ($authorizedGateways->count() < 2) {
            return [];
        }

        $timeFilter = $this->getTimeFilter($timeRange);
        $comparisons = [];

        foreach ($authorizedGateways as $gateway) {
            $consumption = $this->calculateGatewayConsumption($gateway, $timeFilter);
            $comparisons[] = [
                'gateway_id' => $gateway->id,
                'gateway_name' => $gateway->name,
                'total_kwh' => $consumption['total_kwh'],
                'average_kw' => $consumption['average_kw'],
            ];
        }

        // Calculate percentages relative to total
        $totalConsumption = array_sum(array_column($comparisons, 'total_kwh'));
        
        foreach ($comparisons as &$comparison) {
            $comparison['percentage'] = $totalConsumption > 0 
                ? round(($comparison['total_kwh'] / $totalConsumption) * 100, 1)
                : 0;
        }

        return $comparisons;
    }

    /**
     * Get efficiency metrics across all gateways
     */
    private function getEfficiencyMetrics($authorizedGateways, string $timeRange): array
    {
        if ($authorizedGateways->isEmpty()) {
            return [
                'average_efficiency' => 0,
                'best_performer' => null,
                'worst_performer' => null,
                'efficiency_distribution' => [],
            ];
        }

        $timeFilter = $this->getTimeFilter($timeRange);
        $efficiencyScores = [];

        foreach ($authorizedGateways as $gateway) {
            $efficiency = $this->calculateGatewayEfficiency($gateway, $timeFilter);
            $efficiencyScores[] = [
                'gateway_id' => $gateway->id,
                'gateway_name' => $gateway->name,
                'efficiency_score' => $efficiency['efficiency_score'],
            ];
        }

        if (empty($efficiencyScores)) {
            return [
                'average_efficiency' => 0,
                'best_performer' => null,
                'worst_performer' => null,
                'efficiency_distribution' => [],
            ];
        }

        $averageEfficiency = array_sum(array_column($efficiencyScores, 'efficiency_score')) / count($efficiencyScores);
        
        usort($efficiencyScores, function ($a, $b) {
            return $b['efficiency_score'] <=> $a['efficiency_score'];
        });

        return [
            'average_efficiency' => round($averageEfficiency, 1),
            'best_performer' => $efficiencyScores[0] ?? null,
            'worst_performer' => end($efficiencyScores) ?: null,
            'efficiency_distribution' => $this->getEfficiencyDistribution($efficiencyScores),
        ];
    }

    /**
     * Get consumption trends over time
     */
    private function getConsumptionTrends($authorizedGateways): array
    {
        if ($authorizedGateways->isEmpty()) {
            return [];
        }

        $trends = [];
        
        foreach ($authorizedGateways->take(5) as $gateway) { // Limit to top 5 for performance
            $deviceIds = $gateway->devices()->pluck('id')->toArray();
            
            if (empty($deviceIds)) {
                continue;
            }

            // Get hourly consumption for the last 24 hours
            $hourlyData = Reading::whereIn('device_id', $deviceIds)
                ->whereHas('register', function ($query) {
                    $query->where('parameter_name', 'LIKE', '%Power%');
                })
                ->where('timestamp', '>=', now()->subDay())
                ->selectRaw('DATE_FORMAT(timestamp, "%Y-%m-%d %H:00:00") as hour, AVG(value) as avg_consumption')
                ->groupBy('hour')
                ->orderBy('hour')
                ->get();

            $trends[] = [
                'gateway_id' => $gateway->id,
                'gateway_name' => $gateway->name,
                'data' => $hourlyData->map(function ($item) {
                    return [
                        'timestamp' => $item->hour,
                        'consumption' => round($item->avg_consumption, 2),
                    ];
                })->toArray(),
            ];
        }

        return $trends;
    }

    /**
     * Get summary statistics
     */
    private function getSummaryStatistics($authorizedGateways, string $timeRange): array
    {
        if ($authorizedGateways->isEmpty()) {
            return [
                'total_gateways' => 0,
                'total_consumption' => 0,
                'average_consumption_per_gateway' => 0,
                'highest_consumer' => null,
                'most_efficient' => null,
            ];
        }

        $timeFilter = $this->getTimeFilter($timeRange);
        $totalConsumption = 0;
        $gatewayStats = [];

        foreach ($authorizedGateways as $gateway) {
            $consumption = $this->calculateGatewayConsumption($gateway, $timeFilter);
            $efficiency = $this->calculateGatewayEfficiency($gateway, $timeFilter);
            
            $totalConsumption += $consumption['total_kwh'];
            $gatewayStats[] = [
                'gateway_id' => $gateway->id,
                'gateway_name' => $gateway->name,
                'consumption' => $consumption['total_kwh'],
                'efficiency' => $efficiency['efficiency_score'],
            ];
        }

        $averageConsumption = count($gatewayStats) > 0 ? $totalConsumption / count($gatewayStats) : 0;

        // Find highest consumer and most efficient
        $highestConsumer = collect($gatewayStats)->sortByDesc('consumption')->first();
        $mostEfficient = collect($gatewayStats)->sortByDesc('efficiency')->first();

        return [
            'total_gateways' => $authorizedGateways->count(),
            'total_consumption' => round($totalConsumption, 2),
            'average_consumption_per_gateway' => round($averageConsumption, 2),
            'highest_consumer' => $highestConsumer,
            'most_efficient' => $mostEfficient,
        ];
    }

    /**
     * Helper methods
     */
    private function getTimeFilter(string $timeRange): \Carbon\Carbon
    {
        return match($timeRange) {
            '1h' => now()->subHour(),
            '6h' => now()->subHours(6),
            '12h' => now()->subHours(12),
            '24h' => now()->subDay(),
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            default => now()->subDay(),
        };
    }

    private function getHoursFromTimeRange(string $timeRange): float
    {
        return match($timeRange) {
            '1h' => 1,
            '6h' => 6,
            '12h' => 12,
            '24h' => 24,
            '7d' => 168,
            '30d' => 720,
            default => 24,
        };
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

    private function calculateCostEstimate(float $kwh): float
    {
        $costPerKwh = $this->config['cost_per_kwh'] ?? 0.12; // Default $0.12 per kWh
        return round($kwh * $costPerKwh, 2);
    }

    private function calculatePerformanceScore(array $consumption, array $efficiency): float
    {
        // Composite score based on efficiency and consumption stability
        $efficiencyWeight = 0.7;
        $stabilityWeight = 0.3;
        
        $efficiencyScore = $efficiency['efficiency_score'];
        $stabilityScore = $consumption['consumption_pattern'] === 'stable' ? 100 : 
                         ($consumption['consumption_pattern'] === 'variable' ? 70 : 50);
        
        return round(($efficiencyScore * $efficiencyWeight) + ($stabilityScore * $stabilityWeight), 1);
    }

    private function analyzeConsumptionPattern($readings): string
    {
        if ($readings->count() < 10) {
            return 'insufficient_data';
        }

        $values = $readings->pluck('value')->toArray();
        $mean = array_sum($values) / count($values);
        $variance = array_sum(array_map(function($x) use ($mean) { return pow($x - $mean, 2); }, $values)) / count($values);
        $stdDev = sqrt($variance);
        $coefficientOfVariation = $mean > 0 ? ($stdDev / $mean) * 100 : 0;

        if ($coefficientOfVariation < 10) return 'stable';
        if ($coefficientOfVariation < 25) return 'variable';
        return 'highly_variable';
    }

    private function calculateEfficiencyTrend($gateway, $timeFilter): string
    {
        // Compare current efficiency with previous period
        $currentPeriod = $this->calculateGatewayEfficiency($gateway, $timeFilter);
        $previousPeriod = $this->calculateGatewayEfficiency($gateway, $timeFilter->copy()->subDay());
        
        $currentScore = $currentPeriod['efficiency_score'];
        $previousScore = $previousPeriod['efficiency_score'];
        
        if ($currentScore > $previousScore + 5) return 'improving';
        if ($currentScore < $previousScore - 5) return 'declining';
        return 'stable';
    }

    private function getEfficiencyDistribution(array $efficiencyScores): array
    {
        $distribution = [
            'excellent' => 0, // 90-100
            'good' => 0,      // 70-89
            'fair' => 0,      // 50-69
            'poor' => 0,      // 0-49
        ];

        foreach ($efficiencyScores as $score) {
            $efficiency = $score['efficiency_score'];
            if ($efficiency >= 90) $distribution['excellent']++;
            elseif ($efficiency >= 70) $distribution['good']++;
            elseif ($efficiency >= 50) $distribution['fair']++;
            else $distribution['poor']++;
        }

        return $distribution;
    }

    protected function getFallbackData(): array
    {
        return [
            'top_gateways' => [],
            'consumption_comparison' => [],
            'efficiency_metrics' => [
                'average_efficiency' => 0,
                'best_performer' => null,
                'worst_performer' => null,
                'efficiency_distribution' => [],
            ],
            'consumption_trends' => [],
            'summary_statistics' => [
                'total_gateways' => 0,
                'total_consumption' => 0,
                'average_consumption_per_gateway' => 0,
                'highest_consumer' => null,
                'most_efficient' => null,
            ],
        ];
    }
}