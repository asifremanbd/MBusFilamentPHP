<?php

namespace App\Filament\Widgets;

use App\Models\Gateway;
use App\Services\RTUDataService;
use Filament\Widgets\Widget;
use Illuminate\Contracts\View\View;

class RTUIOMonitoringWidget extends Widget
{
    protected static string $view = 'filament.widgets.rtu-io-monitoring-widget';
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 'full';
    
    public ?Gateway $gateway = null;

    public function mount(?Gateway $gateway = null): void
    {
        $this->gateway = $gateway;
    }

    public function getData(): array
    {
        if (!$this->gateway || !$this->gateway->isRTUGateway()) {
            return [
                'error' => 'Invalid or non-RTU gateway',
                'digital_inputs' => [
                    'di1' => ['status' => null, 'label' => 'Digital Input 1', 'state_text' => 'Unknown', 'icon' => 'heroicon-o-switch-horizontal'],
                    'di2' => ['status' => null, 'label' => 'Digital Input 2', 'state_text' => 'Unknown', 'icon' => 'heroicon-o-switch-horizontal']
                ],
                'digital_outputs' => [
                    'do1' => ['status' => null, 'label' => 'Digital Output 1', 'controllable' => false, 'state_text' => 'Unknown', 'icon' => 'heroicon-o-switch-horizontal'],
                    'do2' => ['status' => null, 'label' => 'Digital Output 2', 'controllable' => false, 'state_text' => 'Unknown', 'icon' => 'heroicon-o-switch-horizontal']
                ],
                'analog_input' => [
                    'voltage' => null,
                    'unit' => 'V',
                    'range' => '0-10V',
                    'precision' => 2,
                    'formatted_value' => 'Data Unavailable',
                    'icon' => 'heroicon-o-lightning-bolt'
                ],
                'last_updated' => null
            ];
        }

        $rtuDataService = app(RTUDataService::class);
        $ioStatus = $rtuDataService->getIOStatus($this->gateway);
        
        return [
            'digital_inputs' => [
                'di1' => [
                    'status' => $ioStatus['digital_inputs']['di1']['status'],
                    'label' => $ioStatus['digital_inputs']['di1']['label'],
                    'state_text' => $this->getStateText($ioStatus['digital_inputs']['di1']['status']),
                    'state_class' => $this->getStateClass($ioStatus['digital_inputs']['di1']['status']),
                    'icon' => 'heroicon-o-switch-horizontal'
                ],
                'di2' => [
                    'status' => $ioStatus['digital_inputs']['di2']['status'],
                    'label' => $ioStatus['digital_inputs']['di2']['label'],
                    'state_text' => $this->getStateText($ioStatus['digital_inputs']['di2']['status']),
                    'state_class' => $this->getStateClass($ioStatus['digital_inputs']['di2']['status']),
                    'icon' => 'heroicon-o-switch-horizontal'
                ]
            ],
            'digital_outputs' => [
                'do1' => [
                    'status' => $ioStatus['digital_outputs']['do1']['status'],
                    'label' => $ioStatus['digital_outputs']['do1']['label'],
                    'controllable' => $ioStatus['digital_outputs']['do1']['controllable'],
                    'state_text' => $this->getStateText($ioStatus['digital_outputs']['do1']['status']),
                    'state_class' => $this->getStateClass($ioStatus['digital_outputs']['do1']['status']),
                    'icon' => 'heroicon-o-switch-horizontal'
                ],
                'do2' => [
                    'status' => $ioStatus['digital_outputs']['do2']['status'],
                    'label' => $ioStatus['digital_outputs']['do2']['label'],
                    'controllable' => $ioStatus['digital_outputs']['do2']['controllable'],
                    'state_text' => $this->getStateText($ioStatus['digital_outputs']['do2']['status']),
                    'state_class' => $this->getStateClass($ioStatus['digital_outputs']['do2']['status']),
                    'icon' => 'heroicon-o-switch-horizontal'
                ]
            ],
            'analog_input' => [
                'voltage' => $ioStatus['analog_input']['voltage'],
                'unit' => $ioStatus['analog_input']['unit'],
                'range' => $ioStatus['analog_input']['range'],
                'precision' => $ioStatus['analog_input']['precision'],
                'formatted_value' => $this->formatVoltage($ioStatus['analog_input']['voltage'], $ioStatus['analog_input']['precision'], $ioStatus['analog_input']['unit']),
                'status_class' => $this->getVoltageStatusClass($ioStatus['analog_input']['voltage']),
                'icon' => 'heroicon-o-lightning-bolt'
            ],
            'last_updated' => $ioStatus['last_updated'],
            'error' => $ioStatus['error'] ?? null,
            'gateway_id' => $this->gateway->id
        ];
    }

    /**
     * Get state text for digital I/O
     */
    public function getStateText(?bool $status): string
    {
        if ($status === null) {
            return 'Unknown';
        }
        
        return $status ? 'ON' : 'OFF';
    }

    /**
     * Get CSS class for digital I/O state
     */
    public function getStateClass(?bool $status): string
    {
        if ($status === null) {
            return 'text-gray-500 bg-gray-100 border-gray-300';
        }
        
        return $status 
            ? 'text-green-600 bg-green-50 border-green-200' 
            : 'text-gray-600 bg-gray-50 border-gray-200';
    }

    /**
     * Format voltage value with proper precision and unit
     */
    public function formatVoltage(?float $voltage, int $precision, string $unit): string
    {
        if ($voltage === null) {
            return 'Data Unavailable';
        }
        
        return number_format($voltage, $precision) . ' ' . $unit;
    }

    /**
     * Get status class for voltage reading based on range
     */
    public function getVoltageStatusClass(?float $voltage): string
    {
        if ($voltage === null) {
            return 'text-gray-500 bg-gray-100 border-gray-300';
        }
        
        // Normal range for 0-10V input
        if ($voltage >= 0 && $voltage <= 10) {
            return 'text-blue-600 bg-blue-50 border-blue-200';
        }
        
        // Out of range
        return 'text-red-600 bg-red-50 border-red-200';
    }

    /**
     * Get icon for digital I/O state
     */
    public function getStateIcon(?bool $status): string
    {
        if ($status === null) {
            return 'heroicon-o-question-mark-circle';
        }
        
        return $status ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle';
    }

    /**
     * Check if digital output can be controlled
     */
    public function canControlOutput(string $output): bool
    {
        $data = $this->getData();
        return $data['digital_outputs'][$output]['controllable'] ?? false;
    }

    /**
     * Get toggle button class based on current state
     */
    public function getToggleButtonClass(?bool $status, bool $controllable): string
    {
        if (!$controllable) {
            return 'bg-gray-300 text-gray-500 cursor-not-allowed';
        }
        
        if ($status === null) {
            return 'bg-gray-400 text-white cursor-not-allowed';
        }
        
        return $status 
            ? 'bg-green-500 hover:bg-green-600 text-white cursor-pointer' 
            : 'bg-gray-500 hover:bg-gray-600 text-white cursor-pointer';
    }

    /**
     * Get toggle button text
     */
    public function getToggleButtonText(?bool $status, bool $controllable): string
    {
        if (!$controllable) {
            return 'Disabled';
        }
        
        if ($status === null) {
            return 'Unknown';
        }
        
        return $status ? 'Turn OFF' : 'Turn ON';
    }
}