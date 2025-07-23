<?php

namespace App\Services;

use App\Models\User;
use App\Services\UserPermissionService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;
use Throwable;

class DashboardErrorHandler
{
    protected UserPermissionService $permissionService;

    public function __construct(UserPermissionService $permissionService)
    {
        $this->permissionService = $permissionService;
    }

    /**
     * Handle permission-related errors
     */
    public function handlePermissionError(User $user, string $resource, Exception $e): Response
    {
        Log::channel('security')->warning('Dashboard permission violation', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'user_role' => $user->role,
            'resource' => $resource,
            'error' => $e->getMessage(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->toISOString(),
        ]);

        $fallbackAction = $this->getFallbackAction($user, $resource);
        $errorContext = $this->buildErrorContext($user, $resource, $e);

        if (request()->expectsJson()) {
            return response()->json([
                'error' => 'Access denied',
                'message' => 'You do not have permission to access this resource',
                'resource' => $resource,
                'fallback_action' => $fallbackAction,
                'error_code' => 'PERMISSION_DENIED',
                'context' => $errorContext,
                'timestamp' => now()->toISOString(),
            ], 403);
        }

        return response()->view('dashboard.errors.permission-denied', [
            'error' => 'Access Denied',
            'message' => 'You do not have permission to access this resource',
            'resource' => $resource,
            'fallback_action' => $fallbackAction,
            'context' => $errorContext,
            'user' => $user,
        ], 403);
    }

    /**
     * Handle widget-specific errors
     */
    public function handleWidgetError(string $widgetId, Exception $e): array
    {
        Log::error('Widget loading error', [
            'widget_id' => $widgetId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'user_id' => auth()->user()?->id,
            'timestamp' => now()->toISOString(),
        ]);

        $fallbackData = $this->getWidgetFallbackData($widgetId);
        $errorSeverity = $this->determineErrorSeverity($e);
        $retryStrategy = $this->getRetryStrategy($widgetId, $e);

        return [
            'widget_id' => $widgetId,
            'status' => 'error',
            'error_type' => $this->classifyError($e),
            'error_severity' => $errorSeverity,
            'message' => $this->getUserFriendlyErrorMessage($e, $widgetId),
            'technical_message' => app()->environment('local') ? $e->getMessage() : null,
            'fallback_data' => $fallbackData,
            'retry_strategy' => $retryStrategy,
            'error_code' => $this->generateErrorCode($widgetId, $e),
            'timestamp' => now()->toISOString(),
            'metadata' => [
                'widget_id' => $widgetId,
                'user_id' => auth()->user()?->id,
                'retry_available' => $retryStrategy['available'],
                'fallback_available' => !empty($fallbackData),
            ]
        ];
    }

