<?php

namespace App\Filament\Widgets;

use App\Models\Gateway;
use App\Services\RTUDataService;
use Livewire\Component;
use Illuminate\Contracts\View\View;

class RTUIOMonitoringWidget extends Component
{
    protected string $view = 'filament.widgets.rtu-io-monitoring-widget';
    
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
        $ioStatus = $rtuDataService->getIOStatus($this->gateway);
        
        return [
            'digital_inputs' => [
                'di1' => [
                    'status' => $ioStatus['digital_inputs']['di1']['status'] ?? null,
                    'label' => $ioStatus['digital_inputs']['di1']['label'] ?? 'Digital Input 1',
                    'state_text' => ($ioStatus['digital_inputs']['di1']['status'] ?? false) ? 'ON' : 'OFF',
                    'icon' => 'heroicon-o-switch-horizontal'
                ],
                'di2' => [
                    'status' => $ioStatus['digital_inputs']['di2']['status'] ?? null,
                    'label' => $ioStatus['digital_inputs']['di2']['label'] ?? 'Digital Input 2',
                    'state_text' => ($ioStatus['digital_inputs']['di2']['status'] ?? false) ? 'ON' : 'OFF',
                    'icon' => 'heroicon-o-switch-horizontal'
                ]
            ],
            'digital_outputs' => [
                'do1' => [
                    'status' => $ioStatus['digital_outputs']['do1']['status'] ?? null,
                    'label' => $ioStatus['digital_outputs']['do1']['label'] ?? 'Digital Output 1',
                    'controllable' => $ioStatus['digital_outputs']['do1']['controllable'] ?? false,
                    'state_text' => ($ioStatus['digital_outputs']['do1']['status'] ?? false) ? 'ON' : 'OFF',
                    'icon' => 'heroicon-o-switch-horizontal'
                ],
                'do2' => [
                    'status' => $ioStatus['digital_outputs']['do2']['status'] ?? null,
                    'label' => $ioStatus['digital_outputs']['do2']['label'] ?? 'Digital Output 2',
                    'controllable' => $ioStatus['digital_outputs']['do2']['controllable'] ?? false,
                    'state_text' => ($ioStatus['digital_outputs']['do2']['status'] ?? false) ? 'ON' : 'OFF',
                    'icon' => 'heroicon-o-switch-horizontal'
                ]
            ],
            'analog_input' => [
                'voltage' => $ioStatus['analog_input']['voltage'] ?? null,
                'unit' => $ioStatus['analog_input']['unit'] ?? 'V',
                'range' => $ioStatus['analog_input']['range'] ?? '0-10V',
                'precision' => $ioStatus['analog_input']['precision'] ?? 2,
                'formatted_value' => $this->formatVoltage($ioStatus['analog_input']['voltage'] ?? null, $ioStatus['analog_input']['precision'] ?? 2),
                'icon' => 'heroicon-o-bolt'
            ],
            'last_updated' => $ioStatus['last_updated'] ?? null,
            'gateway' => $this->gateway
        ];
    }

    protected function getEmptyData(): array
    {
        return [
            'digital_inputs' => [
                'di1' => ['status' => null, 'label' => 'Digital Input 1', 'state_text' => 'N/A', 'icon' => 'heroicon-o-switch-horizontal'],
                'di2' => ['status' => null, 'label' => 'Digital Input 2', 'state_text' => 'N/A', 'icon' => 'heroicon-o-switch-horizontal']
            ],
            'digital_outputs' => [
                'do1' => ['status' => null, 'label' => 'Digital Output 1', 'controllable' => false, 'state_text' => 'N/A', 'icon' => 'heroicon-o-switch-horizontal'],
                'do2' => ['status' => null, 'label' => 'Digital Output 2', 'controllable' => false, 'state_text' => 'N/A', 'icon' => 'heroicon-o-switch-horizontal']
            ],
            'analog_input' => [
                'voltage' => null,
                'unit' => 'V',
                'range' => '0-10V',
                'precision' => 2,
                'formatted_value' => 'No Gateway Selected',
                'icon' => 'heroicon-o-bolt'
            ],
            'last_updated' => null,
            'gateway' => null
        ];
    }

    protected function formatVoltage(?float $voltage, int $precision = 2): string
    {
        if ($voltage === null) {
            return 'Data Unavailable';
        }
        
        return number_format($voltage, $precision) . ' V';
    }

    public function toggleDigitalOutput(string $output): void
    {
        if (!$this->gateway || !in_array($output, ['do1', 'do2'])) {
            return;
        }

        $currentState = $this->gateway->{$output . '_status'} ?? false;
        $newState = !$currentState;

        $rtuDataService = app(RTUDataService::class);
        $result = $rtuDataService->setDigitalOutput($this->gateway, $output, $newState);

        if ($result['success']) {
            $this->dispatch('outputToggled', [
                'output' => $output,
                'state' => $newState,
                'message' => $result['message']
            ]);
        } else {
            $this->dispatch('outputToggleFailed', [
                'output' => $output,
                'message' => $result['message']
            ]);
        }
    }
}