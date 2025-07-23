<?php

namespace App\Filament\Pages;

use App\Models\Reading;
use App\Models\Device;
use App\Models\Gateway;
use App\Models\Alert;
use Filament\Pages\Page;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static ?string $navigationLabel = 'Reports';
    protected static ?string $title = 'Energy & System Reports';
    protected static ?string $navigationGroup = 'Reports';
    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.reports-page';
    
    public ?array $reportData = [];
    
    public function mount(): void
    {
        $this->reportData = [
            'date_range' => 'last_7_days',
            'device_filter' => null,
            'gateway_filter' => null,
            'parameter_filter' => null,
        ];
        
        $this->generateReports();
    }
    
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Report Filters')
                    ->schema([
                        Forms\Components\Select::make('reportData.date_range')
                            ->label('Date Range')
                            ->options([
                                'today' => 'Today',
                                'yesterday' => 'Yesterday',
                                'last_7_days' => 'Last 7 Days',
                                'last_30_days' => 'Last 30 Days',
                                'this_month' => 'This Month',
                                'last_month' => 'Last Month',
                                'custom' => 'Custom Range',
                            ])
                            ->default('last_7_days')
                            ->live()
                            ->afterStateUpdated(fn () => $this->generateReports()),
                            
                        Forms\Components\Select::make('reportData.gateway_filter')
                            ->label('Gateway Filter')
                            ->options(Gateway::pluck('name', 'id')->prepend('All Gateways', null))
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(fn () => $this->generateReports()),
                            
                        Forms\Components\Select::make('reportData.device_filter')
                            ->label('Device Filter')
                            ->options(function () {
                                $query = Device::with('gateway');
                                if ($this->reportData['gateway_filter']) {
                                    $query->where('gateway_id', $this->reportData['gateway_filter']);
                                }
                                return $query->get()
                                    ->mapWithKeys(function ($device) {
                                        return [$device->id => "{$device->name} ({$device->gateway->name})"];
                                    })
                                    ->prepend('All Devices', null);
                            })
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(fn () => $this->generateReports()),
                            
                        Forms\Components\Select::make('reportData.parameter_filter')
                            ->label('Parameter Filter')
                            ->options(function () {
                                return Reading::distinct()
                                    ->join('registers', 'readings.register_id', '=', 'registers.id')
                                    ->pluck('registers.parameter_name', 'registers.parameter_name')
                                    ->prepend('All Parameters', null);
                            })
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(fn () => $this->generateReports()),
                    ])
                    ->columns(4),
            ])
            ->statePath('reportData');
    }
    
    public function generateReports(): void
    {
        $dateRange = $this->getDateRange();
        
        // Energy consumption report
        $this->energyReport = $this->generateEnergyReport($dateRange);
        
        // System health report
        $this->systemHealthReport = $this->generateSystemHealthReport($dateRange);
        
        // Usage analytics
        $this->usageAnalytics = $this->generateUsageAnalytics($dateRange);
        
        // Alert summary
        $this->alertSummary = $this->generateAlertSummary($dateRange);
    }
    
    protected function getDateRange(): array
    {
        $range = $this->reportData['date_range'] ?? 'last_7_days';
        
        return match ($range) {
            'today' => [Carbon::today(), Carbon::now()],
            'yesterday' => [Carbon::yesterday(), Carbon::yesterday()->endOfDay()],
            'last_7_days' => [Carbon::now()->subDays(7), Carbon::now()],
            'last_30_days' => [Carbon::now()->subDays(30), Carbon::now()],
            'this_month' => [Carbon::now()->startOfMonth(), Carbon::now()],
            'last_month' => [Carbon::now()->subMonth()->startOfMonth(), Carbon::now()->subMonth()->endOfMonth()],
            default => [Carbon::now()->subDays(7), Carbon::now()],
        };
    }
    
    protected function generateEnergyReport(array $dateRange): array
    {
        [$startDate, $endDate] = $dateRange;
        
        $query = Reading::whereBetween('timestamp', [$startDate, $endDate])
            ->join('registers', 'readings.register_id', '=', 'registers.id')
            ->join('devices', 'readings.device_id', '=', 'devices.id');
            
        // Apply filters
        if ($this->reportData['gateway_filter']) {
            $query->where('devices.gateway_id', $this->reportData['gateway_filter']);
        }
        
        if ($this->reportData['device_filter']) {
            $query->where('readings.device_id', $this->reportData['device_filter']);
        }
        
        if ($this->reportData['parameter_filter']) {
            $query->where('registers.parameter_name', $this->reportData['parameter_filter']);
        }
        
        // Energy consumption by device
        $consumptionByDevice = $query->clone()
            ->where('registers.parameter_name', 'LIKE', '%Energy%')
            ->select('devices.name', DB::raw('AVG(readings.value) as avg_consumption'))
            ->groupBy('devices.id', 'devices.name')
            ->orderBy('avg_consumption', 'desc')
            ->get();
            
        // Peak consumption times
        $peakTimes = $query->clone()
            ->where('registers.parameter_name', 'LIKE', '%Power%')
            ->select(DB::raw('HOUR(timestamp) as hour'), DB::raw('AVG(readings.value) as avg_power'))
            ->groupBy(DB::raw('HOUR(timestamp)'))
            ->orderBy('avg_power', 'desc')
            ->limit(5)
            ->get();
            
        return [
            'total_consumption' => $query->clone()
                ->where('registers.parameter_name', 'LIKE', '%Energy%')
                ->sum('readings.value'),
            'consumption_by_device' => $consumptionByDevice,
            'peak_times' => $peakTimes,
            'average_power' => $query->clone()
                ->where('registers.parameter_name', 'LIKE', '%Power%')
                ->avg('readings.value'),
        ];
    }
    
    protected function generateSystemHealthReport(array $dateRange): array
    {
        [$startDate, $endDate] = $dateRange;
        
        // Device uptime
        $totalDevices = Device::count();
        $activeDevices = Device::whereHas('readings', function ($query) use ($startDate, $endDate) {
            $query->whereBetween('timestamp', [$startDate, $endDate]);
        })->count();
        
        // Gateway status
        $gatewayStatus = Gateway::withCount(['devices', 'devices as active_devices_count' => function ($query) use ($startDate, $endDate) {
            $query->whereHas('readings', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('timestamp', [$startDate, $endDate]);
            });
        }])->get();
        
        return [
            'device_uptime_percentage' => $totalDevices > 0 ? round(($activeDevices / $totalDevices) * 100, 2) : 0,
            'total_devices' => $totalDevices,
            'active_devices' => $activeDevices,
            'gateway_status' => $gatewayStatus,
            'data_points_collected' => Reading::whereBetween('timestamp', [$startDate, $endDate])->count(),
        ];
    }
    
    protected function generateUsageAnalytics(array $dateRange): array
    {
        [$startDate, $endDate] = $dateRange;
        
        // Most monitored parameters
        $topParameters = Reading::whereBetween('timestamp', [$startDate, $endDate])
            ->join('registers', 'readings.register_id', '=', 'registers.id')
            ->select('registers.parameter_name', DB::raw('COUNT(*) as reading_count'))
            ->groupBy('registers.parameter_name')
            ->orderBy('reading_count', 'desc')
            ->limit(10)
            ->get();
            
        // Daily reading trends
        $dailyTrends = Reading::whereBetween('timestamp', [$startDate, $endDate])
            ->select(DB::raw('DATE(timestamp) as date'), DB::raw('COUNT(*) as reading_count'))
            ->groupBy(DB::raw('DATE(timestamp)'))
            ->orderBy('date')
            ->get();
            
        return [
            'top_parameters' => $topParameters,
            'daily_trends' => $dailyTrends,
            'total_readings' => Reading::whereBetween('timestamp', [$startDate, $endDate])->count(),
        ];
    }
    
    protected function generateAlertSummary(array $dateRange): array
    {
        [$startDate, $endDate] = $dateRange;
        
        $alerts = Alert::whereBetween('timestamp', [$startDate, $endDate]);
        
        // Apply device filter if set
        if ($this->reportData['device_filter']) {
            $alerts->where('device_id', $this->reportData['device_filter']);
        }
        
        // Alerts by severity
        $alertsBySeverity = $alerts->clone()
            ->select('severity', DB::raw('COUNT(*) as count'))
            ->groupBy('severity')
            ->get();
            
        // Alerts by device
        $alertsByDevice = $alerts->clone()
            ->join('devices', 'alerts.device_id', '=', 'devices.id')
            ->select('devices.name', DB::raw('COUNT(*) as alert_count'))
            ->groupBy('devices.id', 'devices.name')
            ->orderBy('alert_count', 'desc')
            ->limit(10)
            ->get();
            
        return [
            'total_alerts' => $alerts->count(),
            'resolved_alerts' => $alerts->clone()->where('resolved', true)->count(),
            'critical_alerts' => $alerts->clone()->where('severity', 'critical')->count(),
            'alerts_by_severity' => $alertsBySeverity,
            'alerts_by_device' => $alertsByDevice,
        ];
    }
    
    protected function getViewData(): array
    {
        return [
            'energyReport' => $this->energyReport ?? [],
            'systemHealthReport' => $this->systemHealthReport ?? [],
            'usageAnalytics' => $this->usageAnalytics ?? [],
            'alertSummary' => $this->alertSummary ?? [],
        ];
    }
}