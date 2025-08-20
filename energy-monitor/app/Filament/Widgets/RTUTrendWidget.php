<?php

namespace App\Filament\Widgets;

use App\Models\Gateway;
use App\Services\RTUDataService;
use Filament\Widgets\Widget;
use Illuminate\Contracts\View\View;

class RTUTrendWidget extends Widget
{
    protected static string $view = 'filament.widgets.rtu-trend-widget';
    protected static ?int $sort = 5;
    protected int | string | array $columnSpan = 'full';
    
    public ?Gateway $gateway = null;
    public array $selectedMetrics = [];
    public string $timeRange = '24h';

    public function mount(?Gateway $gateway = null, array $selectedMetrics = [], string $timeRange = '24h'): void
    {
        $this->gateway = $gateway;
        
        // Load user preferences if no explicit parameters provided
        if (empty($selectedMetrics) || $timeRange === '24h') {
            $this->loadUserPreferences();
        } else {
            $this->selectedMetrics = $selectedMetrics;
            $this->timeRange = $timeRange;
        }
    }

    /**
     * Load user preferences for this gateway
     */
    protected function loadUserPreferences(): void
    {
        if (!$this->gateway || !auth()->check()) {
            $this->selectedMetrics = ['signal_strength'];
            $this->timeRange = '24h';
            return;
        }

        $configService = app(\App\Services\RTUDashboardConfigService::class);
        $preferences = $configService->getTrendPreferences(auth()->user(), $this->gateway);
        
        $this->selectedMetrics = $preferences->selected_metrics ?? ['signal_strength'];
        $this->timeRange = $preferences->time_range ?? '24h';
    }

    public function getData(): array
    {
        if (!$this->gateway || !$this->gateway->isRTUGateway()) {
            return [
                'error' => 'Invalid or non-RTU gateway',
                'has_data' => false,
                'available_metrics' => [],
                'chart_data' => [],
                'message' => 'RTU gateway required for trend visualization'
            ];
        }

        $rtuDataService = app(RTUDataService::class);
        $trendData = $rtuDataService->getTrendData($this->gateway, $this->timeRange);
        
        if (!$trendData['has_data']) {
            return [
                'has_data' => false,
                'message' => $trendData['message'] ?? 'No data available for selected period',
                'available_metrics' => $trendData['available_metrics'] ?? [],
                'chart_data' => [],
                'time_range' => $this->timeRange,
                'selected_metrics' => $this->selectedMetrics
            ];
        }

        // Determine which metrics to display
        $metricsToShow = $this->determineMetricsToShow($trendData['available_metrics']);
        
        // Prepare chart data for selected metrics
        $chartData = $this->prepareChartData($trendData['metrics'], $metricsToShow);
        
        return [
            'has_data' => true,
            'available_metrics' => $trendData['available_metrics'],
            'selected_metrics' => $metricsToShow,
            'chart_data' => $chartData,
            'time_range' => $this->timeRange,
            'start_time' => $trendData['start_time'],
            'end_time' => $trendData['end_time'],
            'metric_configs' => $this->getMetricConfigurations(),
            'chart_options' => $this->getChartOptions()
        ];
    }

    /**
     * Determine which metrics to show based on selection and availability
     */
    public function determineMetricsToShow(array $availableMetrics): array
    {
        // If no metrics selected, use default fallback logic
        if (empty($this->selectedMetrics)) {
            return $this->getDefaultMetrics($availableMetrics);
        }

        // Filter selected metrics to only include available ones
        $validMetrics = array_intersect($this->selectedMetrics, $availableMetrics);
        
        // If none of the selected metrics are available, fall back to defaults
        if (empty($validMetrics)) {
            return $this->getDefaultMetrics($availableMetrics);
        }

        return $validMetrics;
    }

    /**
     * Get default metrics to display when no selection is made
     */
    public function getDefaultMetrics(array $availableMetrics): array
    {
        // Priority order for default metrics
        $defaultPriority = ['signal_strength', 'cpu_load', 'memory_usage', 'analog_input'];
        
        foreach ($defaultPriority as $metric) {
            if (in_array($metric, $availableMetrics)) {
                return [$metric]; // Show only the first available metric as default
            }
        }
        
        // If none of the priority metrics are available, return the first available metric
        return !empty($availableMetrics) ? [reset($availableMetrics)] : [];
    }

    /**
     * Prepare chart data for multiple metrics with different scales
     */
    public function prepareChartData(array $metricsData, array $metricsToShow): array
    {
        $chartSeries = [];
        $timestamps = [];
        
        foreach ($metricsToShow as $metricKey) {
            if (!isset($metricsData[$metricKey]) || empty($metricsData[$metricKey])) {
                continue;
            }
            
            $metricConfig = $this->getMetricConfiguration($metricKey);
            $seriesData = [];
            
            foreach ($metricsData[$metricKey] as $dataPoint) {
                $timestamp = $dataPoint['timestamp'];
                $value = $dataPoint['value'];
                
                $timestamps[] = $timestamp;
                $seriesData[] = [
                    'x' => $timestamp,
                    'y' => $value
                ];
            }
            
            if (!empty($seriesData)) {
                $chartSeries[] = [
                    'name' => $metricConfig['label'],
                    'data' => $seriesData,
                    'color' => $metricConfig['color'],
                    'yAxis' => $metricConfig['yAxis'],
                    'unit' => $metricConfig['unit'],
                    'type' => 'line'
                ];
            }
        }
        
        // Sort timestamps and remove duplicates
        $timestamps = array_unique($timestamps);
        sort($timestamps);
        
        return [
            'series' => $chartSeries,
            'timestamps' => $timestamps,
            'has_multiple_metrics' => count($chartSeries) > 1
        ];
    }

