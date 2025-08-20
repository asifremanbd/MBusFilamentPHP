<?php

namespace App\Services;

use App\Models\Gateway;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class RTURetryService
{
    protected RTUDataService $dataService;
    protected RTUWidgetErrorHandler $errorHandler;
    
    // Maximum retry attempts for different operations
    protected const MAX_DATA_RETRIES = 3;
    protected const MAX_CONTROL_RETRIES = 2;
    
    // Retry delays in seconds
    protected const RETRY_DELAYS = [
        'data_collection' => [5, 15, 30], // Progressive delays
        'control_operation' => [10, 30]   // Longer delays for control
    ];

    public function __construct(RTUDataService $dataService, RTUWidgetErrorHandler $errorHandler)
    {
        $this->dataService = $dataService;
        $this->errorHandler = $errorHandler;
    }

    /**
     * Retry data collection with exponential backoff
     */
    public function retryDataCollection(Gateway $gateway, string $dataType, int $attempt = 1): array
    {
        $cacheKey = "rtu_retry_{$gateway->id}_{$dataType}";
        
        // Check if we're already in a retry cycle to prevent concurrent retries
        if (Cache::has($cacheKey) && $attempt === 1) {
            return [
                'status' => 'retry_in_progress',
                'message' => 'Data collection retry already in progress',
                'fallback_data' => $this->errorHandler->getFallbackData($gateway, $dataType)
            ];
        }

        try {
            // Mark retry in progress
            Cache::put($cacheKey, $attempt, 300); // 5 minutes

            // Wait for retry delay if this is not the first attempt
            if ($attempt > 1) {
                $delay = self::RETRY_DELAYS['data_collection'][$attempt - 2] ?? 30;
                sleep($delay);
            }

            Log::info('RTU data collection retry attempt', [
                'gateway_id' => $gateway->id,
                'data_type' => $dataType,
                'attempt' => $attempt
            ]);

            // Attempt data collection based on type
            $result = match($dataType) {
                'system_health' => $this->dataService->getSystemHealth($gateway),
                'network_status' => $this->dataService->getNetworkStatus($gateway),
                'io_status' => $this->dataService->getIOStatus($gateway),
                default => throw new Exception("Unknown data type: {$dataType}")
            };

            // If successful, clear retry cache
            Cache::forget($cacheKey);
            
            Log::info('RTU data collection retry successful', [
                'gateway_id' => $gateway->id,
                'data_type' => $dataType,
                'attempt' => $attempt
            ]);

            return array_merge($result, [
                'retry_successful' => true,
                'retry_attempt' => $attempt
            ]);

        } catch (Exception $e) {
            Log::warning('RTU data collection retry failed', [
                'gateway_id' => $gateway->id,
                'data_type' => $dataType,
                'attempt' => $attempt,
                'error' => $e->getMessage()
            ]);

            // Check if we should retry again
            if ($attempt < self::MAX_DATA_RETRIES) {
                return $this->retryDataCollection($gateway, $dataType, $attempt + 1);
            }

            // All retries exhausted
            Cache::forget($cacheKey);
            
            $errorResult = $this->errorHandler->handleDataCollectionError($gateway, $dataType, $e);
            return array_merge($errorResult, [
                'retry_exhausted' => true,
                'total_attempts' => $attempt
            ]);
        }
    }

    /**
     * Retry control operation with limited attempts
     */
    public function retryControlOperation(Gateway $gateway, string $operation, bool $state, int $attempt = 1): array
    {
        $cacheKey = "rtu_control_retry_{$gateway->id}_{$operation}";
        
        // Check if we're already in a retry cycle
        if (Cache::has($cacheKey) && $attempt === 1) {
            return [
                'success' => false,
                'message' => 'Control operation retry already in progress',
                'retry_in_progress' => true
            ];
        }

        try {
            // Mark retry in progress
            Cache::put($cacheKey, $attempt, 180); // 3 minutes

            // Wait for retry delay if this is not the first attempt
            if ($attempt > 1) {
                $delay = self::RETRY_DELAYS['control_operation'][$attempt - 2] ?? 30;
                sleep($delay);
            }

            Log::info('RTU control operation retry attempt', [
                'gateway_id' => $gateway->id,
                'operation' => $operation,
                'state' => $state,
                'attempt' => $attempt
            ]);

            // Attempt control operation
            $result = $this->dataService->setDigitalOutput($gateway, $operation, $state);

            // If successful, clear retry cache
            if ($result['success']) {
                Cache::forget($cacheKey);
                
                Log::info('RTU control operation retry successful', [
                    'gateway_id' => $gateway->id,
                    'operation' => $operation,
                    'attempt' => $attempt
                ]);

                return array_merge($result, [
                    'retry_successful' => true,
                    'retry_attempt' => $attempt
                ]);
            } else {
                throw new Exception($result['message'] ?? 'Control operation failed');
            }

        } catch (Exception $e) {
            Log::warning('RTU control operation retry failed', [
                'gateway_id' => $gateway->id,
                'operation' => $operation,
                'attempt' => $attempt,
                'error' => $e->getMessage()
            ]);

            // Check if we should retry again
            if ($attempt < self::MAX_CONTROL_RETRIES) {
                return $this->retryControlOperation($gateway, $operation, $state, $attempt + 1);
            }

            // All retries exhausted
            Cache::forget($cacheKey);
            
            $errorResult = $this->errorHandler->handleControlError($gateway, $operation, $e);
            return array_merge($errorResult, [
                'retry_exhausted' => true,
                'total_attempts' => $attempt
            ]);
        }
    }

    /**
     * Check if retry is currently in progress for a gateway operation
     */
    public function isRetryInProgress(Gateway $gateway, string $operationType, ?string $operation = null): bool
    {
        if ($operationType === 'data') {
            $dataTypes = ['system_health', 'network_status', 'io_status'];
            foreach ($dataTypes as $dataType) {
                if (Cache::has("rtu_retry_{$gateway->id}_{$dataType}")) {
                    return true;
                }
            }
        } elseif ($operationType === 'control' && $operation) {
            return Cache::has("rtu_control_retry_{$gateway->id}_{$operation}");
        }

        return false;
    }

    /**
     * Cancel ongoing retry operations for a gateway
     */
    public function cancelRetries(Gateway $gateway): void
    {
        // Cancel data collection retries
        $dataTypes = ['system_health', 'network_status', 'io_status'];
        foreach ($dataTypes as $dataType) {
            Cache::forget("rtu_retry_{$gateway->id}_{$dataType}");
        }

        // Cancel control operation retries
        $controlOperations = ['do1', 'do2'];
        foreach ($controlOperations as $operation) {
            Cache::forget("rtu_control_retry_{$gateway->id}_{$operation}");
        }

        Log::info('RTU retry operations cancelled', [
            'gateway_id' => $gateway->id
        ]);
    }

    /**
     * Get retry status for a gateway
     */
    public function getRetryStatus(Gateway $gateway): array
    {
        $status = [
            'data_retries' => [],
            'control_retries' => [],
            'has_active_retries' => false
        ];

        // Check data collection retries
        $dataTypes = ['system_health', 'network_status', 'io_status'];
        foreach ($dataTypes as $dataType) {
            $cacheKey = "rtu_retry_{$gateway->id}_{$dataType}";
            if (Cache::has($cacheKey)) {
                $status['data_retries'][$dataType] = [
                    'attempt' => Cache::get($cacheKey),
                    'max_attempts' => self::MAX_DATA_RETRIES
                ];
                $status['has_active_retries'] = true;
            }
        }

        // Check control operation retries
        $controlOperations = ['do1', 'do2'];
        foreach ($controlOperations as $operation) {
            $cacheKey = "rtu_control_retry_{$gateway->id}_{$operation}";
            if (Cache::has($cacheKey)) {
                $status['control_retries'][$operation] = [
                    'attempt' => Cache::get($cacheKey),
                    'max_attempts' => self::MAX_CONTROL_RETRIES
                ];
                $status['has_active_retries'] = true;
            }
        }

        return $status;
    }

    /**
     * Schedule automatic retry for failed operations
     */
    public function scheduleAutoRetry(Gateway $gateway, string $operationType, array $parameters = []): void
    {
        // This would integrate with Laravel's job queue system for background retries
        // For now, we'll just log the scheduled retry
        
        Log::info('RTU auto-retry scheduled', [
            'gateway_id' => $gateway->id,
            'operation_type' => $operationType,
            'parameters' => $parameters,
            'scheduled_at' => now()->addMinutes(5)
        ]);

        // In a full implementation, this would dispatch a job:
        // dispatch(new RTURetryJob($gateway, $operationType, $parameters))->delay(now()->addMinutes(5));
    }
}