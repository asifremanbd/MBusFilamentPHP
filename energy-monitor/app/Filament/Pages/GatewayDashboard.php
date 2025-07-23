<?php

namespace App\Filament\Pages;

use App\Models\Gateway;
use App\Services\UserPermissionService;
use App\Filament\Widgets\GatewayStatsOverview;
use App\Filament\Widgets\ActiveAlertsWidget;
use App\Filament\Widgets\DeviceStatusWidget;
use App\Filament\Widgets\ReadingsChartWidget;
use Filament\Pages\Page;
use Filament\Forms\Components\Select;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Concerns\InteractsWithForms;
use Livewire\Attributes\Url;
use Illuminate\Support\Facades\Auth;

class GatewayDashboard extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';
    protected static string $view = 'filament.pages.gateway-dashboard';
    protected static ?string $title = 'Dashboard';
    protected static ?string $navigationLabel = 'Dashboard';
    protected static ?string $navigationGroup = 'Monitoring & Alerts';
    protected static ?int $navigationSort = 0;

    #[Url]
    public ?string $activeGateway = null;

    public function mount(): void
    {
        // Set default gateway if none selected - only from authorized gateways
        if (!$this->activeGateway) {
            $authorizedGateways = $this->getAuthorizedGateways();
            $firstGateway = $authorizedGateways->first();
            $this->activeGateway = $firstGateway?->id;
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('activeGateway')
                    ->label('Select Gateway')
                    ->options($this->getAuthorizedGateways()->pluck('name', 'id'))
                    ->placeholder('Choose a gateway to monitor')
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(function () {
                        $this->dispatch('gateway-changed', gatewayId: $this->activeGateway);
                    }),
            ])
            ->statePath('form');
    }

    protected function getHeaderWidgets(): array
    {
        return [
            GatewayStatsOverview::class,
        ];
    }

    protected function getWidgets(): array
    {
        return [
            ActiveAlertsWidget::class,
            ReadingsChartWidget::class,
            DeviceStatusWidget::class,
        ];
    }

    public function getWidgetData(): array
    {
        return [
            'gatewayId' => $this->activeGateway,
        ];
    }

    public function getHeaderWidgetData(): array
    {
        return [
            'gatewayId' => $this->activeGateway,
        ];
    }

    public function getGateway(): ?Gateway
    {
        if (!$this->activeGateway) {
            return null;
        }
        
        // Ensure user can only access authorized gateways
        $authorizedGateways = $this->getAuthorizedGateways();
        $gateway = $authorizedGateways->find($this->activeGateway);
        
        return $gateway;
    }

    public function getGatewayInfo(): array
    {
        $gateway = $this->getGateway();
        
        if (!$gateway) {
            return [];
        }

        return [
            'name' => $gateway->name,
            'ip' => $gateway->fixed_ip,
            'sim_number' => $gateway->sim_number,
            'signal' => $gateway->gsm_signal,
            'location' => $gateway->gnss_location,
            'device_count' => $gateway->devices()->count(),
            'last_update' => $gateway->devices()
                ->whereHas('readings')
                ->with(['readings' => function ($query) {
                    $query->latest()->limit(1);
                }])
                ->get()
                ->flatMap->readings
                ->max('timestamp'),
        ];
    }

    protected function getViewData(): array
    {
        return [
            'gateway' => $this->getGateway(),
            'gatewayInfo' => $this->getGatewayInfo(),
        ];
    }

    /**
     * Get gateways that the current user is authorized to access
     */
    protected function getAuthorizedGateways()
    {
        $user = Auth::user();
        
        // Admin users can see all gateways
        if ($user->role === 'admin') {
            return Gateway::all();
        }
        
        // For non-admin users, use the permission service
        $permissionService = app(UserPermissionService::class);
        return $permissionService->getAuthorizedGateways($user);
    }
}