    /**
     * Get configuration for all supported metrics
     */
    public function getMetricConfigurations(): array
    {
        return [
            'signal_strength' => [
                'label' => 'Signal Strength',
                'unit' => 'dBm',
                'color' => '#10B981', // Green
                'yAxis' => 0,
                'min' => -120,
                'max' => -30,
                'icon' => 'heroicon-o-signal'
            ],
            'cpu_load' => [
                'label' => 'CPU Load',
                'unit' => '%',
                'color' => '#F59E0B', // Amber
                'yAxis' => 1,
                'min' => 0,
                'max' => 100,
                'icon' => 'heroicon-o-cpu-chip'
            ],
            'memory_usage' => [
                'label' => 'Memory Usage',
                'unit' => '%',
                'color' => '#EF4444', // Red
                'yAxis' => 1,
                'min' => 0,
                'max' => 100,
                'icon' => 'heroicon-o-server'
            ],
            'analog_input' => [
                'label' => 'Analog Input',
                'unit' => 'V',
                'color' => '#8B5CF6', // Purple
                'yAxis' => 2,
                'min' => 0,
                'max' => 10,
                'icon' => 'heroicon-o-lightning-bolt'
            ]
        ];
    }

    /**
     * Get configuration for a specific metric
     */
    public function getMetricConfiguration(string $metricKey): array
    {
        $configs = $this->getMetricConfigurations();
        return $configs[$metricKey] ?? [
            'label' => ucfirst(str_replace('_', ' ', $metricKey)),
            'unit' => '',
            'color' => '#6B7280',
            'yAxis' => 0,
            'icon' => 'heroicon-o-chart-bar'
        ];
    }

    /**
     * Get chart configuration options
     */
    public function getChartOptions(): array
    {
        return [
            'chart' => [
                'type' => 'line',
                'height' => 350,
                'animations' => [
                    'enabled' => true,
                    'easing' => 'easeinout',
                    'speed' => 800
                ],
                'toolbar' => [
                    'show' => true,
                    'tools' => [
                        'download' => true,
                        'selection' => true,
                        'zoom' => true,
                        'zoomin' => true,
                        'zoomout' => true,
                        'pan' => true,
                        'reset' => true
                    ]
                ]
            ],
            'stroke' => [
                'curve' => 'smooth',
                'width' => 2
            ],
            'markers' => [
                'size' => 4,
                'hover' => [
                    'size' => 6
                ]
            ],
            'grid' => [
                'show' => true,
                'borderColor' => '#E5E7EB',
                'strokeDashArray' => 3
            ],
            'legend' => [
                'show' => true,
                'position' => 'top',
                'horizontalAlign' => 'left'
            ],
            'tooltip' => [
                'enabled' => true,
                'shared' => true,
                'intersect' => false,
                'x' => [
                    'format' => 'dd MMM yyyy HH:mm'
                ]
            ],
            'xaxis' => [
                'type' => 'datetime',
                'labels' => [
                    'format' => 'HH:mm'
                ]
            ]
        ];
    }

    /**
     * Update selected metrics (for AJAX calls)
     */
    public function updateSelectedMetrics(array $metrics): void
    {
        $this->selectedMetrics = $metrics;
        $this->dispatch('refresh-chart');
    }

    /**
     * Update time range (for AJAX calls)
     */
    public function updateTimeRange(string $timeRange): void
    {
        $this->timeRange = $timeRange;
        $this->dispatch('refresh-chart');
    }

    /**
     * Get available time range options
     */
    public function getTimeRangeOptions(): array
    {
        return [
            '1h' => '1 Hour',
            '6h' => '6 Hours',
            '24h' => '24 Hours',
            '7d' => '7 Days',
            '30d' => '30 Days'
        ];
    }

    /**
     * Check if widget should be hidden due to no data
     */
    public function shouldHide(): bool
    {
        $data = $this->getData();
        return !$data['has_data'] && empty($data['available_metrics']);
    }

    /**
     * Get CSS classes for metric status indicators
     */
    public function getMetricStatusClass(string $metricKey, $value): string
    {
        switch ($metricKey) {
            case 'signal_strength':
                if ($value > -70) return 'text-green-600';
                if ($value > -85) return 'text-yellow-600';
                return 'text-red-600';
                
            case 'cpu_load':
                if ($value < 60) return 'text-green-600';
                if ($value < 80) return 'text-yellow-600';
                return 'text-red-600';
                
            case 'memory_usage':
                if ($value < 70) return 'text-green-600';
                if ($value < 85) return 'text-yellow-600';
                return 'text-red-600';
                
            case 'analog_input':
                return 'text-blue-600';
                
            default:
                return 'text-gray-600';
        }
    }

    /**
     * Format metric value for display
     */
    public function formatMetricValue(string $metricKey, $value): string
    {
        if ($value === null) {
            return 'N/A';
        }
        
        $config = $this->getMetricConfiguration($metricKey);
        
        switch ($metricKey) {
            case 'signal_strength':
                return number_format($value, 0) . ' ' . $config['unit'];
                
            case 'cpu_load':
            case 'memory_usage':
                return number_format($value, 1) . $config['unit'];
                
            case 'analog_input':
                return number_format($value, 2) . ' ' . $config['unit'];
                
            default:
                return number_format($value, 2) . ' ' . $config['unit'];
        }
    }
}