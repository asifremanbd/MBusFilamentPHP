<?php

namespace App\Filament\Widgets;

use App\Models\Device;
use App\Models\Gateway;
use App\Models\Reading;
use Filament\Widgets\Widget;
use Illuminate\Contracts\View\View;

class DeviceStatusWidget extends Widget
{
    protected static string $view = 'filament.widgets.device-status-widget';
    protected static ?int $sort = 4;
    protected int | string | array $columnSpan = 'full';
    
    public ?int $gatewayId = null;

    public function mount($gatewayId = null): void
    {
        $this->gatewayId = $gatewayId;
    }

    public function getViewData(): array
    {
        $gateway = $this->gatewayId ? Gateway::find($this->gatewayId) : Gateway::first();
        
        if (!$gateway) {
            return ['devices' => collect()];
        }

        $devices = $gateway->devices()->with(['registers', 'readings' => function ($query) {
            $query->latest()->limit(1);
        }])->get();

        $deviceData = $devices->map(function ($device) {
            // Get latest reading for each parameter
            $latestReadings = $device->readings()
                ->with('register')
                ->orderBy('timestamp', 'desc')
                ->take(5)
                ->get()
                ->groupBy('register.parameter_name')
                ->map(function ($readings) {
                    return $readings->first();
                });

            // Check device status (online if has readings in last 30 minutes)
            $isOnline = $device->readings()
                ->where('timestamp', '>=', now()->subMinutes(30))
                ->exists();

            // Get active alerts count
            $activeAlerts = $device->alerts()
                ->where('resolved', false)
                ->count();

            return [
                'device' => $device,
                'isOnline' => $isOnline,
                'latestReadings' => $latestReadings,
                'activeAlerts' => $activeAlerts,
                'lastReading' => $device->readings()
                    ->latest()
                    ->first(),
            ];
        });

        return [
            'gateway' => $gateway,
            'devices' => $deviceData,
        ];
    }
} 