<?php

namespace App\Filament\Pages;

use App\Models\Alert;
use App\Models\Device;
use App\Models\Gateway;
use App\Models\User;
use App\Filament\Resources\UserResource;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Filament\Navigation\NavigationItem;

class AdminDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';
    protected static ?string $navigationLabel = 'Admin Dashboard';
    protected static ?string $title = 'Admin Dashboard';
    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.admin-dashboard';
    
    public static function shouldRegisterNavigation(): bool
    {
        return false; // Disable separate admin dashboard navigation
    }
    
    public function getTotalDevicesProperty(): int
    {
        return Device::count();
    }
    
    public function getTotalGatewaysProperty(): int
    {
        return Gateway::count();
    }
    
    public function getTotalUsersProperty(): int
    {
        return User::count();
    }
    
    public function getActiveAlertsProperty(): int
    {
        return Alert::where('resolved', false)->count();
    }
    
    public function getCriticalAlertsProperty(): int
    {
        return Alert::where('resolved', false)
            ->where('severity', 'critical')
            ->count();
    }
    
    public function getTodayAlertsProperty(): int
    {
        return Alert::whereDate('timestamp', today())->count();
    }
    
    public function getAdminUsersProperty(): int
    {
        return User::where('role', 'admin')->count();
    }
    
    public function getOperatorUsersProperty(): int
    {
        return User::where('role', 'operator')->count();
    }
    
    public function getRecentAlertsProperty()
    {
        return Alert::with('device')
            ->orderBy('timestamp', 'desc')
            ->limit(10)
            ->get();
    }
    
    public function redirectToUserManagement()
    {
        return redirect()->to(UserResource::getUrl());
    }
}
