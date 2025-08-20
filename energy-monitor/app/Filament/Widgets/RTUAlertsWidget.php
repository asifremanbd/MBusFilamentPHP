<?php

namespace App\Filament\Widgets;

use App\Models\Gateway;
use App\Services\RTUAlertService;
use Livewire\Component;
use Illuminate\Support\Facades\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\View\View;

class RTUAlertsWidget extends Component
{
    protected string $view = 'filament.widgets.rtu-alerts-widget';
    
    public ?Gateway $gateway = null;
    
    protected RTUAlertService $alertService;
    
    public function __construct()
    {
        $this->alertService = app(RTUAlertService::class);
    }
    
    public function mount(?Gateway $gateway = null): void
    {
        $this->gateway = $gateway ?? Gateway::first();
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
        
        $filters = $this->getFilters();
        
        // Get grouped alerts using the RTUAlertService
        $groupedAlertsData = $this->alertService->getGroupedAlerts($this->gateway);
        
        // Apply additional filtering if filters are provided
        if (!empty($filters)) {
            $filteredAlerts = $this->alertService->getFilteredAlerts($this->gateway, $filters);
            
            // Recalculate counts based on filtered results
            $criticalCount = $filteredAlerts->where('severity', 'critical')->count();
            $warningCount = $filteredAlerts->where('severity', 'warning')->count();
            $infoCount = $filteredAlerts->where('severity', 'info')->count();
            
            $groupedAlertsData['critical_count'] = $criticalCount;
            $groupedAlertsData['warning_count'] = $warningCount;
            $groupedAlertsData['info_count'] = $infoCount;
            $groupedAlertsData['has_alerts'] = $filteredAlerts->isNotEmpty();
            $groupedAlertsData['grouped_alerts'] = $filteredAlerts->take(10);
            $groupedAlertsData['status_summary'] = $this->getStatusSummary($criticalCount, $warningCount);
        }
        
        return [
            'alerts_data' => $groupedAlertsData,
            'device_status' => $this->getDeviceStatusIndicator($groupedAlertsData),
            'filters' => $filters,
            'available_devices' => $this->getAvailableDevices(),
            'severity_options' => $this->getSeverityOptions(),
            'time_range_options' => $this->getTimeRangeOptions(),
            'gateway' => $this->gateway
        ];
    }
    
    protected function getFilters(): array
    {
        return [
            'severity' => Request::get('severity', []),
            'device_ids' => Request::get('device_ids', []),
            'time_range' => Request::get('time_range', 'last_day'),
            'start_date' => Request::get('start_date'),
            'end_date' => Request::get('end_date')
        ];
    }
    
    public function getDeviceStatusIndicator(array $alertsData): array
    {
        $criticalCount = $alertsData['critical_count'];
        $warningCount = $alertsData['warning_count'];
        
        if ($criticalCount > 0) {
            return [
                'status' => 'critical',
                'text' => $criticalCount === 1 ? '1 Critical Alert' : "{$criticalCount} Critical Alerts",
                'color' => 'danger',
                'icon' => 'heroicon-o-exclamation-triangle'
            ];
        }
        
        if ($warningCount > 0) {
            return [
                'status' => 'warning',
                'text' => $warningCount === 1 ? '1 Warning' : "{$warningCount} Warnings",
                'color' => 'warning',
                'icon' => 'heroicon-o-exclamation-circle'
            ];
        }
        
        return [
            'status' => 'ok',
            'text' => 'All Systems OK',
            'color' => 'success',
            'icon' => 'heroicon-o-check-circle'
        ];
    }
    
    public function getStatusSummary(int $criticalCount, int $warningCount): string
    {
        if ($criticalCount > 0) {
            return $criticalCount === 1 ? '1 Critical Alert' : "{$criticalCount} Critical Alerts";
        }
        
        if ($warningCount > 0) {
            return $warningCount === 1 ? '1 Warning' : "{$warningCount} Warnings";
        }
        
        return 'All Systems OK';
    }
    
    protected function getAvailableDevices(): array
    {
        if (!$this->gateway) {
            return [];
        }
        
        return $this->gateway->devices()
            ->select('id', 'name')
            ->get()
            ->map(function ($device) {
                return [
                    'id' => $device->id,
                    'name' => $device->name
                ];
            })
            ->toArray();
    }
    
    public function getSeverityOptions(): array
    {
        return [
            ['value' => 'critical', 'label' => 'Critical'],
            ['value' => 'warning', 'label' => 'Warning'],
            ['value' => 'info', 'label' => 'Info']
        ];
    }
    
    public function getTimeRangeOptions(): array
    {
        return [
            ['value' => 'last_hour', 'label' => 'Last Hour'],
            ['value' => 'last_day', 'label' => 'Last Day'],
            ['value' => 'last_week', 'label' => 'Last Week'],
            ['value' => 'custom', 'label' => 'Custom Range']
        ];
    }
    
    protected function getEmptyData(): array
    {
        return [
            'alerts_data' => [
                'critical_count' => 0,
                'warning_count' => 0,
                'info_count' => 0,
                'has_alerts' => false,
                'grouped_alerts' => collect(),
                'status_summary' => 'No Gateway Selected'
            ],
            'device_status' => [
                'status' => 'ok',
                'text' => 'No Gateway Selected',
                'color' => 'gray',
                'icon' => 'heroicon-o-information-circle'
            ],
            'filters' => [],
            'available_devices' => [],
            'severity_options' => $this->getSeverityOptions(),
            'time_range_options' => $this->getTimeRangeOptions(),
            'gateway' => null
        ];
    }
    
    public function filterAlerts(): JsonResponse
    {
        try {
            if (!$this->gateway) {
                return response()->json([
                    'success' => false,
                    'message' => 'No gateway selected'
                ], 400);
            }
            
            $filters = Request::input('filters', []);
            
            // Get filtered alerts
            $filteredAlerts = $this->alertService->getFilteredAlerts($this->gateway, $filters);
            
            // Calculate counts
            $criticalCount = $filteredAlerts->where('severity', 'critical')->count();
            $warningCount = $filteredAlerts->where('severity', 'warning')->count();
            $infoCount = $filteredAlerts->where('severity', 'info')->count();
            
            $alertsData = [
                'critical_count' => $criticalCount,
                'warning_count' => $warningCount,
                'info_count' => $infoCount,
                'has_alerts' => $filteredAlerts->isNotEmpty(),
                'grouped_alerts' => $filteredAlerts->take(10),
                'status_summary' => $this->getStatusSummary($criticalCount, $warningCount)
            ];
            
            // Generate HTML for alerts
            $html = view('filament.widgets.partials.rtu-alerts-list', [
                'alertsData' => $alertsData
            ])->render();
            
            return response()->json([
                'success' => true,
                'html' => $html,
                'counts' => [
                    'critical' => $criticalCount,
                    'warning' => $warningCount,
                    'info' => $infoCount
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to filter alerts: ' . $e->getMessage()
            ], 500);
        }
    }
}