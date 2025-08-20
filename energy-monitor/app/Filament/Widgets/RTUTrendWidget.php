<?php

namespace App\Filament\Widgets;

use App\Models\Gateway;
use App\Services\RTUDataService;
use Livewire\Component;
use Illuminate\Contracts\View\View;

class RTUTrendWidget extends Component
{
    protected string $view = 'filament.widgets.rtu-trend-widget';
    
    public ?Gateway $gateway = null;
    public array $selectedMetrics = ['signal_strength'];
    public string $timeRange = '24h';

    public function mount(?Gateway $gateway = null): void
    {
        $this->gateway = $gateway;
    }

    public function render(): View
    {
        return view($this->view, [
            'data' => $this->getData(),
            'gateway' => $this->gateway
        ]);
    }

    public function getData(): array
    {
        if (!$this->gateway) {
            return $this->getEmptyData();
        }

        $rtuDataService = app(RTUDataService::class);
        $trendData = $rtuDataService->getTrendData($this->gateway, $this->timeRange);
        
        if (!$trendData['has_data']) {
            return [
                'has_data' => false,
                'message' => $trendData['message'] ?? 'No data available for selected period',
                'available_metrics' => [],
                'selected_metrics' => $this->selectedMetrics,
                'time_range' => $this->timeRange,
                'gateway' => $this->gateway
            ];
        }

        return [
            'has_data' => true,
            'time_range' => $trendData['time_range'],
            'start_time' => $trendData['start_time'],
            'end_time' => $trendData['end_time'],
            'metrics' => $trendData['metrics'],
            'available_metrics' => $trendData['available_metrics'],
            'selected_metrics' => $this->selectedMetrics,
            'chart_data' => $this->prepareChartData($trendData['metrics']),
            'gateway' => $this->gateway
        ];
    }

    protected function getEmptyData(): array
    {
        return [
            'has_data' => false,
            'message' => 'No Gateway Selected',
            'available_metrics' => [],
            'selected_metrics' => [],
            'time_range' => '24h',
            'gateway' => null
        ];
    }

    protected function prepareChartData(array $metrics): array
    {
        $chartData = [];
        
        foreach ($this->selectedMetrics as $metric) {
            if (isset($metrics[$metric]) && !empty($metrics[$metric])) {
                $chartData[$metric] = [
                    'label' => $this->getMetricLabel($metric),
                    'data' => $metrics[$metric],
                    'color' => $this->getMetricColor($metric),
                    'unit' => $this->getMetricUnit($metric)
                ];
            }
        }
        
        return $chartData;
    }

    protected function getMetricLabel(string $metric): string
    {
        return match($metric) {
            'signal_strength' => 'Signal Strength (RSSI)',
            'cpu_load' => 'CPU Load',
            'memory_usage' => 'Memory Usage',
            'analog_input' => 'Analog Input',
            default => ucfirst(str_replace('_', ' ', $metric))
        };
    }

    protected function getMetricColor(string $metric): string
    {
        return match($metric) {
            'signal_strength' => '#3B82F6', // Blue
            'cpu_load' => '#EF4444', // Red
            'memory_usage' => '#F59E0B', // Amber
            'analog_input' => '#10B981', // Emerald
            default => '#6B7280' // Gray
        };
    }

    protected function getMetricUnit(string $metric): string
    {
        return match($metric) {
            'signal_strength' => 'dBm',
            'cpu_load' => '%',
            'memory_usage' => '%',
            'analog_input' => 'V',
            default => ''
        };
    }

    public function updateSelectedMetrics(array $metrics): void
    {
        $this->selectedMetrics = array_intersect($metrics, ['signal_strength', 'cpu_load', 'memory_usage', 'analog_input']);
        
        if (empty($this->selectedMetrics)) {
            $this->selectedMetrics = ['signal_strength']; // Default fallback
        }
    }

    public function updateTimeRange(string $timeRange): void
    {
        if (in_array($timeRange, ['1h', '6h', '24h', '7d'])) {
            $this->timeRange = $timeRange;
        }
    }

    public function getAvailableMetrics(): array
    {
        return [
            'signal_strength' => 'Signal Strength (RSSI)',
            'cpu_load' => 'CPU Load (%)',
            'memory_usage' => 'Memory Usage (%)',
            'analog_input' => 'Analog Input (V)'
        ];
    }

    public function getTimeRangeOptions(): array
    {
        return [
            '1h' => '1 Hour',
            '6h' => '6 Hours',
            '24h' => '24 Hours',
            '7d' => '7 Days'
        ];
    }
}