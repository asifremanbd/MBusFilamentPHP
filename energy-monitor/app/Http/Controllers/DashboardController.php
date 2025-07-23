<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\Gateway;
use App\Services\WidgetFactory;
use App\Services\UserPermissionService;
use App\Services\DashboardConfigService;
use App\Models\DashboardAccessLog;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    protected WidgetFactory $widgetFactory;
    protected UserPermissionService $permissionService;
    protected DashboardConfigService $configService;

    public function __construct(
        WidgetFactory $widgetFactory,
        UserPermissionService $permissionService,
        DashboardConfigService $configService
    ) {
        $this->widgetFactory = $widgetFactory;
        $this->permissionService = $permissionService;
        $this->configService = $configService;
    }

    /**
     * Display the global dashboard
     */
    public function globalDashboard(Request $request): Response
    {
        try {
            $user = $request->user();
            
            // Log dashboard access
            DashboardAccessLog::logAccess(
                $user->id,
                'global',
                true,
                $request->ip(),
                $request->userAgent()
            );

            // Get authorized gateways for this user
            $authorizedGateways = $this->getAuthorizedGateways($user);
            
            // Get user's dashboard configuration
            $dashboardConfig = $this->getUserDashboardConfig($user, 'global');
            
            // Get authorized widgets for global dashboard
            $widgets = $this->getAuthorizedWidgets($user, 'global');

            return response()->view('dashboard.global', [
                'gateways' => $authorizedGateways,
                'widgets' => $widgets,
                'config' => $dashboardConfig,
                'user' => $user,
                'dashboard_type' => 'global',
            ]);

        } catch (\Exception $e) {
            Log::error('Global dashboard error', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->view('dashboard.error', [
                'error' => 'Failed to load global dashboard',
                'message' => 'Please try refreshing the page or contact support if the problem persists.'
            ], 500);
        }
    }

    /**
     * Display a gateway-specific dashboard
     */
    public function gatewayDashboard(Request $request, int $gatewayId): Response
    {
        try {
            $user = $request->user();
            
            // Find and authorize gateway access
            $gateway = Gateway::findOrFail($gatewayId);
            $this->authorize('view', $gateway);

            // Log dashboard access
            DashboardAccessLog::logAccess(
                $user->id,
                'gateway',
                true,
                $request->ip(),
                $request->userAgent(),
                $gatewayId
            );

            // Get authorized gateway and devices
            $authorizedGateway = $this->getAuthorizedGateway($user, $gatewayId);
            $authorizedDevices = $this->getAuthorizedDevices($user, $gatewayId);
            
            // Get user's dashboard configuration
            $dashboardConfig = $this->getUserDashboardConfig($user, 'gateway');
            
            // Get authorized widgets for gateway dashboard
            $widgets = $this->getAuthorizedWidgets($user, 'gateway', $gatewayId);

            return response()->view('dashboard.gateway', [
                'gateway' => $authorizedGateway,
                'devices' => $authorizedDevices,
                'widgets' => $widgets,
                'config' => $dashboardConfig,
                'user' => $user,
                'dashboard_type' => 'gateway',
                'gateway_id' => $gatewayId,
            ]);

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            // Log unauthorized access attempt
            DashboardAccessLog::logAccess(
                $request->user()?->id ?? 0,
                'gateway',
                false,
                $request->ip(),
                $request->userAgent(),
                $gatewayId
            );

            return response()->view('dashboard.unauthorized', [
                'error' => 'Access Denied',
                'message' => 'You do not have permission to access this gateway dashboard.',
                'suggested_action' => $this->getSuggestedAction($request->user(), 'gateway')
            ], 403);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->view('dashboard.not-found', [
                'error' => 'Gateway Not Found',
                'message' => 'The requested gateway does not exist.',
                'gateway_id' => $gatewayId
            ], 404);

        } catch (\Exception $e) {
            Log::error('Gateway dashboard error', [
                'user_id' => $request->user()?->id,
                'gateway_id' => $gatewayId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->view('dashboard.error', [
                'error' => 'Failed to load gateway dashboard',
                'message' => 'Please try refreshing the page or contact support if the problem persists.',
                'gateway_id' => $gatewayId
            ], 500);
        }
    }

    /**
     * Get dashboard data as JSON (for AJAX updates)
     */
    public function getDashboardData(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $user = $request->user();
            $dashboardType = $request->input('dashboard_type', 'global');
            $gatewayId = $request->input('gateway_id');

            // Validate dashboard type
            if (!in_array($dashboardType, ['global', 'gateway'])) {
                return response()->json([
                    'error' => 'Invalid dashboard type'
                ], 400);
            }

            // For gateway dashboard, validate gateway access
            if ($dashboardType === 'gateway') {
                if (!$gatewayId) {
                    return response()->json([
                        'error' => 'Gateway ID is required for gateway dashboard'
                    ], 400);
                }

                $gateway = Gateway::findOrFail($gatewayId);
                $this->authorize('view', $gateway);
            }

            // Get authorized widgets and render them
            $widgets = $this->getAuthorizedWidgets($user, $dashboardType, $gatewayId);
            $renderedWidgets = $this->widgetFactory->renderWidgets($widgets);

            return response()->json([
                'success' => true,
                'dashboard_type' => $dashboardType,
                'gateway_id' => $gatewayId,
                'widgets' => $renderedWidgets,
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Dashboard data error', [
                'user_id' => $request->user()?->id,
                'dashboard_type' => $request->input('dashboard_type'),
                'gateway_id' => $request->input('gateway_id'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to load dashboard data',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available gateways for the user
     */
    public function getAvailableGateways(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $user = $request->user();
            $authorizedGateways = $this->getAuthorizedGateways($user);

            $gateways = $authorizedGateways->map(function ($gateway) {
                return [
                    'id' => $gateway->id,
                    'name' => $gateway->name,
                    'location' => $gateway->gnss_location,
                    'device_count' => $gateway->devices()->count(),
                    'status' => $this->getGatewayStatus($gateway),
                ];
            });

            return response()->json([
                'success' => true,
                'gateways' => $gateways,
                'total_count' => $gateways->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Get available gateways error', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to load available gateways',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get authorized gateways for a user
     */
    protected function getAuthorizedGateways($user)
    {
        return $this->permissionService->getAuthorizedGateways($user);
    }

    /**
     * Get authorized gateway for a user
     */
    protected function getAuthorizedGateway($user, int $gatewayId)
    {
        $authorizedGateways = $this->getAuthorizedGateways($user);
        
        $gateway = $authorizedGateways->firstWhere('id', $gatewayId);
        
        if (!$gateway) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Access denied to this gateway');
        }
        
        return $gateway;
    }

    /**
     * Get authorized devices for a user and gateway
     */
    protected function getAuthorizedDevices($user, ?int $gatewayId = null)
    {
        return $this->permissionService->getAuthorizedDevices($user, $gatewayId);
    }

    /**
     * Get authorized widgets for a user and dashboard type
     */
    protected function getAuthorizedWidgets($user, string $dashboardType, ?int $gatewayId = null): array
    {
        return $this->widgetFactory->getAuthorizedWidgets($user, $dashboardType, $gatewayId);
    }

    /**
     * Get user dashboard configuration
     */
    protected function getUserDashboardConfig($user, string $dashboardType)
    {
        return $this->configService->getUserDashboardConfig($user, $dashboardType);
    }

    /**
     * Get gateway status
     */
    protected function getGatewayStatus($gateway): string
    {
        // Check if gateway has recent device readings
        $recentReadings = \App\Models\Reading::whereHas('device', function ($query) use ($gateway) {
            $query->where('gateway_id', $gateway->id);
        })->where('timestamp', '>=', now()->subMinutes(10))->count();

        if ($recentReadings > 0) {
            return 'online';
        }

        $oldReadings = \App\Models\Reading::whereHas('device', function ($query) use ($gateway) {
            $query->where('gateway_id', $gateway->id);
        })->where('timestamp', '>=', now()->subHour())->count();

        if ($oldReadings > 0) {
            return 'warning';
        }

        return 'offline';
    }

    /**
     * Get suggested action for unauthorized access
     */
    protected function getSuggestedAction($user, string $context): array
    {
        if (!$user) {
            return [
                'action' => 'login',
                'message' => 'Please log in to access the dashboard'
            ];
        }

        // Check if user has any device access
        if (!$this->permissionService->hasAnyDeviceAccess($user)) {
            return [
                'action' => 'contact_admin',
                'message' => 'Contact your administrator to request device access'
            ];
        }

        // If user has some access but not to this specific resource
        if ($context === 'gateway') {
            $authorizedGateways = $this->getAuthorizedGateways($user);
            if ($authorizedGateways->count() > 0) {
                return [
                    'action' => 'redirect',
                    'url' => route('dashboard.gateway', ['gateway' => $authorizedGateways->first()->id]),
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