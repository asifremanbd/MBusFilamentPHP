<?php

namespace App\Filament\Widgets;

use App\Models\Gateway;
use App\Models\Device;
use App\Models\Alert;
use App\Models\Reading;
use App\Services\AlertService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class GatewayStatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;
    
    public ?int $gatewayId = null;

    public function mount($gatewayId = null): void
    {
        $this->gatewayId = $gatewayId;
    }

    protected function getStats(): array
    {
        $gateway = $this->gatewayId ? Gateway::find($this->gatewayId) : Gateway::first();
        
        if (!$gateway) {
            return [
                Stat::make('No Gateway Selected', '0')
                    ->description('Please select a gateway to view statistics')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('warning'),
            ];
        }
        
        // Get device count for this gateway
        $deviceCount = $gateway->devices()->count();
        
        // Get active devices (devices with recent readings in last hour)
        $activeDevices = $gateway->devices()
            ->whereHas('readings', function ($query) {
                $query->where('timestamp', '>=', now()->subHour());
            })
            ->count();
        
        // Get active alerts for this gateway
        $gatewayAlerts = Alert::whereIn('device_id', $gateway->devices()->pluck('id'))
            ->where('resolved', false)
            ->count();

        // Get signal strength with fallback
        $signalStrength = $gateway->gsm_signal ?? -999;
        $signalDisplay = $signalStrength === -999 ? 'Unknown' : $signalStrength . ' dBm';

        return [
            Stat::make('Communication Status', $this->getGatewayStatus($gateway))
                ->description($gateway->name)
                ->descriptionIcon('heroicon-o-signal')
                ->color($this->getGatewayStatusColor($gateway))
                ->icon('heroicon-o-wifi'),

            Stat::make('Connected Devices', $deviceCount)
                ->description($activeDevices . ' active in last hour')
                ->descriptionIcon('heroicon-o-device-phone-mobile')
                ->chart($this->getDeviceActivityChart($gateway))
                ->color($activeDevices === $deviceCount ? 'success' : 'warning'),

            Stat::make('Active Alerts', $gatewayAlerts)
                ->description('Unresolved alerts')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color($gatewayAlerts > 0 ? ($gatewayAlerts > 5 ? 'danger' : 'warning') : 'success'),

            Stat::make('Signal Strength', $signalDisplay)
                ->description('GSM Signal Quality')
                ->descriptionIcon('heroicon-o-signal')
                ->color($this->getSignalColor($signalStrength)),
        ];
    }

    private function getGatewayStatus(Gateway $gateway): string
    {
        // Check if gateway has recent readings (within last 30 minutes)
        $hasRecentData = $gateway->devices()
            ->whereHas('readings', function ($query) {
                $query->where('timestamp', '>=', now()->subMinutes(30));
            })
            ->exists();

        return $hasRecentData ? 'Online' : 'Offline';
    }

    private function getGatewayStatusColor(Gateway $gateway): string
    {
        $status = $this->getGatewayStatus($gateway);
        return $status === 'Online' ? 'success' : 'danger';
    }

    private function getSignalColor(?int $signal): string
    {
        if (!$signal) return 'gray';
        
        return match (true) {
            $signal >= -70 => 'success',
            $signal >= -85 => 'warning',
            default => 'danger',
        };
    }

    private function getDeviceActivityChart(Gateway $gateway): array
    {
        // Get last 7 days of device activity
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $activeCount = $gateway->devices()
                ->whereHas('readings', function ($query) use ($date) {
                    $query->whereDate('timestamp', $date);
                })
                ->count();
            $data[] = $activeCount;
        }
        
        return $data;
    }

    private function getReadingsChart(Gateway $gateway): array
    {
        // Get last 7 days of readings count
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $readingsCount = Reading::whereIn('device_id', $gateway->devices()->pluck('id'))
                ->whereDate('timestamp', $date)
                ->count();
            $data[] = $readingsCount;
        }
        
        return $data;
    }
}
