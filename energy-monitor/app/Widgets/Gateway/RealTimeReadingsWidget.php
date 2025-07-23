<?php

namespace App\Widgets\Gateway;

use App\Widgets\BaseWidget;
use App\Models\Device;
use App\Models\Reading;
use App\Models\Register;
use Illuminate\Support\Facades\DB;

class RealTimeReadingsWidget extends BaseWidget
{
    protected function getData(): array
    {
        $authorizedDevices = $this->permissionService->getAuthorizedDevices($this->user, $this->gatewayId);

        return [
            'live_readings' => $this->getLiveReadings($authorizedDevices),
            'reading_trends' => $this->getReadingTrends($authorizedDevices, '1 hour'),
            'parameter_summaries' => $this->getParameterSummaries($authorizedDevices),
            'data_quality_metrics' => $this->getDataQualityMetrics($authorizedDevices),
            'reading_statistics' => $this->getReadingStatistics($authorizedDevices),
        ];
    }

    protected function getWidgetType(): string
    {
        return 'real-time-readings';
    }

    protected function getWidgetName(): string
    {
        return 'Real-time Readings';
    }

    protected function getWidgetDescription(): string
    {
        return 'Live energy readings and trends from gateway devices';
    }

    protected function getWidgetCategory(): string
    {
        return 'monitoring';
    }

    protected function getWidgetPriority(): int
    {
        return 20;
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
        return 15; // Update every 15 seconds for real-time data
    }

    /**
     * Get live readings from all authorized devices
     */
    private function getLiveReadings($authorizedDevices): array
    {
        if ($authorizedDevices->isEmpty()) {
            return [];
        }

        $deviceIds = $authorizedDevices->pluck('id')->toArray();
        $liveReadings = [];

        // Get the most recent reading for each device and parameter
        $latestReadings = Reading::whereIn('device_id', $deviceIds)
            ->with(['device', 'register'])
            ->where('timestamp', '>=', now()->subMinutes(5))
            ->orderBy('timestamp', 'desc')
            ->get()
            ->groupBy('device_id');

        foreach ($authorizedDevices as $device) {
            $deviceReadings = $latestReadings->get($device->id, collect());
            
            if ($deviceReadings->isEmpty()) {
                continue;
            }

            $readings = [];
            foreach ($deviceReadings as $reading) {
                $readings[] = [
                    'register_id' => $reading->register_id,
                    'parameter_name' => $reading->register->parameter_name ?? 'Unknown',
                    'value' => $reading->value,
                    'unit' => $reading->register->unit ?? '',
                    'timestamp' => $reading->timestamp->toISOString(),
                    'age_seconds' => $reading->timestamp->diffInSeconds(now()),
                    'quality_score' => $this->calculateReadingQuality($reading),
                    'trend' => $this->calculateReadingTrend($reading),
                ];
            }

            $liveReadings[] = [
                'device_id' => $device->id,
                'device_name' => $device->name,
                'slave_id' => $device->slave_id,
                'location_tag' => $device->location_tag,
                'readings' => $readings,
                'last_update' => $deviceReadings->first()->timestamp->toISOString(),
                'reading_count' => count($readings),
                'status' => $this->getDeviceReadingStatus($deviceReadings),
            ];
        }

        // Sort by most recent readings first
        usort($liveReadings, function ($a, $b) {
            return strtotime($b['last_update']) <=> strtotime($a['last_update']);
        });

        return $liveReadings;
    }

