<?php

namespace App\Services;

use App\Models\User;
use App\Widgets\BaseWidget;
use App\Services\UserPermissionService;
use Illuminate\Support\Facades\Log;

class WidgetFactory
{
    protected UserPermissionService $permissionService;
    protected array $widgetRegistry = [];

    public function __construct(UserPermissionService $permissionService)
    {
        $this->permissionService = $permissionService;
        $this->registerDefaultWidgets();
    }

    /**
     * Create a widget instance
     */
    public function create(string $widgetType, User $user, array $config = [], ?int $gatewayId = null): ?BaseWidget
    {
        try {
            // Check if widget type is registered
            if (!isset($this->widgetRegistry[$widgetType])) {
                Log::warning('Unknown widget type requested', [
                    'widget_type' => $widgetType,
                    'user_id' => $user->id
                ]);
                return null;
            }

            $widgetClass = $this->widgetRegistry[$widgetType];

            // Check if class exists
            if (!class_exists($widgetClass)) {
                Log::error('Widget class not found', [
                    'widget_type' => $widgetType,
                    'widget_class' => $widgetClass,
                    'user_id' => $user->id
                ]);
                return null;
            }

            // Create widget instance
            $widget = new $widgetClass($user, $config, $gatewayId);

            // Validate that it extends BaseWidget
            if (!$widget instanceof BaseWidget) {
                Log::error('Widget class does not extend BaseWidget', [
                    'widget_type' => $widgetType,
                    'widget_class' => $widgetClass
                ]);
                return null;
            }

            return $widget;

        } catch (\Exception $e) {
            Log::error('Failed to create widget', [
                'widget_type' => $widgetType,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Create multiple widgets for a dashboard
     */
    public function createMultiple(array $widgetSpecs, User $user, ?int $gatewayId = null): array
    {
        $widgets = [];

        foreach ($widgetSpecs as $spec) {
            $widgetType = $spec['type'] ?? null;
            $config = $spec['config'] ?? [];

            if (!$widgetType) {
                continue;
            }

            $widget = $this->create($widgetType, $user, $config, $gatewayId);
            if ($widget) {
                $widgets[$widgetType] = $widget;
            }
        }

        return $widgets;
    }

    /**
     * Get authorized widgets for a user and dashboard type
     */
    public function getAuthorizedWidgets(User $user, string $dashboardType, ?int $gatewayId = null): array
    {
        $availableWidgets = $this->getAvailableWidgetsForDashboard($dashboardType);
        $authorizedWidgets = [];

        foreach ($availableWidgets as $widgetType => $widgetInfo) {
            $config = ['gateway_id' => $gatewayId];
            
            if ($this->permissionService->canAccessWidget($user, $widgetType, $config)) {
                $widget = $this->create($widgetType, $user, $config, $gatewayId);
                if ($widget) {
                    $authorizedWidgets[$widgetType] = $widget;
                }
            }
        }

        return $authorizedWidgets;
    }

    /**
     * Register a widget type
     */
    public function register(string $widgetType, string $widgetClass): void
    {
        $this->widgetRegistry[$widgetType] = $widgetClass;
    }

    /**
     * Unregister a widget type
     */
    public function unregister(string $widgetType): void
    {
        unset($this->widgetRegistry[$widgetType]);
    }

    /**
     * Get all registered widget types
     */
    public function getRegisteredWidgets(): array
    {
        return array_keys($this->widgetRegistry);
    }

    /**
     * Check if a widget type is registered
     */
    public function isRegistered(string $widgetType): bool
    {
        return isset($this->widgetRegistry[$widgetType]);
    }

    /**
     * Get widget class for a type
     */
    public function getWidgetClass(string $widgetType): ?string
    {
        return $this->widgetRegistry[$widgetType] ?? null;
    }

    /**
     * Register default widgets
     */
    protected function registerDefaultWidgets(): void
    {
        // Global Dashboard Widgets
        $this->register('system-overview', \App\Widgets\Global\SystemOverviewWidget::class);
        $this->register('cross-gateway-alerts', \App\Widgets\Global\CrossGatewayAlertsWidget::class);
        $this->register('top-consuming-gateways', \App\Widgets\Global\TopConsumingGatewaysWidget::class);
        $this->register('system-health', \App\Widgets\Global\SystemHealthWidget::class);

        // Gateway Dashboard Widgets
        $this->register('gateway-device-list', \App\Widgets\Gateway\GatewayDeviceListWidget::class);
        $this->register('real-time-readings', \App\Widgets\Gateway\RealTimeReadingsWidget::class);
        $this->register('gateway-stats', \App\Widgets\Gateway\GatewayStatsWidget::class);
        $this->register('gateway-alerts', \App\Widgets\Gateway\GatewayAlertsWidget::class);
    }

    /**
     * Get available widgets for a dashboard type
     */
    protected function getAvailableWidgetsForDashboard(string $dashboardType): array
    {
        return match($dashboardType) {
            'global' => [
                'system-overview' => ['name' => 'System Overview', 'category' => 'overview'],
                'cross-gateway-alerts' => ['name' => 'Cross-Gateway Alerts', 'category' => 'alerts'],
                'top-consuming-gateways' => ['name' => 'Top Consuming Gateways', 'category' => 'analytics'],
                'system-health' => ['name' => 'System Health', 'category' => 'monitoring'],
            ],
            'gateway' => [
                'gateway-device-list' => ['name' => 'Device List', 'category' => 'devices'],
                'real-time-readings' => ['name' => 'Real-time Readings', 'category' => 'monitoring'],
                'gateway-stats' => ['name' => 'Gateway Statistics', 'category' => 'overview'],
                'gateway-alerts' => ['name' => 'Gateway Alerts', 'category' => 'alerts'],
            ],
            default => []
        };
    }

    /**
     * Render multiple widgets
     */
    public function renderWidgets(array $widgets): array
    {
        $renderedWidgets = [];

        foreach ($widgets as $widgetType => $widget) {
            if ($widget instanceof BaseWidget) {
                $renderedWidgets[$widgetType] = $widget->render();
            }
        }

        return $renderedWidgets;
    }

    /**
     * Get widget metadata for multiple widgets
     */
    public function getWidgetsMetadata(array $widgets): array
    {
        $metadata = [];

        foreach ($widgets as $widgetType => $widget) {
            if ($widget instanceof BaseWidget) {
                $metadata[$widgetType] = $widget->getMetadata();
            }
        }

        return $metadata;
    }

    /**
     * Clear cache for multiple widgets
     */
    public function clearWidgetsCache(array $widgets): void
    {
        foreach ($widgets as $widget) {
            if ($widget instanceof BaseWidget) {
                $widget->clearCache();
            }
        }
    }

    /**
     * Validate widget configuration
     */
    public function validateWidgetConfig(string $widgetType, array $config): bool
    {
        try {
            $widgetClass = $this->getWidgetClass($widgetType);
            if (!$widgetClass || !class_exists($widgetClass)) {
                return false;
            }

            // Create a temporary instance to validate config
            $tempUser = new User(); // Temporary user for validation
            $widget = new $widgetClass($tempUser, $config);
            
            return $widget instanceof BaseWidget;
        } catch (\Exception $e) {
            Log::warning('Widget config validation failed', [
                'widget_type' => $widgetType,
                'config' => $config,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get widget performance metrics
     */
    public function getWidgetPerformanceMetrics(BaseWidget $widget): array
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        $result = $widget->render();

        $endTime = microtime(true);
        $endMemory = memory_get_usage();

        return [
            'widget_type' => $widget->getWidgetType(),
            'execution_time' => round(($endTime - $startTime) * 1000, 2), // milliseconds
            'memory_usage' => $endMemory - $startMemory,
            'status' => $result['status'] ?? 'unknown',
            'from_cache' => $result['metadata']['from_cache'] ?? false,
            'timestamp' => now()->toISOString(),
        ];
    }
}