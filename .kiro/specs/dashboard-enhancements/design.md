# Design: Dashboard Enhancements with User-Based Access Control

## Overview

This design enhances the existing Laravel-based energy monitoring dashboard system to implement user-specific access control based on device and gateway permissions. The solution builds upon the current Filament-based dashboard architecture and existing RBAC system to provide personalized dashboard experiences with two distinct dashboard types: Global Dashboard and Gateway-Based Dashboard.

The enhancement integrates with the existing user management system, leveraging the current User model, device assignments, and role-based permissions while adding sophisticated dashboard filtering and widget customization capabilities.

## Architecture

### Dashboard Access Control Layer
- **Permission-Based Filtering**: All dashboard data filtered based on user's assigned devices and gateways
- **Real-Time Permission Updates**: Immediate dashboard updates when permissions change
- **Session-Based Caching**: Efficient permission caching with automatic invalidation
- **Audit Logging**: Complete access logging for security and compliance

### Dashboard Type Architecture
- **Global Dashboard**: System-wide overview aggregating data from user's authorized gateways
- **Gateway-Based Dashboard**: Detailed view of specific gateway with device-level monitoring
- **Dynamic Widget Loading**: Conditional widget rendering based on user permissions
- **Responsive Layout System**: Adaptive layouts for both dashboard types

### Widget Permission System
- **Widget-Level Authorization**: Each widget checks user permissions before rendering
- **Data Scope Filtering**: Automatic filtering of widget data to authorized resources
- **Progressive Loading**: Widgets load incrementally based on permission validation
- **Error Boundary Handling**: Graceful degradation for unauthorized widget access

## Components and Interfaces

### Enhanced Dashboard Components

#### Permission-Aware Dashboard Controller
```php
class DashboardController extends Controller
{
    public function globalDashboard(Request $request): Response
    {
        $authorizedGateways = $this->getAuthorizedGateways($request->user());
        $dashboardConfig = $this->getUserDashboardConfig($request->user(), 'global');
        
        return view('dashboard.global', [
            'gateways' => $authorizedGateways,
            'widgets' => $this->getAuthorizedWidgets($request->user(), 'global'),
            'config' => $dashboardConfig
        ]);
    }
    
    public function gatewayDashboard(Request $request, int $gatewayId): Response
    {
        $this->authorize('view', Gateway::findOrFail($gatewayId));
        
        $gateway = $this->getAuthorizedGateway($request->user(), $gatewayId);
        $dashboardConfig = $this->getUserDashboardConfig($request->user(), 'gateway');
        
        return view('dashboard.gateway', [
            'gateway' => $gateway,
            'devices' => $this->getAuthorizedDevices($request->user(), $gatewayId),
            'widgets' => $this->getAuthorizedWidgets($request->user(), 'gateway'),
            'config' => $dashboardConfig
        ]);
    }
}
```

#### User Permission Service
```php
class UserPermissionService
{
    public function getAuthorizedGateways(User $user): Collection
    {
        if ($user->isAdmin()) {
            return Gateway::all();
        }
        
        return Gateway::whereHas('devices', function ($query) use ($user) {
            $query->whereIn('id', $user->getAssignedDeviceIds());
        })->get();
    }
    
    public function getAuthorizedDevices(User $user, ?int $gatewayId = null): Collection
    {
        $query = Device::whereIn('id', $user->getAssignedDeviceIds());
        
        if ($gatewayId) {
            $query->where('gateway_id', $gatewayId);
        }
        
        return $query->get();
    }
    
    public function canAccessWidget(User $user, string $widgetType, array $widgetConfig): bool
    {
        return match($widgetType) {
            'system-overview' => $this->hasSystemOverviewAccess($user),
            'gateway-stats' => $this->hasGatewayAccess($user, $widgetConfig['gateway_id'] ?? null),
            'device-status' => $this->hasDeviceAccess($user, $widgetConfig['device_ids'] ?? []),
            'alerts-summary' => $this->hasAlertAccess($user, $widgetConfig),
            default => false
        };
    }
}
```

### Dashboard Widget System

