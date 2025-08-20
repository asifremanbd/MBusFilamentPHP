<?php

namespace App\Filament\Widgets;

use App\Models\Gateway;
use App\Services\RTUDataService;
use Livewire\Component;
use Illuminate\Contracts\View\View;

class RTUNetworkStatusWidget extends Component
{
    protected string $view = 'filament.widgets.rtu-network-status-widget';
    
    public ?Gateway $gateway = null;

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
        $networkStatus = $rtuDataService->getNetworkStatus($this->gateway);
        
        return [
            'wan_connection' => [
                'ip_address' => $networkStatus['wan_ip'] ?? 'Not assigned',
                'status' => $networkStatus['connection_status'] ?? 'unknown',
                'icon' => 'heroicon-o-globe-alt'
            ],
            'sim_details' => [
                'iccid' => $networkStatus['sim_iccid'] ?? 'Unknown',
                'apn' => $networkStatus['sim_apn'] ?? 'Not configured',
                'operator' => $networkStatus['sim_operator'] ?? 'Unknown',
                'icon' => 'heroicon-o-device-phone-mobile'
            ],
            'signal_quality' => [
                'rssi' => [
                    'value' => $networkStatus['signal_quality']['rssi'] ?? null,
                    'unit' => 'dBm',
                    'label' => 'RSSI'
                ],
                'rsrp' => [
                    'value' => $networkStatus['signal_quality']['rsrp'] ?? null,
                    'unit' => 'dBm',
                    'label' => 'RSRP'
                ],
                'rsrq' => [
                    'value' => $networkStatus['signal_quality']['rsrq'] ?? null,
                    'unit' => 'dB',
                    'label' => 'RSRQ'
                ],
                'sinr' => [
                    'value' => $networkStatus['signal_quality']['sinr'] ?? null,
                    'unit' => 'dB',
                    'label' => 'SINR'
                ],
                'overall_status' => $networkStatus['signal_quality']['status'] ?? 'unknown',
                'icon' => 'heroicon-o-signal'
            ],
            'last_updated' => $networkStatus['last_updated'] ?? null,
            'gateway' => $this->gateway
        ];
    }

    protected function getEmptyData(): array
    {
        return [
            'wan_connection' => [
                'ip_address' => 'No Gateway Selected',
                'status' => 'unknown',
                'icon' => 'heroicon-o-globe-alt'
            ],
            'sim_details' => [
                'iccid' => 'No Gateway Selected',
                'apn' => 'No Gateway Selected',
                'operator' => 'No Gateway Selected',
                'icon' => 'heroicon-o-device-phone-mobile'
            ],
            'signal_quality' => [
                'rssi' => ['value' => null, 'unit' => 'dBm', 'label' => 'RSSI'],
                'rsrp' => ['value' => null, 'unit' => 'dBm', 'label' => 'RSRP'],
                'rsrq' => ['value' => null, 'unit' => 'dB', 'label' => 'RSRQ'],
                'sinr' => ['value' => null, 'unit' => 'dB', 'label' => 'SINR'],
                'overall_status' => 'unknown',
                'icon' => 'heroicon-o-signal'
            ],
            'last_updated' => null,
            'gateway' => null
        ];
    }
}