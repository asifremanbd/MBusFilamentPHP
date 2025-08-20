<?php

namespace App\Http\Controllers;

use App\Models\Gateway;
use App\Services\RTUDataService;
use App\Services\RTUAlertService;
use App\Services\DashboardConfigService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class RTUDashboardController extends Controller
{
    use AuthorizesRequests;

    protected RTUDataService $rtuDataService;
    protected RTUAlertService $rtuAlertService;
    protected DashboardConfigService $configService;

    public function __construct(
        RTUDataService $rtuDataService,
        RTUAlertService $rtuAlertService,
        DashboardConfigService $configService
    ) {
        $this->rtuDataService = $rtuDataService;
        $this->rtuAlertService = $rtuAlertService;
        $this->configService = $configService;
    }

    /**
     * Display RTU-specific dashboard for a gateway
     */
    public function rtuDashboard(Request $request, Gateway $gateway): Response
    {
        try {
            $user = $request->user();
            
            // Authorize gateway access
            $this->authorize('view', $gateway);
            
            // Validate that this is an RTU gateway
            if (!$gateway->isRTUGateway()) {
                return response()->view('dashboard.error', [
                    'error' => 'Invalid Gateway Type',
                    'message' => 'This gateway is not configured as an RTU device. Please use the standard dashboard instead.',
                    'suggested_action' => [
                        'action' => 'redirect',
                        'url' => route('dashboard.gateway', $gateway),
                        'message' => 'Go to standard gateway dashboard'
                    ]
                ], 400);
            }

            // Get RTU dashboard configuration
            $dashboardConfig = $this->configService->getUserDashboardConfig($user, 'rtu');
            
            // Collect RTU-specific data
            $systemHealth = $this->rtuDataService->getSystemHealth($gateway);
            $networkStatus = $this->rtuDataService->getNetworkStatus($gateway);
            $ioStatus = $this->rtuDataService->getIOStatus($gateway);
            $groupedAlerts = $this->rtuAlertService->getGroupedAlerts($gateway);
            $trendData = $this->rtuDataService->getTrendData($gateway, '24h');

            Log::info('RTU dashboard accessed', [
                'user_id' => $user->id,
                'gateway_id' => $gateway->id,
                'gateway_name' => $gateway->name,
                'gateway_type' => $gateway->gateway_type
            ]);

            return response()->view('dashboard.rtu', [
                'gateway' => $gateway,
                'systemHealth' => $systemHealth,
                'networkStatus' => $networkStatus,
                'ioStatus' => $ioStatus,
                'groupedAlerts' => $groupedAlerts,
                'trendData' => $trendData,
                'config' => $dashboardConfig,
                'user' => $user,
                'dashboard_type' => 'rtu'
            ]);

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            Log::warning('Unauthorized RTU dashboard access attempt', [
                'user_id' => $request->user()?->id,
                'gateway_id' => $gateway->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            return response()->view('dashboard.unauthorized', [
                'error' => 'Access Denied',
                'message' => 'You do not have permission to access this RTU gateway dashboard.',
                'suggested_action' => [
                    'action' => 'contact_admin',
                    'message' => 'Contact your administrator to request RTU gateway access'
                ]
            ], 403);

        } catch (\Exception $e) {
            Log::error('RTU dashboard error', [
                'user_id' => $request->user()?->id,
                'gateway_id' => $gateway->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->view('dashboard.error', [
                'error' => 'Failed to load RTU dashboard',
                'message' => 'An error occurred while loading the RTU dashboard. Please try refreshing the page or contact support if the problem persists.',
                'gateway_id' => $gateway->id
            ], 500);
        }
    }

    /**
     * Update digital output state for RTU gateway
     */
    public function updateDigitalOutput(Request $request, Gateway $gateway, string $output): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Authorize gateway control access
            $this->authorize('control', $gateway);
            
            // Validate that this is an RTU gateway
            if (!$gateway->isRTUGateway()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid gateway type',
                    'message' => 'This gateway is not configured as an RTU device.'
                ], 400);
            }

            // Validate request data
            $validated = $request->validate([
                'state' => 'required|boolean'
            ]);

            // Validate output parameter
            if (!in_array($output, ['do1', 'do2'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid output',
                    'message' => 'Output must be either do1 or do2.'
                ], 400);
            }

            // Attempt to set digital output
            $result = $this->rtuDataService->setDigitalOutput($gateway, $output, $validated['state']);
            
            Log::info('RTU digital output control attempt', [
                'user_id' => $user->id,
                'gateway_id' => $gateway->id,
                'output' => $output,
                'desired_state' => $validated['state'],
                'success' => $result['success']
            ]);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => $result['message'],
                    'new_state' => $result['new_state'],
                    'output' => $output,
                    'timestamp' => now()->toISOString()
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Control operation failed',
                    'message' => $result['message'],
                    'error_type' => $result['error_type'] ?? 'unknown_error'
                ], 500);
            }

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            Log::warning('Unauthorized RTU control attempt', [
                'user_id' => $request->user()?->id,
                'gateway_id' => $gateway->id,
                'output' => $output,
                'ip_address' => $request->ip()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Access denied',
                'message' => 'You do not have permission to control this RTU gateway.'
            ], 403);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'message' => 'Invalid request data.',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('RTU digital output control error', [
                'user_id' => $request->user()?->id,
                'gateway_id' => $gateway->id,
                'output' => $output,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Internal server error',
                'message' => 'An error occurred while controlling the digital output. Please try again or contact support.'
            ], 500);
        }
    }

    /**
     * Get RTU dashboard data as JSON (for AJAX updates)
     */
    public function getRTUDashboardData(Request $request, Gateway $gateway): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Authorize gateway access
            $this->authorize('view', $gateway);
            
            // Validate that this is an RTU gateway
            if (!$gateway->isRTUGateway()) {
                return response()->json([
                    'error' => 'Invalid gateway type',
                    'message' => 'This gateway is not configured as an RTU device.'
                ], 400);
            }

            // Get requested data sections (default to all)
            $sections = $request->input('sections', ['system', 'network', 'io', 'alerts', 'trends']);
            $timeRange = $request->input('time_range', '24h');

            $data = [];

            // Collect requested data sections
            if (in_array('system', $sections)) {
                $data['systemHealth'] = $this->rtuDataService->getSystemHealth($gateway);
            }

            if (in_array('network', $sections)) {
                $data['networkStatus'] = $this->rtuDataService->getNetworkStatus($gateway);
            }

            if (in_array('io', $sections)) {
                $data['ioStatus'] = $this->rtuDataService->getIOStatus($gateway);
            }

            if (in_array('alerts', $sections)) {
                $data['groupedAlerts'] = $this->rtuAlertService->getGroupedAlerts($gateway);
            }

            if (in_array('trends', $sections)) {
                $data['trendData'] = $this->rtuDataService->getTrendData($gateway, $timeRange);
            }

            return response()->json([
                'success' => true,
                'gateway_id' => $gateway->id,
                'gateway_name' => $gateway->name,
                'data' => $data,
                'timestamp' => now()->toISOString(),
                'sections_requested' => $sections
            ]);

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'error' => 'Access denied',
                'message' => 'You do not have permission to access this RTU gateway data.'
            ], 403);

        } catch (\Exception $e) {
            Log::error('RTU dashboard data error', [
                'user_id' => $request->user()?->id,
                'gateway_id' => $gateway->id,
                'sections' => $request->input('sections', []),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to load RTU dashboard data',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get RTU gateway status summary
     */
    public function getRTUStatus(Request $request, Gateway $gateway): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Authorize gateway access
            $this->authorize('view', $gateway);
            
            // Validate that this is an RTU gateway
            if (!$gateway->isRTUGateway()) {
                return response()->json([
                    'error' => 'Invalid gateway type',
                    'message' => 'This gateway is not configured as an RTU device.'
                ], 400);
            }

            // Get basic status information
            $systemHealth = $this->rtuDataService->getSystemHealth($gateway);
            $networkStatus = $this->rtuDataService->getNetworkStatus($gateway);
            $alertStats = $this->rtuAlertService->getAlertStats($gateway);

            return response()->json([
                'success' => true,
                'gateway_id' => $gateway->id,
                'gateway_name' => $gateway->name,
                'status' => [
                    'overall_health' => $systemHealth['health_score'],
                    'system_status' => $systemHealth['status'],
                    'connection_status' => $networkStatus['connection_status'],
                    'signal_quality' => $networkStatus['signal_quality']['status'],
                    'alert_summary' => $alertStats['status_summary'],
                    'critical_alerts' => $alertStats['critical'],
                    'active_alerts' => $alertStats['active']
                ],
                'last_updated' => $gateway->last_system_update,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'error' => 'Access denied',
                'message' => 'You do not have permission to access this RTU gateway status.'
            ], 403);

        } catch (\Exception $e) {
            Log::error('RTU status error', [
                'user_id' => $request->user()?->id,
                'gateway_id' => $gateway->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to load RTU status',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Filter RTU alerts based on provided criteria
     */
    public function filterAlerts(Request $request, Gateway $gateway): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Authorize gateway access
            $this->authorize('view', $gateway);
            
            // Validate that this is an RTU gateway
            if (!$gateway->isRTUGateway()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid gateway type',
                    'message' => 'This gateway is not configured as an RTU device.'
                ], 400);
            }

            // Validate request data
            $validated = $request->validate([
                'filters' => 'required|array',
                'filters.device_ids' => 'sometimes|array',
                'filters.device_ids.*' => 'integer|exists:devices,id',
                'filters.severity' => 'sometimes|array',
                'filters.severity.*' => 'string|in:critical,warning,info',
                'filters.time_range' => 'sometimes|string|in:last_hour,last_day,last_week,custom',
                'filters.start_date' => 'sometimes|date',
                'filters.end_date' => 'sometimes|date|after_or_equal:filters.start_date'
            ]);

            $filters = $validated['filters'];

            // Get filtered alerts
            $filteredAlerts = $this->rtuAlertService->getFilteredAlerts($gateway, $filters);
            
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
            
            Log::info('RTU alerts filtered', [
                'user_id' => $user->id,
                'gateway_id' => $gateway->id,
                'filters_applied' => $filters,
                'results_count' => $filteredAlerts->count()
            ]);
            
            return response()->json([
                'success' => true,
                'html' => $html,
                'counts' => [
                    'critical' => $criticalCount,
                    'warning' => $warningCount,
                    'info' => $infoCount,
                    'total' => $filteredAlerts->count()
                ],
                'filters_applied' => $filters,
                'timestamp' => now()->toISOString()
            ]);
            
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Access denied',
                'message' => 'You do not have permission to access this RTU gateway alerts.'
            ], 403);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'message' => 'Invalid filter parameters.',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('RTU alerts filtering error', [
                'user_id' => $request->user()?->id,
                'gateway_id' => $gateway->id,
                'filters' => $request->input('filters', []),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Internal server error',
                'message' => 'An error occurred while filtering alerts. Please try again or contact support.'
            ], 500);
        }
    }

    /**
     * Get status summary based on alert counts
     */
    private function getStatusSummary(int $criticalCount, int $warningCount): string
    {
        if ($criticalCount > 0) {
            return $criticalCount === 1 ? '1 Critical Alert' : "{$criticalCount} Critical Alerts";
        }
        
        if ($warningCount > 0) {
            return $warningCount === 1 ? '1 Warning' : "{$warningCount} Warnings";
        }
        
        return 'All Systems OK';
    }
}