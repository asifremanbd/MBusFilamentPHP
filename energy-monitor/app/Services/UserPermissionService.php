<?php

namespace App\Services;

use App\Models\User;
use App\Models\Gateway;
use App\Models\Device;
use App\Models\Alert;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class UserPermissionService
{
    /**
     * Get gateways authorized for the user
     * @param User|int $user User object or user ID
     */
    public function getAuthorizedGateways($user): Collection
    {
        // If user is an ID, fetch the User object
        if (is_numeric($user)) {
            $user = User::find($user);
            if (!$user) {
                return collect(); // Return empty collection if user not found
            }
        }

        if ($user->isAdmin()) {
            return Gateway::all();
        }

        $cacheKey = "user_gateways_{$user->id}";
        
        return Cache::remember($cacheKey, 300, function () use ($user) {
            return Gateway::whereHas('devices', function ($query) use ($user) {
                $query->whereIn('id', $user->getAssignedDeviceIds());
            })->get();
        });
    }

    /**
     * Get devices authorized for the user
     */
    public function getAuthorizedDevices(User $user, ?int $gatewayId = null): Collection
    {
        $assignedDeviceIds = $user->getAssignedDeviceIds();
        
        if (empty($assignedDeviceIds)) {
            return collect();
        }

        $query = Device::whereIn('id', $assignedDeviceIds);
        
        if ($gatewayId) {
            $query->where('gateway_id', $gatewayId);
        }
        
        return $query->get();
    }

    /**
     * Get a specific authorized gateway for the user
     */
    public function getAuthorizedGateway(User $user, int $gatewayId): ?Gateway
    {
        if (!$user->canAccessGateway($gatewayId)) {
            return null;
        }

        return Gateway::find($gatewayId);
    }

    /**
     * Check if user can access a specific widget
     */
    public function canAccessWidget(User $user, string $widgetType, array $widgetConfig = []): bool
    {
        return match($widgetType) {
            'system-overview' => $this->hasSystemOverviewAccess($user),
            'cross-gateway-alerts' => $this->hasCrossGatewayAlertsAccess($user),
            'top-consuming-gateways' => $this->hasTopConsumingGatewaysAccess($user),
            'system-health' => $this->hasSystemHealthAccess($user),
            'gateway-device-list' => $this->hasGatewayDeviceListAccess($user, $widgetConfig),
            'real-time-readings' => $this->hasRealTimeReadingsAccess($user, $widgetConfig),
            'gateway-stats' => $this->hasGatewayStatsAccess($user, $widgetConfig),
            'gateway-alerts' => $this->hasGatewayAlertsAccess($user, $widgetConfig),
            default => false
        };
    }

    /**
     * Get alerts authorized for the user
     */
    public function getAuthorizedAlerts(User $user, ?int $gatewayId = null): Collection
    {
        if ($user->isAdmin()) {
            $query = Alert::query();
        } else {
            $assignedDeviceIds = $user->getAssignedDeviceIds();
            if (empty($assignedDeviceIds)) {
                return collect();
            }
            $query = Alert::whereIn('device_id', $assignedDeviceIds);
        }

        if ($gatewayId) {
            $query->whereHas('device', function ($q) use ($gatewayId) {
                $q->where('gateway_id', $gatewayId);
            });
        }

        return $query->get();
    }

    /**
     * Clear user permission cache
     */
    public function clearUserPermissionCache(User $user): void
    {
        Cache::forget("user_gateways_{$user->id}");
        Cache::forget("user_devices_{$user->id}");
    }

    /**
     * Check system overview access
     */
    private function hasSystemOverviewAccess(User $user): bool
    {
        // User needs access to at least one gateway
        return !empty($user->getAssignedGatewayIds()) || $user->isAdmin();
    }

    /**
     * Check cross-gateway alerts access
     */
    private function hasCrossGatewayAlertsAccess(User $user): bool
    {
        // User needs access to at least one device
        return !empty($user->getAssignedDeviceIds()) || $user->isAdmin();
    }

    /**
     * Check top consuming gateways access
     */
    private function hasTopConsumingGatewaysAccess(User $user): bool
    {
        // User needs access to at least one gateway
        return !empty($user->getAssignedGatewayIds()) || $user->isAdmin();
    }

    /**
     * Check system health access
     */
    private function hasSystemHealthAccess(User $user): bool
    {
        // User needs access to at least one gateway
        return !empty($user->getAssignedGatewayIds()) || $user->isAdmin();
    }

    /**
     * Check gateway device list access
     */
    private function hasGatewayDeviceListAccess(User $user, array $widgetConfig): bool
    {
        $gatewayId = $widgetConfig['gateway_id'] ?? null;
        
        if (!$gatewayId) {
            return false;
        }

        return $user->canAccessGateway($gatewayId);
    }

    /**
     * Check real-time readings access
     */
    private function hasRealTimeReadingsAccess(User $user, array $widgetConfig): bool
    {
        $gatewayId = $widgetConfig['gateway_id'] ?? null;
        
        if (!$gatewayId) {
            return false;
        }

        return $user->canAccessGateway($gatewayId);
    }

    /**
     * Check gateway stats access
     */
    private function hasGatewayStatsAccess(User $user, array $widgetConfig): bool
    {
        $gatewayId = $widgetConfig['gateway_id'] ?? null;
        
        if (!$gatewayId) {
            return false;
        }

        return $user->canAccessGateway($gatewayId);
    }

    /**
     * Check gateway alerts access
     */
    private function hasGatewayAlertsAccess(User $user, array $widgetConfig): bool
    {
        $gatewayId = $widgetConfig['gateway_id'] ?? null;
        
        if (!$gatewayId) {
            return false;
        }

        return $user->canAccessGateway($gatewayId);
    }

    /**
     * Get authorized widgets for user and dashboard type
     */
    public function getAuthorizedWidgets(User $user, string $dashboardType, ?int $gatewayId = null): array
    {
        $widgets = [];

        if ($dashboardType === 'global') {
            $globalWidgets = [
                'system-overview',
                'cross-gateway-alerts', 
                'top-consuming-gateways',
                'system-health'
            ];

            foreach ($globalWidgets as $widget) {
                if ($this->canAccessWidget($user, $widget)) {
                    $widgets[] = $widget;
                }
            }
        } elseif ($dashboardType === 'gateway' && $gatewayId) {
            $gatewayWidgets = [
                'gateway-device-list',
                'real-time-readings',
                'gateway-stats',
                'gateway-alerts'
            ];

            $widgetConfig = ['gateway_id' => $gatewayId];

            foreach ($gatewayWidgets as $widget) {
                if ($this->canAccessWidget($user, $widget, $widgetConfig)) {
                    $widgets[] = $widget;
                }
            }
        }

        return $widgets;
    }
}