<?php

namespace App\Filament\Pages;

use App\Models\Alert;
use App\Models\Device;
use App\Filament\Resources\DeviceResource;
use App\Filament\Pages\UserProfile;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class OperatorDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Operator Dashboard';
    protected static ?string $title = 'Operator Dashboard';
    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.operator-dashboard';
    
    public static function shouldRegisterNavigation(): bool
    {
        return false; // Disable separate operator dashboard navigation
    }
    
    public function getTotalAssignedDevicesProperty(): int
    {
        return Auth::user()->getAssignedDevices()->count();
    }
    
    public function getActiveDevicesProperty(): int
    {
        $assignedDeviceIds = Auth::user()->getAssignedDevices()->pluck('devices.id');
        return Device::whereIn('id', $assignedDeviceIds)
            ->whereHas('readings', function ($query) {
                $query->where('created_at', '>=', now()->subHours(24));
            })
            ->count();
    }
    
    public function getActiveAlertsProperty(): int
    {
        $assignedDeviceIds = Auth::user()->getAssignedDevices()->pluck('devices.id');
        return Alert::whereIn('device_id', $assignedDeviceIds)
            ->where('resolved', false)
            ->count();
    }
    
    public function getCriticalAlertsProperty(): int
    {
        $assignedDeviceIds = Auth::user()->getAssignedDevices()->pluck('devices.id');
        return Alert::whereIn('device_id', $assignedDeviceIds)
            ->where('resolved', false)
            ->where('severity', 'critical')
            ->count();
    }
    
    public function getTodayAlertsProperty(): int
    {
        $assignedDeviceIds = Auth::user()->getAssignedDevices()->pluck('devices.id');
        return Alert::whereIn('device_id', $assignedDeviceIds)
            ->whereDate('timestamp', today())
            ->count();
    }
    
    public function getRecentAlertsProperty()
    {
        $assignedDeviceIds = Auth::user()->getAssignedDevices()->pluck('devices.id');
        return Alert::with('device')
            ->whereIn('device_id', $assignedDeviceIds)
            ->orderBy('timestamp', 'desc')
            ->limit(10)
            ->get();
    }
    
    public function redirectToDevices()
    {
        return redirect()->to(DeviceResource::getUrl());
    }
    
    public function redirectToProfile()
    {
        return redirect()->to(UserProfile::getUrl());
    }
}