#### Global Dashboard Widgets
```php
class SystemOverviewWidget extends BaseWidget
{
    protected function getData(): array
    {
        $authorizedGateways = $this->permissionService->getAuthorizedGateways($this->user);
        
        return [
            'total_energy_consumption' => $this->calculateTotalConsumption($authorizedGateways),
            'active_devices_count' => $this->getActiveDevicesCount($authorizedGateways),
            'critical_alerts_count' => $this->getCriticalAlertsCount($authorizedGateways),
            'system_health_score' => $this->calculateSystemHealth($authorizedGateways),
            'top_consuming_gateways' => $this->getTopConsumingGateways($authorizedGateways, 5)
        ];
    }
}

class CrossGatewayAlertsWidget extends BaseWidget
{
    protected function getData(): array
    {
        $authorizedDevices = $this->permissionService->getAuthorizedDevices($this->user);
        
        return [
            'critical_alerts' => $this->getAlertsByType($authorizedDevices, 'critical'),
            'warning_alerts' => $this->getAlertsByType($authorizedDevices, 'warning'),
            'info_alerts' => $this->getAlertsByType($authorizedDevices, 'info'),
            'recent_alerts' => $this->getRecentAlerts($authorizedDevices, 10)
        ];
    }
}
```

#### Gateway-Based Dashboard Widgets
```php
class GatewayDeviceListWidget extends BaseWidget
{
    protected function getData(): array
    {
        $authorizedDevices = $this->permissionService->getAuthorizedDevices(
            $this->user, 
            $this->gatewayId
        );
        
        return [
            'devices' => $authorizedDevices->map(function ($device) {
                return [
                    'id' => $device->id,
                    'name' => $device->name,
                    'status' => $this->getDeviceStatus($device),
                    'last_reading' => $this->getLastReading($device),
                    'alert_count' => $this->getActiveAlertCount($device)
                ];
            })
        ];
    }
}

class RealTimeReadingsWidget extends BaseWidget
{
    protected function getData(): array
    {
        $authorizedDevices = $this->permissionService->getAuthorizedDevices(
            $this->user, 
            $this->gatewayId
        );
        
        return [
            'live_readings' => $this->getLiveReadings($authorizedDevices),
            'reading_trends' => $this->getReadingTrends($authorizedDevices, '1 hour'),
            'parameter_summaries' => $this->getParameterSummaries($authorizedDevices)
        ];
    }
}
```

### Dashboard Configuration System

#### User Dashboard Configuration Model
```php
class UserDashboardConfig extends Model
{
    protected $fillable = [
        'user_id', 'dashboard_type', 'widget_config', 'layout_config'
    ];
    
    protected $casts = [
        'widget_config' => 'array',
        'layout_config' => 'array'
    ];
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    public function getWidgetVisibility(string $widgetId): bool
    {
        return $this->widget_config['visibility'][$widgetId] ?? true;
    }
    
    public function getWidgetPosition(string $widgetId): array
    {
        return $this->layout_config['positions'][$widgetId] ?? ['row' => 0, 'col' => 0];
    }
    
    public function getWidgetSize(string $widgetId): array
    {
        return $this->layout_config['sizes'][$widgetId] ?? ['width' => 12, 'height' => 4];
    }
}
```

#### Dashboard Configuration Service
```php
class DashboardConfigService
{
    public function getUserDashboardConfig(User $user, string $dashboardType): UserDashboardConfig
    {
        return UserDashboardConfig::firstOrCreate([
            'user_id' => $user->id,
            'dashboard_type' => $dashboardType
        ], [
            'widget_config' => $this->getDefaultWidgetConfig($dashboardType),
            'layout_config' => $this->getDefaultLayoutConfig($dashboardType)
        ]);
    }
    
    public function updateWidgetVisibility(User $user, string $dashboardType, string $widgetId, bool $visible): void
    {
        $config = $this->getUserDashboardConfig($user, $dashboardType);
        $widgetConfig = $config->widget_config;
        $widgetConfig['visibility'][$widgetId] = $visible;
        $config->update(['widget_config' => $widgetConfig]);
    }
    
    public function updateWidgetLayout(User $user, string $dashboardType, array $layoutUpdates): void
    {
        $config = $this->getUserDashboardConfig($user, $dashboardType);
        $layoutConfig = $config->layout_config;
        
        foreach ($layoutUpdates as $widgetId => $layout) {
            $layoutConfig['positions'][$widgetId] = $layout['position'];
            $layoutConfig['sizes'][$widgetId] = $layout['size'];
        }
        
        $config->update(['layout_config' => $layoutConfig]);
    }
}
```

## Data Models

### Enhanced User Device Assignment
```sql
CREATE TABLE user_gateway_assignments (
    id BIGINT UNSIGNED PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    gateway_id BIGINT UNSIGNED NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_by BIGINT UNSIGNED NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (gateway_id) REFERENCES gateways(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_user_gateway (user_id, gateway_id)
);
```

