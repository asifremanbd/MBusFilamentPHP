<?php

namespace App\Services;

use App\Models\Gateway;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class RTUWidgetErrorHandler
{
    /**
     * Handle data collection errors with graceful degradation
     */
    public function handleDataCollectionError(Gateway $gateway, string $dataType, Exception $e): array
    {
        Log::error('RTU data collection failed', [
            'gateway_id' => $gateway->id,
            'gateway_name' => $gateway->name,
            'data_type' => $dataType,
            'error' => $e->getMessage(),
            'gateway_type' => $gateway->gateway_type,
            'trace' => $e->getTraceAsString()
        ]);

        return [
            'status' => 'error',
            'error_type' => $this->classifyDataError($e),
            'message' => $this->getUserFriendlyDataMessage($dataType, $e),
            'fallback_data' => $this->getFallbackData($gateway, $dataType),
            'retry_available' => $this->shouldAllowRetry($e),
            'last_successful_update' => $gateway->last_system_update,
            'troubleshooting' => $this->getDataTroubleshootingSteps($dataType, $e),
            'cache_age' => $this->getCacheAge($gateway, $dataType)
        ];
    }

    /**
     * Handle I/O control operation errors
     */
    public function handleControlError(Gateway $gateway, string $operation, Exception $e): array
    {
        Log::error('RTU control operation failed', [
            'gateway_id' => $gateway->id,
            'gateway_name' => $gateway->name,
            'operation' => $operation,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return [
            'success' => false,
            'error_type' => $this->classifyControlError($e),
            'message' => $this->getUserFriendlyControlMessage($operation, $e),
            'retry_suggested' => $this->shouldSuggestRetry($e),
            'retry_delay' => $this->getRetryDelay($e),
            'troubleshooting_steps' => $this->getControlTroubleshootingSteps($operation, $e),
            'fallback_action' => $this->getFallbackAction($operation),
            'support_contact' => $this->getSupportContact($e)
        ];
    }

    /**
     * Get fallback data for temporarily unavailable RTU metrics
     */
    protected function getFallbackData(Gateway $gateway, string $dataType): array
    {
        $cacheKey = "rtu_fallback_{$gateway->id}_{$dataType}";
        $cachedData = Cache::get($cacheKey);

        if ($cachedData) {
            return array_merge($cachedData, [
                'is_cached' => true,
                'cache_timestamp' => $cachedData['timestamp'] ?? null
            ]);
        }

        // Return last known values from database
        return match($dataType) {
            'system_health' => [
                'uptime_hours' => $gateway->uptime_hours,
                'cpu_load' => $gateway->cpu_load,
                'memory_usage' => $gateway->memory_usage,
                'health_score' => 0,
                'status' => 'unavailable',
                'last_updated' => $gateway->last_system_update,
                'is_cached' => false,
                'fallback_source' => 'database'
            ],
            'network_status' => [
                'wan_ip' => $gateway->wan_ip ?? 'Data unavailable',
                'sim_iccid' => $gateway->sim_iccid ?? 'Data unavailable',
                'sim_apn' => $gateway->sim_apn ?? 'Data unavailable',
                'sim_operator' => $gateway->sim_operator ?? 'Data unavailable',
                'signal_quality' => [
                    'rssi' => $gateway->rssi,
                    'rsrp' => $gateway->rsrp,
                    'rsrq' => $gateway->rsrq,
                    'sinr' => $gateway->sinr,
                    'status' => 'unknown'
                ],
                'connection_status' => 'unavailable',
                'last_updated' => $gateway->last_system_update,
                'is_cached' => false,
                'fallback_source' => 'database'
            ],
            'io_status' => [
                'digital_inputs' => [
                    'di1' => ['status' => $gateway->di1_status, 'label' => 'Digital Input 1'],
                    'di2' => ['status' => $gateway->di2_status, 'label' => 'Digital Input 2']
                ],
                'digital_outputs' => [
                    'do1' => ['status' => $gateway->do1_status, 'label' => 'Digital Output 1', 'controllable' => false],
                    'do2' => ['status' => $gateway->do2_status, 'label' => 'Digital Output 2', 'controllable' => false]
                ],
                'analog_input' => [
                    'voltage' => $gateway->analog_input_voltage,
                    'unit' => 'V',
                    'range' => '0-10V',
                    'precision' => 2
                ],
                'last_updated' => $gateway->last_system_update,
                'is_cached' => false,
                'fallback_source' => 'database'
            ],
            default => [
                'status' => 'unavailable',
                'message' => 'No fallback data available',
                'is_cached' => false,
                'fallback_source' => 'none'
            ]
        };
    }

    /**
     * Classify data collection errors
     */
    protected function classifyDataError(Exception $e): string
    {
        $message = strtolower($e->getMessage());
        
        if (str_contains($message, 'timeout') || str_contains($message, 'connection timed out')) {
            return 'timeout';
        }
        
        if (str_contains($message, 'connection refused') || str_contains($message, 'unreachable')) {
            return 'connection_refused';
        }
        
        if (str_contains($message, 'authentication') || str_contains($message, 'unauthorized')) {
            return 'authentication';
        }
        
        if (str_contains($message, 'not found') || str_contains($message, '404')) {
            return 'not_found';
        }
        
        if (str_contains($message, 'invalid response') || str_contains($message, 'malformed')) {
            return 'invalid_response';
        }
        
        return 'unknown';
    }

    /**
     * Classify control operation errors
     */
    protected function classifyControlError(Exception $e): string
    {
        $message = strtolower($e->getMessage());
        
        if (str_contains($message, 'permission') || str_contains($message, 'forbidden')) {
            return 'permission_denied';
        }
        
        if (str_contains($message, 'hardware') || str_contains($message, 'module offline')) {
            return 'hardware_failure';
        }
        
        if (str_contains($message, 'invalid state') || str_contains($message, 'invalid value')) {
            return 'invalid_operation';
        }
        
        return $this->classifyDataError($e);
    }

    /**
     * Get user-friendly error messages for data collection failures
     */
    protected function getUserFriendlyDataMessage(string $dataType, Exception $e): string
    {
        $errorType = $this->classifyDataError($e);
        
        return match($errorType) {
            'timeout' => "Connection to RTU gateway timed out. Showing last known {$dataType} data.",
            'connection_refused' => "Unable to connect to RTU gateway. The device may be offline or unreachable.",
            'authentication' => "Authentication failed when connecting to RTU gateway. Please check credentials.",
            'not_found' => "RTU gateway endpoint not found. The device configuration may have changed.",
            'invalid_response' => "Received invalid response from RTU gateway. The device may be experiencing issues.",
            default => "Unable to retrieve {$dataType} data from RTU gateway. Showing cached information."
        };
    }

    /**
     * Get user-friendly error messages for control operations
     */
    protected function getUserFriendlyControlMessage(string $operation, Exception $e): string
    {
        $errorType = $this->classifyControlError($e);
        
        return match($errorType) {
            'permission_denied' => "You don't have permission to perform this control operation.",
            'hardware_failure' => "Hardware module is offline or not responding. Control operation failed.",
            'invalid_operation' => "Invalid control operation. Please check the requested state and try again.",
            'timeout' => "Control operation timed out. The command may not have been executed.",
            'connection_refused' => "Unable to connect to RTU gateway for control operation.",
            default => "Control operation failed: {$operation}. Please try again or contact support."
        };
    }

    /**
     * Determine if retry should be allowed
     */
    protected function shouldAllowRetry(Exception $e): bool
    {
        $errorType = $this->classifyDataError($e);
        
        return in_array($errorType, ['timeout', 'connection_refused', 'invalid_response']);
    }

    /**
     * Determine if retry should be suggested for control operations
     */
    protected function shouldSuggestRetry(Exception $e): bool
    {
        $errorType = $this->classifyControlError($e);
        
        return in_array($errorType, ['timeout', 'connection_refused', 'invalid_response']);
    }

    /**
     * Get retry delay in seconds
     */
    protected function getRetryDelay(Exception $e): int
    {
        $errorType = $this->classifyControlError($e);
        
        return match($errorType) {
            'timeout' => 30,
            'connection_refused' => 60,
            'invalid_response' => 15,
            default => 30
        };
    }

    /**
     * Get troubleshooting steps for data collection issues
     */
    protected function getDataTroubleshootingSteps(string $dataType, Exception $e): array
    {
        $errorType = $this->classifyDataError($e);
        
        $commonSteps = [
            'Check RTU gateway network connectivity',
            'Verify gateway IP address and port configuration',
            'Ensure gateway is powered on and operational'
        ];
        
        $specificSteps = match($errorType) {
            'timeout' => [
                'Check network latency to RTU gateway',
                'Verify firewall settings allow communication',
                'Consider increasing timeout values'
            ],
            'connection_refused' => [
                'Verify RTU gateway is online and accessible',
                'Check if gateway services are running',
                'Confirm network routing to gateway'
            ],
            'authentication' => [
                'Verify RTU gateway credentials',
                'Check if authentication method has changed',
                'Ensure user account has proper permissions'
            ],
            'not_found' => [
                'Verify RTU gateway API endpoints',
                'Check if gateway firmware has been updated',
                'Confirm device configuration matches expected format'
            ],
            default => [
                'Review RTU gateway logs for errors',
                'Check device documentation for known issues',
                'Contact technical support if problem persists'
            ]
        };
        
        return array_merge($commonSteps, $specificSteps);
    }

    /**
     * Get troubleshooting steps for control operation issues
     */
    protected function getControlTroubleshootingSteps(string $operation, Exception $e): array
    {
        $errorType = $this->classifyControlError($e);
        
        return match($errorType) {
            'permission_denied' => [
                'Verify user has control permissions for this gateway',
                'Check if control operations are enabled for this device',
                'Contact administrator to grant necessary permissions'
            ],
            'hardware_failure' => [
                'Check I/O module status on RTU gateway',
                'Verify physical connections to I/O terminals',
                'Restart RTU gateway if safe to do so',
                'Contact maintenance team for hardware inspection'
            ],
            'invalid_operation' => [
                'Verify the requested operation is supported',
                'Check if output is already in the requested state',
                'Ensure operation parameters are within valid range'
            ],
            default => array_merge(
                $this->getDataTroubleshootingSteps('control', $e),
                ['Wait a few moments and try the operation again']
            )
        };
    }

    /**
     * Get fallback action for failed control operations
     */
    protected function getFallbackAction(string $operation): ?string
    {
        return match($operation) {
            'do1', 'do2' => 'Manual control may be available directly on the RTU gateway device',
            default => 'Check RTU gateway web interface for manual control options'
        };
    }

    /**
     * Get support contact information based on error type
     */
    protected function getSupportContact(Exception $e): array
    {
        $errorType = $this->classifyControlError($e);
        
        $contacts = [
            'hardware_failure' => [
                'type' => 'maintenance',
                'message' => 'Contact maintenance team for hardware issues',
                'urgency' => 'high'
            ],
            'permission_denied' => [
                'type' => 'admin',
                'message' => 'Contact system administrator for access issues',
                'urgency' => 'medium'
            ]
        ];
        
        return $contacts[$errorType] ?? [
            'type' => 'technical',
            'message' => 'Contact technical support if issues persist',
            'urgency' => 'low'
        ];
    }

    /**
     * Get cache age for fallback data
     */
    protected function getCacheAge(Gateway $gateway, string $dataType): ?int
    {
        if (!$gateway->last_system_update) {
            return null;
        }
        
        return Carbon::now()->diffInMinutes($gateway->last_system_update);
    }

    /**
     * Cache successful data for fallback use
     */
    public function cacheSuccessfulData(Gateway $gateway, string $dataType, array $data): void
    {
        $cacheKey = "rtu_fallback_{$gateway->id}_{$dataType}";
        $cacheData = array_merge($data, ['timestamp' => now()]);
        
        // Cache for 1 hour
        Cache::put($cacheKey, $cacheData, 3600);
    }

    /**
     * Clear cached fallback data
     */
    public function clearFallbackCache(Gateway $gateway, ?string $dataType = null): void
    {
        if ($dataType) {
            $cacheKey = "rtu_fallback_{$gateway->id}_{$dataType}";
            Cache::forget($cacheKey);
        } else {
            // Clear all fallback cache for this gateway
            $dataTypes = ['system_health', 'network_status', 'io_status'];
            foreach ($dataTypes as $type) {
                $cacheKey = "rtu_fallback_{$gateway->id}_{type}";
                Cache::forget($cacheKey);
            }
        }
    }
}