    /**
     * Get reading trends over specified time period
     */
    private function getReadingTrends($authorizedDevices, string $timeRange): array
    {
        if ($authorizedDevices->isEmpty()) {
            return [];
        }

        $deviceIds = $authorizedDevices->pluck('id')->toArray();
        $timeFilter = $this->getTimeFilter($timeRange);
        $trends = [];

        // Get trending parameters (most commonly read parameters)
        $trendingParameters = Reading::whereIn('device_id', $deviceIds)
            ->where('timestamp', '>=', $timeFilter)
            ->join('registers', 'readings.register_id', '=', 'registers.id')
            ->selectRaw('registers.parameter_name, COUNT(*) as reading_count')
            ->groupBy('registers.parameter_name')
            ->orderByDesc('reading_count')
            ->limit(10)
            ->get();

        foreach ($trendingParameters as $param) {
            $parameterName = $param->parameter_name;
            
            // Get time-series data for this parameter
            $timeSeriesData = Reading::whereIn('device_id', $deviceIds)
                ->whereHas('register', function ($query) use ($parameterName) {
                    $query->where('parameter_name', $parameterName);
                })
                ->where('timestamp', '>=', $timeFilter)
                ->selectRaw('
                    DATE_FORMAT(timestamp, "%Y-%m-%d %H:%i:00") as time_bucket,
                    AVG(value) as avg_value,
                    MIN(value) as min_value,
                    MAX(value) as max_value,
                    COUNT(*) as reading_count
                ')
                ->groupBy('time_bucket')
                ->orderBy('time_bucket')
                ->get();

            if ($timeSeriesData->isNotEmpty()) {
                $trends[] = [
                    'parameter_name' => $parameterName,
                    'unit' => $this->getParameterUnit($parameterName, $deviceIds),
                    'data_points' => $timeSeriesData->map(function ($item) {
                        return [
                            'timestamp' => $item->time_bucket,
                            'avg_value' => round($item->avg_value, 2),
                            'min_value' => round($item->min_value, 2),
                            'max_value' => round($item->max_value, 2),
                            'reading_count' => $item->reading_count,
                        ];
                    })->toArray(),
                    'trend_direction' => $this->calculateTrendDirection($timeSeriesData),
                    'volatility' => $this->calculateVolatility($timeSeriesData),
                ];
            }
        }

        return $trends;
    }

    /**
     * Get parameter summaries across all devices
     */
    private function getParameterSummaries($authorizedDevices): array
    {
        if ($authorizedDevices->isEmpty()) {
            return [];
        }

        $deviceIds = $authorizedDevices->pluck('id')->toArray();
        $summaries = [];

        // Get all unique parameters from recent readings
        $parameters = Reading::whereIn('device_id', $deviceIds)
            ->where('timestamp', '>=', now()->subHour())
            ->join('registers', 'readings.register_id', '=', 'registers.id')
            ->selectRaw('
                registers.parameter_name,
                registers.unit,
                COUNT(*) as reading_count,
                AVG(readings.value) as avg_value,
                MIN(readings.value) as min_value,
                MAX(readings.value) as max_value,
                STDDEV(readings.value) as std_dev
            ')
            ->groupBy('registers.parameter_name', 'registers.unit')
            ->orderBy('reading_count', 'desc')
            ->get();

        foreach ($parameters as $param) {
            $summaries[] = [
                'parameter_name' => $param->parameter_name,
                'unit' => $param->unit ?? '',
                'reading_count' => $param->reading_count,
                'statistics' => [
                    'average' => round($param->avg_value, 2),
                    'minimum' => round($param->min_value, 2),
                    'maximum' => round($param->max_value, 2),
                    'standard_deviation' => round($param->std_dev ?? 0, 2),
                    'range' => round($param->max_value - $param->min_value, 2),
                ],
                'device_count' => $this->getDeviceCountForParameter($param->parameter_name, $deviceIds),
                'data_quality' => $this->getParameterDataQuality($param->parameter_name, $deviceIds),
                'last_reading' => $this->getLastReadingForParameter($param->parameter_name, $deviceIds),
            ];
        }

        return $summaries;
    }

    /**
     * Get data quality metrics
     */
    private function getDataQualityMetrics($authorizedDevices): array
    {
        if ($authorizedDevices->isEmpty()) {
            return [
                'overall_quality_score' => 0,
                'completeness_percentage' => 0,
                'accuracy_score' => 0,
                'timeliness_score' => 0,
                'consistency_score' => 0,
            ];
        }

        $deviceIds = $authorizedDevices->pluck('id')->toArray();
        
        // Get recent readings for analysis
        $recentReadings = Reading::whereIn('device_id', $deviceIds)
            ->where('timestamp', '>=', now()->subHour())
            ->with('register')
            ->get();

        if ($recentReadings->isEmpty()) {
            return [
                'overall_quality_score' => 0,
                'completeness_percentage' => 0,
                'accuracy_score' => 0,
                'timeliness_score' => 0,
                'consistency_score' => 0,
            ];
        }

        // Calculate completeness (percentage of expected readings received)
        $expectedReadings = $authorizedDevices->count() * 12; // Assuming 12 readings per hour per device
        $actualReadings = $recentReadings->count();
        $completeness = min(100, ($actualReadings / $expectedReadings) * 100);

        // Calculate accuracy (percentage of valid readings)
        $validReadings = $recentReadings->filter(function ($reading) {
            return $reading->value !== null && $reading->value >= 0;
        })->count();
        $accuracy = $actualReadings > 0 ? ($validReadings / $actualReadings) * 100 : 0;

        // Calculate timeliness (percentage of recent readings)
        $timelyReadings = $recentReadings->filter(function ($reading) {
            return $reading->timestamp->gt(now()->subMinutes(10));
        })->count();
        $timeliness = $actualReadings > 0 ? ($timelyReadings / $actualReadings) * 100 : 0;

        // Calculate consistency (low variance in reading intervals)
        $consistency = $this->calculateReadingConsistency($recentReadings);

        $overallScore = ($completeness * 0.3) + ($accuracy * 0.3) + ($timeliness * 0.2) + ($consistency * 0.2);

        return [
            'overall_quality_score' => round($overallScore, 1),
            'completeness_percentage' => round($completeness, 1),
            'accuracy_score' => round($accuracy, 1),
            'timeliness_score' => round($timeliness, 1),
            'consistency_score' => round($consistency, 1),
        ];
    }

    /**
     * Get reading statistics
     */
    private function getReadingStatistics($authorizedDevices): array
    {
        if ($authorizedDevices->isEmpty()) {
            return [
                'total_readings_today' => 0,
                'readings_per_hour' => 0,
                'active_parameters' => 0,
                'data_coverage' => 0,
            ];
        }

        $deviceIds = $authorizedDevices->pluck('id')->toArray();

        // Total readings today
        $totalReadingsToday = Reading::whereIn('device_id', $deviceIds)
            ->whereDate('timestamp', today())
            ->count();

        // Readings per hour (last 24 hours)
        $readingsPerHour = Reading::whereIn('device_id', $deviceIds)
            ->where('timestamp', '>=', now()->subDay())
            ->count() / 24;

        // Active parameters (parameters with readings in last hour)
        $activeParameters = Reading::whereIn('device_id', $deviceIds)
            ->where('timestamp', '>=', now()->subHour())
            ->distinct('register_id')
            ->count('register_id');

        // Data coverage (percentage of devices with recent data)
        $devicesWithRecentData = Reading::whereIn('device_id', $deviceIds)
            ->where('timestamp', '>=', now()->subMinutes(30))
            ->distinct('device_id')
            ->count('device_id');
        $dataCoverage = $authorizedDevices->count() > 0 ? ($devicesWithRecentData / $authorizedDevices->count()) * 100 : 0;

        return [
            'total_readings_today' => $totalReadingsToday,
            'readings_per_hour' => round($readingsPerHour, 1),
            'active_parameters' => $activeParameters,
            'data_coverage' => round($dataCoverage, 1),
        ];
    }

    /**
     * Helper methods
     */
    private function getTimeFilter(string $timeRange): \Carbon\Carbon
    {
        return match($timeRange) {
            '15 minutes' => now()->subMinutes(15),
            '30 minutes' => now()->subMinutes(30),
            '1 hour' => now()->subHour(),
            '6 hours' => now()->subHours(6),
            '24 hours' => now()->subDay(),
            default => now()->subHour(),
        };
    }

    private function calculateReadingQuality($reading): float
    {
        $score = 100;

        // Deduct for null values
        if ($reading->value === null) {
            return 0;
        }

        // Deduct for negative values (if inappropriate)
        if ($reading->value < 0 && !$this->allowsNegativeValues($reading->register->parameter_name ?? '')) {
            $score -= 30;
        }

        // Deduct for age
        $ageMinutes = $reading->timestamp->diffInMinutes(now());
        if ($ageMinutes > 5) {
            $score -= min(50, $ageMinutes * 2);
        }

        return max(0, $score);
    }

    private function calculateReadingTrend($reading): string
    {
        // Get previous reading for comparison
        $previousReading = Reading::where('device_id', $reading->device_id)
            ->where('register_id', $reading->register_id)
            ->where('timestamp', '<', $reading->timestamp)
            ->orderBy('timestamp', 'desc')
            ->first();

        if (!$previousReading) {
            return 'stable';
        }

        $change = $reading->value - $previousReading->value;
        $changePercent = $previousReading->value != 0 ? abs($change / $previousReading->value) * 100 : 0;

        if ($changePercent < 5) return 'stable';
        return $change > 0 ? 'increasing' : 'decreasing';
    }

    private function getDeviceReadingStatus($readings): string
    {
        if ($readings->isEmpty()) {
            return 'no_data';
        }

        $latestReading = $readings->first();
        $ageMinutes = $latestReading->timestamp->diffInMinutes(now());

        if ($ageMinutes <= 2) return 'live';
        if ($ageMinutes <= 5) return 'recent';
        if ($ageMinutes <= 15) return 'delayed';
        return 'stale';
    }

    private function getParameterUnit(string $parameterName, array $deviceIds): string
    {
        $register = Register::whereIn('device_id', $deviceIds)
            ->where('parameter_name', $parameterName)
            ->first();

        return $register->unit ?? '';
    }

    private function calculateTrendDirection($timeSeriesData): string
    {
        if ($timeSeriesData->count() < 2) {
            return 'stable';
        }

        $first = $timeSeriesData->first()->avg_value;
        $last = $timeSeriesData->last()->avg_value;
        $change = (($last - $first) / $first) * 100;

        if (abs($change) < 5) return 'stable';
        return $change > 0 ? 'increasing' : 'decreasing';
    }

    private function calculateVolatility($timeSeriesData): float
    {
        if ($timeSeriesData->count() < 2) {
            return 0;
        }

        $values = $timeSeriesData->pluck('avg_value')->toArray();
        $mean = array_sum($values) / count($values);
        $variance = array_sum(array_map(function($x) use ($mean) { return pow($x - $mean, 2); }, $values)) / count($values);
        
        return round(sqrt($variance), 2);
    }

    private function getDeviceCountForParameter(string $parameterName, array $deviceIds): int
    {
        return Register::whereIn('device_id', $deviceIds)
            ->where('parameter_name', $parameterName)
            ->distinct('device_id')
            ->count('device_id');
    }

    private function getParameterDataQuality(string $parameterName, array $deviceIds): float
    {
        $readings = Reading::whereIn('device_id', $deviceIds)
            ->whereHas('register', function ($query) use ($parameterName) {
                $query->where('parameter_name', $parameterName);
            })
            ->where('timestamp', '>=', now()->subHour())
            ->get();

        if ($readings->isEmpty()) {
            return 0;
        }

        $validReadings = $readings->filter(function ($reading) {
            return $reading->value !== null && $reading->value >= 0;
        })->count();

        return ($validReadings / $readings->count()) * 100;
    }

    private function getLastReadingForParameter(string $parameterName, array $deviceIds): ?string
    {
        $lastReading = Reading::whereIn('device_id', $deviceIds)
            ->whereHas('register', function ($query) use ($parameterName) {
                $query->where('parameter_name', $parameterName);
            })
            ->orderBy('timestamp', 'desc')
            ->first();

        return $lastReading?->timestamp->toISOString();
    }

    private function calculateReadingConsistency($readings): float
    {
        if ($readings->count() < 3) {
            return 100;
        }

        // Group by device and calculate interval consistency
        $deviceReadings = $readings->groupBy('device_id');
        $consistencyScores = [];

        foreach ($deviceReadings as $deviceId => $deviceReadingGroup) {
            $sortedReadings = $deviceReadingGroup->sortBy('timestamp');
            $intervals = [];

            $previous = null;
            foreach ($sortedReadings as $reading) {
                if ($previous) {
                    $intervals[] = $reading->timestamp->diffInSeconds($previous->timestamp);
                }
                $previous = $reading;
            }

            if (count($intervals) > 1) {
                $avgInterval = array_sum($intervals) / count($intervals);
                $variance = array_sum(array_map(function($x) use ($avgInterval) { 
                    return pow($x - $avgInterval, 2); 
                }, $intervals)) / count($intervals);
                
                $coefficientOfVariation = $avgInterval > 0 ? (sqrt($variance) / $avgInterval) * 100 : 0;
                $consistencyScores[] = max(0, 100 - $coefficientOfVariation);
            }
        }

        return count($consistencyScores) > 0 ? array_sum($consistencyScores) / count($consistencyScores) : 100;
    }

    private function allowsNegativeValues(string $parameterName): bool
    {
        $negativeAllowedParams = ['temperature', 'current', 'power_factor'];
        
        foreach ($negativeAllowedParams as $param) {
            if (stripos($parameterName, $param) !== false) {
                return true;
            }
        }
        
        return false;
    }

    protected function getFallbackData(): array
    {
        return [
            'live_readings' => [],
            'reading_trends' => [],
            'parameter_summaries' => [],
            'data_quality_metrics' => [
                'overall_quality_score' => 0,
                'completeness_percentage' => 0,
                'accuracy_score' => 0,
                'timeliness_score' => 0,
                'consistency_score' => 0,
            ],
            'reading_statistics' => [
                'total_readings_today' => 0,
                'readings_per_hour' => 0,
                'active_parameters' => 0,
                'data_coverage' => 0,
            ],
        ];
    }
}