### Dashboard Configuration Schema
```sql
CREATE TABLE user_dashboard_configs (
    id BIGINT UNSIGNED PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    dashboard_type ENUM('global', 'gateway') NOT NULL,
    widget_config JSON NOT NULL,
    layout_config JSON NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_dashboard_type (user_id, dashboard_type)
);
```

### Permission Access Log Schema
```sql
CREATE TABLE dashboard_access_logs (
    id BIGINT UNSIGNED PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    dashboard_type VARCHAR(50) NOT NULL,
    gateway_id BIGINT UNSIGNED NULL,
    widget_accessed VARCHAR(100) NULL,
    access_granted BOOLEAN NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,
    accessed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (gateway_id) REFERENCES gateways(id) ON DELETE SET NULL,
    INDEX idx_user_access (user_id, accessed_at),
    INDEX idx_gateway_access (gateway_id, accessed_at)
);
```

## Error Handling

### Permission Validation Errors
- **Unauthorized Gateway Access**: HTTP 403 with clear error message when user attempts to access unauthorized gateway
- **Device Permission Violations**: Graceful widget hiding when user lacks device permissions
- **Real-time Permission Changes**: Immediate dashboard updates when permissions are revoked
- **Session Permission Sync**: Automatic permission refresh on session validation

### Widget Loading Errors
- **Data Unavailable**: Fallback displays when authorized data is temporarily unavailable
- **Partial Permission Access**: Progressive widget loading with partial data display
- **Widget Configuration Errors**: Default configuration fallback for corrupted user preferences
- **Network Connectivity Issues**: Offline mode with cached data display

### Dashboard Configuration Errors
- **Invalid Layout Configurations**: Automatic reset to default layout on configuration corruption
- **Widget Dependency Errors**: Dependency validation before widget rendering
- **User Preference Conflicts**: Conflict resolution with user notification
- **Migration Errors**: Graceful handling of configuration schema changes

### Error Recovery Strategy
```php
class DashboardErrorHandler
{
    public function handlePermissionError(User $user, string $resource, Exception $e): Response
    {
        Log::channel('security')->warning('Dashboard permission violation', [
            'user_id' => $user->id,
            'resource' => $resource,
            'error' => $e->getMessage(),
            'ip_address' => request()->ip()
        ]);
        
        return response()->json([
            'error' => 'Access denied',
            'message' => 'You do not have permission to access this resource',
            'fallback_action' => $this->getFallbackAction($user, $resource)
        ], 403);
    }
    
    public function handleWidgetError(string $widgetId, Exception $e): array
    {
        Log::error('Widget loading error', [
            'widget_id' => $widgetId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return [
            'widget_id' => $widgetId,
            'status' => 'error',
            'fallback_data' => $this->getWidgetFallbackData($widgetId),
            'retry_available' => true
        ];
    }
}
```

## Testing Strategy

### Permission Testing
- **User Access Control**: Verify users only see authorized gateways and devices
- **Real-time Permission Updates**: Test immediate dashboard updates when permissions change
- **Cross-User Isolation**: Ensure users cannot access other users' data
- **Admin Override Testing**: Verify admin users can access all resources

### Widget Authorization Testing
- **Widget-Level Permissions**: Test each widget type with various permission combinations
- **Data Filtering Accuracy**: Verify widget data contains only authorized resources
- **Progressive Loading**: Test widget loading with partial permissions
- **Error Boundary Testing**: Verify graceful handling of permission errors

### Dashboard Configuration Testing
- **Layout Persistence**: Test widget positioning and sizing across sessions
- **Configuration Migration**: Test handling of configuration schema changes
- **Multi-Dashboard Configs**: Verify separate configurations for global and gateway dashboards
- **Bulk Configuration Updates**: Test performance with large configuration changes

### Integration Testing
- **RBAC Integration**: Test integration with existing role-based access control
- **Real-time Updates**: Test dashboard updates with live data changes
- **Session Management**: Test permission caching and invalidation
- **Cross-Browser Compatibility**: Test dashboard functionality across different browsers

### Performance Testing
- **Permission Query Optimization**: Test database query performance with large user bases
- **Widget Loading Performance**: Test widget rendering performance with large datasets
- **Real-time Update Performance**: Test performance of live dashboard updates
- **Concurrent User Testing**: Test system performance with multiple simultaneous users