    /**
     * Handle network-related errors
     */
    public function handleNetworkError(string $operation, Exception $e): array
    {
        Log::warning('Network error in dashboard operation', [
            'operation' => $operation,
            'error' => $e->getMessage(),
            'user_id' => auth()->user()?->id,
            'timestamp' => now()->toISOString(),
        ]);

        $retryStrategy = $this->getNetworkRetryStrategy($operation, $e);
        $offlineData = $this->getOfflineData($operation);

        return [
            'status' => 'network_error',
            'operation' => $operation,
            'error_type' => 'network',
            'message' => 'Network connectivity issue detected',
            'user_message' => $this->getNetworkErrorMessage($e),
            'retry_strategy' => $retryStrategy,
            'offline_data' => $offlineData,
            'error_code' => 'NETWORK_ERROR',
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Handle database-related errors
     */
    public function handleDatabaseError(string $operation, Exception $e): array
    {
        Log::error('Database error in dashboard operation', [
            'operation' => $operation,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'user_id' => auth()->user()?->id,
            'timestamp' => now()->toISOString(),
        ]);

        $cachedData = $this->getCachedData($operation);
        $retryStrategy = $this->getDatabaseRetryStrategy($operation, $e);

        return [
            'status' => 'database_error',
            'operation' => $operation,
            'error_type' => 'database',
            'message' => 'Database connectivity issue',
            'user_message' => 'We are experiencing technical difficulties. Please try again in a moment.',
            'cached_data' => $cachedData,
            'retry_strategy' => $retryStrategy,
            'error_code' => 'DATABASE_ERROR',
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Handle general application errors
     */
    public function handleApplicationError(string $context, Throwable $e): array
    {
        Log::error('Application error in dashboard', [
            'context' => $context,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'user_id' => auth()->user()?->id,
            'timestamp' => now()->toISOString(),
        ]);

        return [
            'status' => 'application_error',
            'context' => $context,
            'error_type' => 'application',
            'message' => 'An unexpected error occurred',
            'user_message' => 'We encountered an unexpected issue. Our team has been notified.',
            'error_code' => 'APPLICATION_ERROR',
            'support_reference' => $this->generateSupportReference(),
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Implement graceful widget degradation
     */
    public function degradeWidget(string $widgetId, Exception $e): array
    {
        $degradationLevel = $this->determineDegradationLevel($e);
        $fallbackData = $this->getWidgetFallbackData($widgetId);

        Log::info('Widget degradation applied', [
            'widget_id' => $widgetId,
            'degradation_level' => $degradationLevel,
            'error' => $e->getMessage(),
            'user_id' => auth()->user()?->id,
        ]);

        return match($degradationLevel) {
            'minimal' => $this->applyMinimalDegradation($widgetId, $fallbackData),
            'partial' => $this->applyPartialDegradation($widgetId, $fallbackData),
            'full' => $this->applyFullDegradation($widgetId, $fallbackData),
            default => $this->applyFullDegradation($widgetId, $fallbackData),
        };
    }

    /**
     * Get fallback action for permission errors
     */
    protected function getFallbackAction(User $user, string $resource): array
    {
        // If user has no device access at all, suggest contacting admin
        if (!$this->permissionService->hasAnyDeviceAccess($user)) {
            return [
                'action' => 'contact_admin',
                'message' => 'Contact your administrator to request device access',
                'contact_info' => config('app.admin_contact', 'system administrator'),
            ];
        }

        // If user has some access but not to this specific resource
        if (str_contains($resource, 'gateway')) {
            $authorizedGateways = $this->permissionService->getAuthorizedGateways($user);
            if ($authorizedGateways->count() > 0) {
                return [
                    'action' => 'redirect',
                    'url' => route('dashboard.gateway', ['gateway' => $authorizedGateways->first()->id]),
                    'message' => 'Try viewing a different gateway dashboard',
                    'available_gateways' => $authorizedGateways->take(3)->pluck('name', 'id')->toArray(),
                ];
            }
        }

        if (str_contains($resource, 'global')) {
            return [
                'action' => 'redirect',
                'url' => route('dashboard.global'),
                'message' => 'Try the global dashboard instead',
            ];
        }

        return [
            'action' => 'refresh',
            'message' => 'Your permissions may have changed. Try refreshing the page.',
            'refresh_url' => request()->url(),
        ];
    }

    /**
     * Build error context for debugging
     */
    protected function buildErrorContext(User $user, string $resource, Exception $e): array
    {
        return [
            'user_permissions' => [
                'role' => $user->role,
                'is_admin' => $user->isAdmin(),
                'gateway_count' => $this->permissionService->getAuthorizedGateways($user)->count(),
                'device_count' => $this->permissionService->getAuthorizedDevices($user)->count(),
            ],
            'request_info' => [
                'url' => request()->url(),
                'method' => request()->method(),
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ],
            'error_details' => [
                'type' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ],
        ];
    }

    /**
     * Get widget fallback data
     */
    protected function getWidgetFallbackData(string $widgetId): array
    {
        $fallbackData = match($widgetId) {
            'system-overview' => [
                'total_energy_consumption' => ['current_kw' => 0, 'total_kwh' => 0],
                'active_devices_count' => ['total' => 0, 'active' => 0],
                'critical_alerts_count' => ['critical' => 0, 'warning' => 0],
                'system_health_score' => ['score' => 0, 'status' => 'unknown'],
            ],
            'cross-gateway-alerts' => [
                'critical_alerts' => [],
                'warning_alerts' => [],
                'recent_alerts' => [],
            ],
            'top-consuming-gateways' => [
                'top_gateways' => [],
                'consumption_comparison' => [],
            ],
            'system-health' => [
                'overall_health' => ['score' => 0, 'status' => 'unknown'],
                'component_health' => [],
            ],
            'gateway-device-list' => [
                'devices' => [],
                'device_summary' => ['total_devices' => 0, 'online_devices' => 0],
            ],
            'real-time-readings' => [
                'live_readings' => [],
                'reading_trends' => [],
            ],
            'gateway-stats' => [
                'gateway_info' => ['status' => 'unknown'],
                'communication_status' => ['overall_status' => 'unknown'],
            ],
            'gateway-alerts' => [
                'active_alerts' => [],
                'alert_summary' => ['total_active' => 0],
            ],
            default => [],
        };

        // Add metadata to fallback data
        $fallbackData['_fallback'] = [
            'is_fallback' => true,
            'widget_id' => $widgetId,
            'generated_at' => now()->toISOString(),
            'reason' => 'Widget error - displaying fallback data',
        ];

        return $fallbackData;
    }

    /**
     * Classify error type
     */
    protected function classifyError(Exception $e): string
    {
        $errorClass = get_class($e);
        
        return match(true) {
            str_contains($errorClass, 'Permission') || str_contains($errorClass, 'Authorization') => 'permission',
            str_contains($errorClass, 'Database') || str_contains($errorClass, 'Query') => 'database',
            str_contains($errorClass, 'Network') || str_contains($errorClass, 'Connection') => 'network',
            str_contains($errorClass, 'Timeout') => 'timeout',
            str_contains($errorClass, 'Validation') => 'validation',
            default => 'application',
        };
    }

    /**
     * Determine error severity
     */
    protected function determineErrorSeverity(Exception $e): string
    {
        $errorType = $this->classifyError($e);
        
        return match($errorType) {
            'permission' => 'high',
            'database' => 'high',
            'network' => 'medium',
            'timeout' => 'medium',
            'validation' => 'low',
            default => 'medium',
        };
    }

    /**
     * Get retry strategy for widgets
     */
    protected function getRetryStrategy(string $widgetId, Exception $e): array
    {
        $errorType = $this->classifyError($e);
        
        return match($errorType) {
            'network', 'timeout' => [
                'available' => true,
                'max_attempts' => 3,
                'delay_seconds' => 5,
                'backoff_multiplier' => 2,
                'strategy' => 'exponential_backoff',
            ],
            'database' => [
                'available' => true,
                'max_attempts' => 2,
                'delay_seconds' => 10,
                'strategy' => 'fixed_delay',
            ],
            'permission' => [
                'available' => false,
                'reason' => 'Permission errors cannot be retried automatically',
            ],
            default => [
                'available' => true,
                'max_attempts' => 1,
                'delay_seconds' => 30,
                'strategy' => 'single_retry',
            ],
        };
    }

    /**
     * Get network retry strategy
     */
    protected function getNetworkRetryStrategy(string $operation, Exception $e): array
    {
        return [
            'available' => true,
            'max_attempts' => 5,
            'delay_seconds' => 2,
            'backoff_multiplier' => 1.5,
            'strategy' => 'exponential_backoff',
            'timeout_seconds' => 30,
        ];
    }

    /**
     * Get database retry strategy
     */
    protected function getDatabaseRetryStrategy(string $operation, Exception $e): array
    {
        return [
            'available' => true,
            'max_attempts' => 3,
            'delay_seconds' => 5,
            'strategy' => 'fixed_delay',
            'circuit_breaker' => true,
        ];
    }

    /**
     * Get user-friendly error message
     */
    protected function getUserFriendlyErrorMessage(Exception $e, string $widgetId): string
    {
        $errorType = $this->classifyError($e);
        
        return match($errorType) {
            'permission' => 'You do not have permission to view this information.',
            'database' => 'We are experiencing technical difficulties. Please try again in a moment.',
            'network' => 'Unable to connect to the server. Please check your internet connection.',
            'timeout' => 'The request took too long to complete. Please try again.',
            'validation' => 'Invalid data was provided. Please check your input.',
            default => 'An unexpected error occurred while loading this widget.',
        };
    }

    /**
     * Get network error message
     */
    protected function getNetworkErrorMessage(Exception $e): string
    {
        if (str_contains($e->getMessage(), 'timeout')) {
            return 'The connection timed out. Please check your internet connection and try again.';
        }
        
        if (str_contains($e->getMessage(), 'refused')) {
            return 'Unable to connect to the server. The service may be temporarily unavailable.';
        }
        
        return 'Network connectivity issue. Please check your connection and try again.';
    }

    /**
     * Generate error code
     */
    protected function generateErrorCode(string $widgetId, Exception $e): string
    {
        $errorType = strtoupper($this->classifyError($e));
        $widgetCode = strtoupper(str_replace('-', '_', $widgetId));
        $timestamp = now()->format('YmdHis');
        
        return "{$errorType}_{$widgetCode}_{$timestamp}";
    }

    /**
     * Generate support reference
     */
    protected function generateSupportReference(): string
    {
        return 'REF-' . now()->format('YmdHis') . '-' . substr(md5(uniqid()), 0, 8);
    }

    /**
     * Determine degradation level
     */
    protected function determineDegradationLevel(Exception $e): string
    {
        $errorType = $this->classifyError($e);
        
        return match($errorType) {
            'network', 'timeout' => 'minimal',
            'database' => 'partial',
            'permission' => 'full',
            default => 'partial',
        };
    }

    /**
     * Apply minimal degradation
     */
    protected function applyMinimalDegradation(string $widgetId, array $fallbackData): array
    {
        return [
            'widget_id' => $widgetId,
            'status' => 'degraded_minimal',
            'message' => 'Some data may be outdated due to connectivity issues',
            'data' => $fallbackData,
            'degradation_info' => [
                'level' => 'minimal',
                'description' => 'Showing cached or fallback data',
                'retry_available' => true,
            ],
        ];
    }

    /**
     * Apply partial degradation
     */
    protected function applyPartialDegradation(string $widgetId, array $fallbackData): array
    {
        return [
            'widget_id' => $widgetId,
            'status' => 'degraded_partial',
            'message' => 'Limited functionality available',
            'data' => $fallbackData,
            'degradation_info' => [
                'level' => 'partial',
                'description' => 'Some features are temporarily unavailable',
                'retry_available' => true,
            ],
        ];
    }

    /**
     * Apply full degradation
     */
    protected function applyFullDegradation(string $widgetId, array $fallbackData): array
    {
        return [
            'widget_id' => $widgetId,
            'status' => 'degraded_full',
            'message' => 'Widget temporarily unavailable',
            'data' => $fallbackData,
            'degradation_info' => [
                'level' => 'full',
                'description' => 'Widget functionality is currently unavailable',
                'retry_available' => false,
            ],
        ];
    }

    /**
     * Get offline data for operations
     */
    protected function getOfflineData(string $operation): array
    {
        $cacheKey = "offline_data_{$operation}";
        return Cache::get($cacheKey, []);
    }

    /**
     * Get cached data for operations
     */
    protected function getCachedData(string $operation): array
    {
        $cacheKey = "cached_data_{$operation}";
        return Cache::get($cacheKey, []);
    }

    /**
     * Create error response for API endpoints
     */
    public function createApiErrorResponse(Exception $e, string $context = 'api'): array
    {
        $errorType = $this->classifyError($e);
        $severity = $this->determineErrorSeverity($e);
        
        return [
            'success' => false,
            'error' => [
                'type' => $errorType,
                'severity' => $severity,
                'message' => $this->getUserFriendlyErrorMessage($e, $context),
                'code' => $this->generateErrorCode($context, $e),
                'context' => $context,
                'timestamp' => now()->toISOString(),
            ],
            'retry_info' => $this->getRetryStrategy($context, $e),
        ];
    }

    /**
     * Log error with appropriate level
     */
    public function logError(Exception $e, string $context, array $additionalData = []): void
    {
        $severity = $this->determineErrorSeverity($e);
        $logData = array_merge([
            'context' => $context,
            'error_type' => $this->classifyError($e),
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'user_id' => auth()->user()?->id,
            'timestamp' => now()->toISOString(),
        ], $additionalData);

        match($severity) {
            'high' => Log::error("High severity error in {$context}", $logData),
            'medium' => Log::warning("Medium severity error in {$context}", $logData),
            'low' => Log::info("Low severity error in {$context}", $logData),
            default => Log::error("Unknown severity error in {$context}", $logData),
        };
    }
}