<?php

namespace App\Filament\Widgets;

use App\Models\Reading;
use App\Models\Device;
use App\Models\Gateway;
use Filament\Widgets\ChartWidget;
use Carbon\Carbon;

class ReadingsChartWidget extends ChartWidget
{
    protected static ?string $heading = '24-Hour Readings Trend';
    protected static ?int $sort = 3;
    
    public ?string $filter = 'voltage';
    public ?int $gatewayId = null;
    
    // Initialize ChartWidget properties
    public string $dataChecksum = '';

    public function mount($gatewayId = null): void
    {
        $this->gatewayId = $gatewayId;
    }

    protected function getData(): array
    {
        $gateway = $this->gatewayId ? Gateway::find($this->gatewayId) : Gateway::first();
        
        if (!$gateway) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        // Get the last 24 hours of data
        $start = Carbon::now()->subHours(24);
        $end = Carbon::now();

        // Get readings for devices in this gateway
        $deviceIds = $gateway->devices()->pluck('id');
        
        $readings = Reading::whereIn('device_id', $deviceIds)
            ->whereHas('register', function ($query) {
                $query->where('parameter_name', 'like', '%' . $this->filter . '%');
            })
            ->whereBetween('timestamp', [$start, $end])
            ->with(['register', 'device'])
            ->orderBy('timestamp')
            ->get();

        // Group by device and create datasets
        $datasets = [];
        $labels = [];
        $colors = ['#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6', '#06b6d4'];
        $colorIndex = 0;

        // Create hourly labels
        for ($i = 23; $i >= 0; $i--) {
            $labels[] = Carbon::now()->subHours($i)->format('H:i');
        }

        $groupedReadings = $readings->groupBy('device_id');

        foreach ($groupedReadings as $deviceId => $deviceReadings) {
            $device = $deviceReadings->first()->device;
            $register = $deviceReadings->first()->register;
            
            // Create hourly averages
            $hourlyData = [];
            for ($i = 23; $i >= 0; $i--) {
                $hourStart = Carbon::now()->subHours($i);
                $hourEnd = Carbon::now()->subHours($i - 1);
                
                $hourReadings = $deviceReadings->filter(function ($reading) use ($hourStart, $hourEnd) {
                    return $reading->timestamp >= $hourStart && $reading->timestamp < $hourEnd;
                });
                
                $avg = $hourReadings->avg('value');
                $hourlyData[] = $avg ? round($avg, 2) : null;
            }

            $datasets[] = [
                'label' => $device->name . ' (' . $register->unit . ')',
                'data' => $hourlyData,
                'borderColor' => $colors[$colorIndex % count($colors)],
                'backgroundColor' => $colors[$colorIndex % count($colors)] . '20',
                'fill' => false,
                'tension' => 0.4,
            ];
            
            $colorIndex++;
        }

        return [
            'datasets' => $datasets,
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getFilters(): ?array
    {
        return [
            'voltage' => 'Voltage',
            'current' => 'Current',
            'power' => 'Power',
            'energy' => 'Energy',
            'temperature' => 'Temperature',
            'flow' => 'Flow',
        ];
    }

    protected function getOptions(): ?array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => false,
                    'title' => [
                        'display' => true,
                        'text' => 'Value',
                    ],
                ],
                'x' => [
                    'title' => [
                        'display' => true,
                        'text' => 'Time (Last 24 Hours)',
                    ],
                ],
            ],
            'interaction' => [
                'intersect' => false,
                'mode' => 'index',
            ],
        ];
    }
} 