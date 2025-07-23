<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\DashboardConfigService;
use App\Services\UserPermissionService;
use App\Services\WidgetFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class DashboardConfigController extends Controller
{
    protected DashboardConfigService $configService;
    protected UserPermissionService $permissionService;
    protected WidgetFactory $widgetFactory;

    public function __construct(
        DashboardConfigService $configService,
        UserPermissionService $permissionService,
        WidgetFactory $widgetFactory
    ) {
        $this->configService = $configService;
        $this->permissionService = $permissionService;
        $this->widgetFactory = $widgetFactory;
    }

    /**
     * Get user's dashboard configuration
     */
    public function getConfig(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'dashboard_type' => 'required|in:global,gateway',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $user = $request->user();
            $dashboardType = $request->input('dashboard_type');

            $config = $this->configService->getUserDashboardConfig($user, $dashboardType);

            return response()->json([
                'success' => true,
                'config' => [
                    'dashboard_type' => $config->dashboard_type,
                    'widget_config' => $config->widget_config,
                    'layout_config' => $config->layout_config,
                    'updated_at' => $config->updated_at->toISOString(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get dashboard config error', [
                'user_id' => $request->user()?->id,
                'dashboard_type' => $request->input('dashboard_type'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to get dashboard configuration',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update widget visibility
     */
    public function updateWidgetVisibility(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'dashboard_type' => 'required|in:global,gateway',
                'widget_id' => 'required|string',
                'visible' => 'required|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $user = $request->user();
            $dashboardType = $request->input('dashboard_type');
            $widgetId = $request->input('widget_id');
            $visible = $request->input('visible');

            // Validate that the widget exists and user has permission
            if (!$this->validateWidgetAccess($user, $widgetId, $dashboardType)) {
                return response()->json([
                    'error' => 'Access denied',
                    'message' => 'You do not have permission to configure this widget'
                ], 403);
            }

            $this->configService->updateWidgetVisibility($user, $dashboardType, $widgetId, $visible);

            return response()->json([
                'success' => true,
                'message' => 'Widget visibility updated successfully',
                'widget_id' => $widgetId,
                'visible' => $visible
            ]);

        } catch (\Exception $e) {
            Log::error('Update widget visibility error', [
                'user_id' => $request->user()?->id,
                'dashboard_type' => $request->input('dashboard_type'),
                'widget_id' => $request->input('widget_id'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to update widget visibility',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update widget layout (position and size)
     */
    public function updateWidgetLayout(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'dashboard_type' => 'required|in:global,gateway',
                'layout_updates' => 'required|array',
                'layout_updates.*.widget_id' => 'required|string',
                'layout_updates.*.position' => 'required|array',
                'layout_updates.*.position.row' => 'required|integer|min:0',
                'layout_updates.*.position.col' => 'required|integer|min:0',
                'layout_updates.*.size' => 'required|array',
                'layout_updates.*.size.width' => 'required|integer|min:1|max:12',
                'layout_updates.*.size.height' => 'required|integer|min:1|max:20',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $user = $request->user();
            $dashboardType = $request->input('dashboard_type');
            $layoutUpdates = $request->input('layout_updates');

            // Validate widget access for all widgets being updated
            foreach ($layoutUpdates as $update) {
                if (!$this->validateWidgetAccess($user, $update['widget_id'], $dashboardType)) {
                    return response()->json([
                        'error' => 'Access denied',
                        'message' => "You do not have permission to configure widget: {$update['widget_id']}"
                    ], 403);
                }
            }

            // Transform layout updates to the format expected by the service
            $formattedUpdates = [];
            foreach ($layoutUpdates as $update) {
                $formattedUpdates[$update['widget_id']] = [
                    'position' => $update['position'],
                    'size' => $update['size']
                ];
            }

            $this->configService->updateWidgetLayout($user, $dashboardType, $formattedUpdates);

            return response()->json([
                'success' => true,
                'message' => 'Widget layout updated successfully',
                'updated_widgets' => array_keys($formattedUpdates)
            ]);

        } catch (\Exception $e) {
            Log::error('Update widget layout error', [
                'user_id' => $request->user()?->id,
                'dashboard_type' => $request->input('dashboard_type'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to update widget layout',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reset dashboard configuration to defaults
     */
    public function resetConfig(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'dashboard_type' => 'required|in:global,gateway',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $user = $request->user();
            $dashboardType = $request->input('dashboard_type');

            $this->configService->resetDashboardConfig($user, $dashboardType);

            return response()->json([
                'success' => true,
                'message' => 'Dashboard configuration reset to defaults'
            ]);

        } catch (\Exception $e) {
            Log::error('Reset dashboard config error', [
                'user_id' => $request->user()?->id,
                'dashboard_type' => $request->input('dashboard_type'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to reset dashboard configuration',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available widgets for a dashboard type
     */
    public function getAvailableWidgets(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'dashboard_type' => 'required|in:global,gateway',
                'gateway_id' => 'nullable|integer|exists:gateways,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $user = $request->user();
            $dashboardType = $request->input('dashboard_type');
            $gatewayId = $request->input('gateway_id');

            // For gateway dashboard, validate gateway access
            if ($dashboardType === 'gateway' && $gatewayId) {
                $gateway = \App\Models\Gateway::findOrFail($gatewayId);
                $this->authorize('view', $gateway);
            }

            $widgets = $this->widgetFactory->getAuthorizedWidgets($user, $dashboardType, $gatewayId);
            $widgetMetadata = $this->widgetFactory->getWidgetsMetadata($widgets);

            return response()->json([
                'success' => true,
                'dashboard_type' => $dashboardType,
                'gateway_id' => $gatewayId,
                'widgets' => $widgetMetadata
            ]);

        } catch (\Exception $e) {
            Log::error('Get available widgets error', [
                'user_id' => $request->user()?->id,
                'dashboard_type' => $request->input('dashboard_type'),
                'gateway_id' => $request->input('gateway_id'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to get available widgets',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update widget configuration
     */
    public function updateWidgetConfig(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'dashboard_type' => 'required|in:global,gateway',
                'widget_id' => 'required|string',
                'config' => 'required|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $user = $request->user();
            $dashboardType = $request->input('dashboard_type');
            $widgetId = $request->input('widget_id');
            $config = $request->input('config');

            // Validate widget access
            if (!$this->validateWidgetAccess($user, $widgetId, $dashboardType)) {
                return response()->json([
                    'error' => 'Access denied',
                    'message' => 'You do not have permission to configure this widget'
                ], 403);
            }

            // Validate widget configuration
            if (!$this->widgetFactory->validateWidgetConfig($widgetId, $config)) {
                return response()->json([
                    'error' => 'Invalid configuration',
                    'message' => 'The provided widget configuration is invalid'
                ], 400);
            }

            $this->configService->updateWidgetConfig($user, $dashboardType, $widgetId, $config);

            return response()->json([
                'success' => true,
                'message' => 'Widget configuration updated successfully',
                'widget_id' => $widgetId
            ]);

        } catch (\Exception $e) {
            Log::error('Update widget config error', [
                'user_id' => $request->user()?->id,
                'dashboard_type' => $request->input('dashboard_type'),
                'widget_id' => $request->input('widget_id'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to update widget configuration',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get widget performance metrics
     */
    public function getWidgetPerformance(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'dashboard_type' => 'required|in:global,gateway',
                'widget_id' => 'required|string',
                'gateway_id' => 'nullable|integer|exists:gateways,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $user = $request->user();
            $dashboardType = $request->input('dashboard_type');
            $widgetId = $request->input('widget_id');
            $gatewayId = $request->input('gateway_id');

            // Validate widget access
            if (!$this->validateWidgetAccess($user, $widgetId, $dashboardType)) {
                return response()->json([
                    'error' => 'Access denied',
                    'message' => 'You do not have permission to access this widget'
                ], 403);
            }

            // Create widget instance and get performance metrics
            $widget = $this->widgetFactory->create($widgetId, $user, [], $gatewayId);
            
            if (!$widget) {
                return response()->json([
                    'error' => 'Widget not found',
                    'message' => 'The requested widget could not be created'
                ], 404);
            }

            $performanceMetrics = $this->widgetFactory->getWidgetPerformanceMetrics($widget);

            return response()->json([
                'success' => true,
                'widget_id' => $widgetId,
                'performance' => $performanceMetrics
            ]);

        } catch (\Exception $e) {
            Log::error('Get widget performance error', [
                'user_id' => $request->user()?->id,
                'dashboard_type' => $request->input('dashboard_type'),
                'widget_id' => $request->input('widget_id'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to get widget performance metrics',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear widget cache
     */
    public function clearWidgetCache(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'dashboard_type' => 'required|in:global,gateway',
                'widget_id' => 'nullable|string',
                'gateway_id' => 'nullable|integer|exists:gateways,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $user = $request->user();
            $dashboardType = $request->input('dashboard_type');
            $widgetId = $request->input('widget_id');
            $gatewayId = $request->input('gateway_id');

            if ($widgetId) {
                // Clear cache for specific widget
                if (!$this->validateWidgetAccess($user, $widgetId, $dashboardType)) {
                    return response()->json([
                        'error' => 'Access denied',
                        'message' => 'You do not have permission to access this widget'
                    ], 403);
                }

                $widget = $this->widgetFactory->create($widgetId, $user, [], $gatewayId);
                if ($widget) {
                    $widget->clearCache();
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Widget cache cleared successfully',
                    'widget_id' => $widgetId
                ]);
            } else {
                // Clear cache for all authorized widgets
                $widgets = $this->widgetFactory->getAuthorizedWidgets($user, $dashboardType, $gatewayId);
                $this->widgetFactory->clearWidgetsCache($widgets);

                return response()->json([
                    'success' => true,
                    'message' => 'All widget caches cleared successfully',
                    'cleared_count' => count($widgets)
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Clear widget cache error', [
                'user_id' => $request->user()?->id,
                'dashboard_type' => $request->input('dashboard_type'),
                'widget_id' => $request->input('widget_id'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to clear widget cache',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get authorized gateways for current user
     */
    public function getAuthorizedGateways(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $user = $request->user();
            
            // Get authorized gateways with device counts and status
            $gateways = $this->permissionService->getAuthorizedGateways($user->id)
                ->with(['devices' => function($query) use ($user) {
                    // Only count devices the user has access to
                    $authorizedDeviceIds = $this->permissionService->getAuthorizedDevices($user->id)->pluck('id');
                    $query->whereIn('id', $authorizedDeviceIds);
                }])
                ->get()
                ->map(function($gateway) {
                    return [
                        'id' => $gateway->id,
                        'name' => $gateway->name,
                        'status' => $gateway->communication_status ?? 'unknown',
                        'location' => $gateway->gnss_location,
                        'device_count' => $gateway->devices->count(),
                        'last_communication' => $gateway->last_communication,
                        'ip_address' => $gateway->ip_address,
                        'port' => $gateway->port
                    ];
                });
            
            return response()->json([
                'success' => true,
                'gateways' => $gateways
            ]);
            
        } catch (\Exception $e) {
            Log::error('Get authorized gateways error', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to load gateways',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate that user has access to a specific widget
     */
    protected function validateWidgetAccess($user, string $widgetId, string $dashboardType): bool
    {
        // Check if widget is registered
        if (!$this->widgetFactory->isRegistered($widgetId)) {
            return false;
        }

        // Check if user can access this widget type
        $config = ['dashboard_type' => $dashboardType];
        return $this->permissionService->canAccessWidget($user, $widgetId, $config);
    }
}