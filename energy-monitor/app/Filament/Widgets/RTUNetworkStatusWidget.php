<?php

namespace App\Filament\Widgets;

use App\Models\Gateway;
use App\Services\RTUDataService;
use Filament\Widgets\Widget;

class RTUNetworkStatusWidget extends Widget
{
    protected static string $view = 'filament.widgets.rtu-network-status-widget';
    protected static ?int $sort = 2;
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
                'error' => 'Invalid or non-RTU gateway'
            ];
        }

        $rtuDataService = app(RTUDataService::class);
        return $rtuDataService->getNetworkStatus($this->gateway);
    }
}