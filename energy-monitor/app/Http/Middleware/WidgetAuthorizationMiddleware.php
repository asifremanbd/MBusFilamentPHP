<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\UserPermissionService;
use App\Models\DashboardAccessLog;
use Illuminate\Support\Facades\Log;

class WidgetAuthorizationMiddleware
{
    protected UserPermissionService $permissionService;

    public function __construct(UserPermissionService $permissionService)
    {
        $this->permissionService = $permissionService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $widgetType = null): Response
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Authentication required'
            ], 401);
        }

        // Get widget type from route parameter or middleware parameter
        $widgetType = $widgetType ?? $request->route('widget_type') ?? $request->input('widget_type');
        
        if (!$widgetType) {
            return response()->json([
                'error' => 'Bad Request',
                'message' => 'Widget type is required'
            ], 400);
        }

        // Get widget configuration from request
        $widgetConfig = $this->extractWidgetConfig($request);

        // Check if user can access this widget
        $canAccess = $this->permissionService->canAccessWidget($user, $widgetType, $widgetConfig);

        // Log the access attempt
        $this->logWidgetAccess($request, $user, $widgetType, $canAccess, $widgetConfig);

        if (!$canAccess) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'You do not have permission to access this widget',
                'widget_type' => $widgetType,
                'fallback_action' => $this->getFallbackAction($user, $widgetType)
            ], 403);
        }

        // Add widget information to request for downstream use
        $request->merge([
            'widget_type' => $widgetType,
            'widget_config' => $widgetConfig,
            'user_permissions' => [
                'can_access_widget' => true,
                'authorized_gateways' => $this->permissionService->getAuthorizedGateways($user)->pluck('id')->toArray(),
                'authorized_devices' => $this->permissionService->getAuthorizedDevices($user)->pluck('id')->toArray(),
            ]
        ]);

        return $next($request);
    }

    /**
     * Extract widget configuration from request
     */
    protected function extractWidgetConfig(Request $request): array
    {
        $config = [];

        // Extract gateway ID
        if ($request->has('gateway_id')) {
            $config['gateway_id'] = (int) $request->input('gateway_id');
        }

        // Extract device IDs
        if ($request->has('device_ids')) {
            $deviceIds = $request->input('device_ids');
            if (is_string($deviceIds)) {
                $deviceIds = explode(',', $deviceIds);
            }
            $config['device_ids'] = array_map('intval', (array) $deviceIds);
        }

        // Extract other widget-specific parameters
        $widgetParams = $request->only([
            'time_range',
            'limit',
            'severity',
            'status',
            'category',
            'sort_by',
            'sort_order'
        ]);

        return array_merge($config, array_filter($widgetParams));
    }

    /**
     * Log widget access attempt
     */
    protected function logWidgetAccess(Request $request, $user, string $widgetType, bool $canAccess, array $widgetConfig): void
    {
        try {
            DashboardAccessLog::logAccess(
                $user->id,
                $this->getDashboardType($widgetType),
                $canAccess,
                $request->ip(),
                $request->userAgent(),
                $widgetConfig['gateway_id'] ?? null,
                $widgetType
            );
        } catch (\Exception $e) {
            Log::error('Failed to log widget access', [
                'user_id' => $user->id,
                'widget_type' => $widgetType,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Determine dashboard type from widget type
     */
    protected function getDashboardType(string $widgetType): string
    {
        $globalWidgets = [
            'system-overview',
            'cross-gateway-alerts',
            'top-consuming-gateways',
            'system-health'
        ];

        return in_array($widgetType, $globalWidgets) ? 'global' : 'gateway';
    }

    /**
     * Get fallback action for unauthorized access
     */
    protected function getFallbackAction($user, string $widgetType): ?array
    {
        // If user has no device access at all, suggest contacting admin
        if (!$this->permissionService->hasAnyDeviceAccess($user)) {
            return [
                'action' => 'contact_admin',
                'message' => 'Contact your administrator to request device access'
            ];
        }

        // If user has some access but not to this specific widget, suggest alternatives
        $dashboardType = $this->getDashboardType($widgetType);
        
        if ($dashboardType === 'global' && $this->permissionService->hasAnyGatewayAccess($user)) {
            return [
                'action' => 'redirect',
                'url' => '/dashboard/gateway',
                'message' => 'Try viewing individual gateway dashboards instead'
            ];
        }

        if ($dashboardType === 'gateway') {
            $authorizedGateways = $this->permissionService->getAuthorizedGateways($user);
            if ($authorizedGateways->count() > 0) {
                return [
                    'action' => 'redirect',
                    'url' => '/dashboard/gateway/' . $authorizedGateways->first()->id,
                    'message' => 'Try viewing a different gateway dashboard'
                ];
            }
        }

        return [
            'action' => 'refresh',
            'message' => 'Your permissions may have changed. Try refreshing the page.'
        ];
    }
}