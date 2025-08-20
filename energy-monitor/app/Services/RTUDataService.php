<?php

namespace App\Services;

use App\Models\Gateway;
use App\Models\Reading;
use App\Models\Alert;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Collection;
use Exception;
use Carbon\Carbon;

class RTUDataService
{
    protected RTUWidgetErrorHandler $errorHandler;

    public function __construct(RTUWidgetErrorHandler $errorHandler)
    {
        $this->errorHandler = $errorHandler;
    }
    /**
     * Get system health information for RTU gateway
     */
    public function getSystemHealth(Gateway $gateway): array
    {
        // Check cache first for performance optimization
        $cacheService = app(RTUCacheService::class);
        $cachedData = $cacheService->getSystemHealth($gateway);
        
        if ($cachedData !== null) {
            return $cachedData;
        }

        try {
            // For now, we'll use the data stored in the gateway model
            // In a real implementation, this would make API calls to the Teltonika device
            $systemData = $this->fetchSystemDataFromGateway($gateway);
            
            $result = [
                'uptime_hours' => $systemData['uptime_hours'] ?? $gateway->uptime_hours ?? 0,
                'cpu_load' => $systemData['cpu_load'] ?? $gateway->cpu_load ?? 0,
                'memory_usage' => $systemData['memory_usage'] ?? $gateway->memory_usage ?? 0,
                'health_score' => $gateway->getSystemHealthScore(),
                'status' => $this->determineSystemStatus($systemData),
                'last_updated' => $gateway->last_system_update ?? now()
            ];

            // Update gateway model with latest data for persistence
            $gateway->update([
                'cpu_load' => $result['cpu_load'],
                'memory_usage' => $result['memory_usage'],
                'uptime_hours' => $result['uptime_hours'],
                'communication_status' => $result['status'] === 'unavailable' ? 'offline' : 'online',
                'last_system_update' => now()
            ]);
            
            // Cache successful data for performance and fallback use
            $cacheService->cacheSystemHealth($gateway, $result);
            $this->errorHandler->cacheSuccessfulData($gateway, 'system_health', $result);
            
            return $result;
        } catch (Exception $e) {
            return $this->errorHandler->handleDataCollectionError($gateway, 'system_health', $e);
        }
    }

    /**
     * Get network status information for RTU gateway
     */
    public function getNetworkStatus(Gateway $gateway): array
    {
        // Check cache first for performance optimization
        $cacheService = app(RTUCacheService::class);
        $cachedData = $cacheService->getNetworkStatus($gateway);
        
        if ($cachedData !== null) {
            return $cachedData;
        }

        try {
            $networkData = $this->fetchNetworkDataFromGateway($gateway);
            
            $result = [
                'wan_ip' => $networkData['wan_ip'] ?? $gateway->wan_ip ?? 'Not assigned',
                'sim_iccid' => $networkData['sim_iccid'] ?? $gateway->sim_iccid ?? 'Unknown',
                'sim_apn' => $networkData['sim_apn'] ?? $gateway->sim_apn ?? 'Not configured',
                'sim_operator' => $networkData['sim_operator'] ?? $gateway->sim_operator ?? 'Unknown',
                'signal_quality' => [
                    'rssi' => $networkData['rssi'] ?? $gateway->rssi,
                    'rsrp' => $networkData['rsrp'] ?? $gateway->rsrp,
                    'rsrq' => $networkData['rsrq'] ?? $gateway->rsrq,
                    'sinr' => $networkData['sinr'] ?? $gateway->sinr,
                    'status' => $gateway->getSignalQualityStatus()
                ],
                'connection_status' => $networkData['connection_status'] ?? $gateway->communication_status ?? 'unknown',
                'last_updated' => $gateway->last_system_update ?? now()
            ];

            // Update gateway model with latest network data
            $gateway->update([
                'wan_ip' => $result['wan_ip'],
                'sim_iccid' => $result['sim_iccid'],
                'sim_apn' => $result['sim_apn'],
                'sim_operator' => $result['sim_operator'],
                'rssi' => $result['signal_quality']['rssi'],
                'rsrp' => $result['signal_quality']['rsrp'],
                'rsrq' => $result['signal_quality']['rsrq'],
                'sinr' => $result['signal_quality']['sinr'],
                'last_system_update' => now()
            ]);
            
            // Cache successful data for performance and fallback use
            $cacheService->cacheNetworkStatus($gateway, $result);
            $this->errorHandler->cacheSuccessfulData($gateway, 'network_status', $result);
            
            return $result;
        } catch (Exception $e) {
            return $this->errorHandler->handleDataCollectionError($gateway, 'network_status', $e);
        }
    }

    /**
     * Get I/O status information for RTU gateway
     */
    public function getIOStatus(Gateway $gateway): array
    {
        // Check cache first for performance optimization
        $cacheService = app(RTUCacheService::class);
        $cachedData = $cacheService->getIOStatus($gateway);
        
        if ($cachedData !== null) {
            return $cachedData;
        }

        try {
            $ioData = $this->fetchIODataFromGateway($gateway);
            
            $result = [
                'digital_inputs' => [
                    'di1' => [
                        'status' => $ioData['di1'] ?? $gateway->di1_status ?? false,
                        'label' => 'Digital Input 1'
                    ],
                    'di2' => [
                        'status' => $ioData['di2'] ?? $gateway->di2_status ?? false,
                        'label' => 'Digital Input 2'
                    ]
                ],
                'digital_outputs' => [
                    'do1' => [
                        'status' => $ioData['do1'] ?? $gateway->do1_status ?? false,
                        'label' => 'Digital Output 1',
                        'controllable' => true
                    ],
                    'do2' => [
                        'status' => $ioData['do2'] ?? $gateway->do2_status ?? false,
                        'label' => 'Digital Output 2',
                        'controllable' => true
                    ]
                ],
                'analog_input' => [
                    'voltage' => $ioData['analog_voltage'] ?? $gateway->analog_input_voltage ?? 0.0,
                    'unit' => 'V',
                    'range' => '0-10V',
                    'precision' => 2
                ],
                'last_updated' => $gateway->last_system_update ?? now()
            ];

            // Update gateway model with latest I/O data
            $gateway->update([
                'di1_status' => $result['digital_inputs']['di1']['status'],
                'di2_status' => $result['digital_inputs']['di2']['status'],
                'do1_status' => $result['digital_outputs']['do1']['status'],
                'do2_status' => $result['digital_outputs']['do2']['status'],
                'analog_input_voltage' => $result['analog_input']['voltage'],
                'last_system_update' => now()
            ]);
            
            // Cache successful data for performance and fallback use
            $cacheService->cacheIOStatus($gateway, $result);
            $this->errorHandler->cacheSuccessfulData($gateway, 'io_status', $result);
            
            return $result;
        } catch (Exception $e) {
            return $this->errorHandler->handleDataCollectionError($gateway, 'io_status', $e);
        }
    }

    /**
     * Set digital output state for RTU gateway
     */
    public function setDigitalOutput(Gateway $gateway, string $output, bool $state): array
    {
        try {
            // Validate output parameter
            if (!in_array($output, ['do1', 'do2'])) {
                throw new Exception("Invalid output parameter: {$output}");
            }

            // In a real implementation, this would make an API call to the Teltonika device
            $result = $this->sendDigitalOutputCommand($gateway, $output, $state);
            
            if ($result['success']) {
                // Update gateway model with new state
                $gateway->update([
                    $output . '_status' => $state,
                    'last_system_update' => now()
                ]);
                
                Log::info('RTU digital output updated', [
                    'gateway_id' => $gateway->id,
                    'gateway_name' => $gateway->name,
                    'output' => $output,
                    'new_state' => $state
                ]);
                
                // Clear any cached I/O data since state has changed
                $this->errorHandler->clearFallbackCache($gateway, 'io_status');
                
                return [
                    'success' => true,
                    'message' => "Digital output {$output} set to " . ($state ? 'ON' : 'OFF'),
                    'new_state' => $state
                ];
            } else {
                throw new Exception($result['error'] ?? 'Unknown error occurred');
            }
        } catch (Exception $e) {
            return $this->errorHandler->handleControlError($gateway, $output, $e);
        }
    } 
   /**
     * Get trend data for RTU gateway with time range support
     */
    public function getTrendData(Gateway $gateway, string $timeRange): array
    {
        try {
            $endTime = now();
            $startTime = $this->calculateStartTime($timeRange, $endTime);

            // Get readings from devices associated with this gateway
            $readings = Reading::whereHas('device', function ($query) use ($gateway) {
                $query->where('gateway_id', $gateway->id);
            })
            ->whereBetween('timestamp', [$startTime, $endTime])
            ->orderBy('timestamp')
            ->get();

            if ($readings->isEmpty()) {
                // Even without device readings, we can still show gateway-level metrics
                $availableMetrics = $this->getAvailableMetrics(collect(), $gateway);
                
                return [
                    'has_data' => !empty($availableMetrics),
                    'message' => empty($availableMetrics) ? 'No data available for selected period' : 'Gateway metrics available',
                    'available_metrics' => $availableMetrics,
                    'time_range' => $timeRange,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'metrics' => !empty($availableMetrics) ? [
                        'signal_strength' => $this->extractMetricData(collect(), 'rssi', $gateway),
                        'cpu_load' => $this->extractMetricData(collect(), 'cpu_load', $gateway),
                        'memory_usage' => $this->extractMetricData(collect(), 'memory_usage', $gateway),
                        'analog_input' => $this->extractMetricData(collect(), 'analog_voltage', $gateway)
                    ] : []
                ];
            }

            return [
                'has_data' => true,
                'time_range' => $timeRange,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'metrics' => [
                    'signal_strength' => $this->extractMetricData($readings, 'rssi', $gateway),
                    'cpu_load' => $this->extractMetricData($readings, 'cpu_load', $gateway),
                    'memory_usage' => $this->extractMetricData($readings, 'memory_usage', $gateway),
                    'analog_input' => $this->extractMetricData($readings, 'analog_voltage', $gateway)
                ],
                'available_metrics' => $this->getAvailableMetrics($readings, $gateway)
            ];
        } catch (Exception $e) {
            Log::error('RTU trend data collection failed', [
                'gateway_id' => $gateway->id,
                'gateway_name' => $gateway->name,
                'time_range' => $timeRange,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'has_data' => false,
                'message' => 'Failed to retrieve trend data: ' . $e->getMessage(),
                'available_metrics' => [],
                'error' => 'Trend data collection failed'
            ];
        }
    }

    /**
     * Helper method to determine system status based on collected data
     */
    protected function determineSystemStatus(array $systemData): string
    {
        $cpuLoad = $systemData['cpu_load'] ?? null;
        $memoryUsage = $systemData['memory_usage'] ?? null;
        $uptime = $systemData['uptime_hours'] ?? null;

        // If no data available
        if ($cpuLoad === null && $memoryUsage === null && $uptime === null) {
            return 'unavailable';
        }

        // Check for critical conditions
        if (($cpuLoad !== null && $cpuLoad >= 95) || ($memoryUsage !== null && $memoryUsage >= 95)) {
            return 'critical';
        }

        // Check for warning conditions
        if (($cpuLoad !== null && $cpuLoad >= 80) || ($memoryUsage !== null && $memoryUsage >= 85)) {
            return 'warning';
        }

        // Check if system is offline (no uptime)
        if ($uptime !== null && $uptime <= 0) {
            return 'offline';
        }

        return 'normal';
    }

    /**
     * Helper method to extract metric data from readings
     */
    protected function extractMetricData(Collection $readings, string $metricType, Gateway $gateway): array
    {
        $metricData = [];
        
        // For RTU gateways, we might get some metrics from the gateway model itself
        // and others from device readings
        switch ($metricType) {
            case 'rssi':
                // Signal strength might come from gateway model or readings
                if ($gateway->rssi !== null) {
                    $metricData[] = [
                        'timestamp' => $gateway->last_system_update ?? now(),
                        'value' => $gateway->rssi,
                        'unit' => 'dBm'
                    ];
                }
                break;
                
            case 'cpu_load':
                if ($gateway->cpu_load !== null) {
                    $metricData[] = [
                        'timestamp' => $gateway->last_system_update ?? now(),
                        'value' => $gateway->cpu_load,
                        'unit' => '%'
                    ];
                }
                break;
                
            case 'memory_usage':
                if ($gateway->memory_usage !== null) {
                    $metricData[] = [
                        'timestamp' => $gateway->last_system_update ?? now(),
                        'value' => $gateway->memory_usage,
                        'unit' => '%'
                    ];
                }
                break;
                
            case 'analog_voltage':
                if ($gateway->analog_input_voltage !== null) {
                    $metricData[] = [
                        'timestamp' => $gateway->last_system_update ?? now(),
                        'value' => $gateway->analog_input_voltage,
                        'unit' => 'V'
                    ];
                }
                break;
        }

        // Also extract from device readings if available
        $deviceMetrics = $readings->filter(function ($reading) use ($metricType) {
            return $reading->register && 
                   str_contains(strtolower($reading->register->parameter_name ?? ''), strtolower($metricType));
        })->map(function ($reading) {
            return [
                'timestamp' => $reading->timestamp,
                'value' => $reading->value,
                'unit' => $this->getMetricUnit($reading->register->parameter_name ?? '')
            ];
        })->toArray();

        return array_merge($metricData, $deviceMetrics);
    }

    /**
     * Calculate start time based on time range
     */
    protected function calculateStartTime(string $timeRange, Carbon $endTime): Carbon
    {
        return match($timeRange) {
            '1h' => $endTime->copy()->subHour(),
            '6h' => $endTime->copy()->subHours(6),
            '24h' => $endTime->copy()->subDay(),
            '7d' => $endTime->copy()->subWeek(),
            '30d' => $endTime->copy()->subMonth(),
            default => $endTime->copy()->subDay()
        };
    }

    /**
     * Get available metrics from readings and gateway data
     */
    protected function getAvailableMetrics(Collection $readings, Gateway $gateway): array
    {
        $availableMetrics = [];

        // Check gateway-level metrics
        if ($gateway->rssi !== null) {
            $availableMetrics[] = 'signal_strength';
        }
        if ($gateway->cpu_load !== null) {
            $availableMetrics[] = 'cpu_load';
        }
        if ($gateway->memory_usage !== null) {
            $availableMetrics[] = 'memory_usage';
        }
        if ($gateway->analog_input_voltage !== null) {
            $availableMetrics[] = 'analog_input';
        }

        // Check device readings for additional metrics
        $deviceMetrics = $readings->pluck('register.parameter_name')->filter()->unique()->map(function ($registerName) {
            return strtolower(str_replace(' ', '_', $registerName));
        })->toArray();

        return array_unique(array_merge($availableMetrics, $deviceMetrics));
    }

    /**
     * Get appropriate unit for a metric
     */
    protected function getMetricUnit(string $registerName): string
    {
        $registerName = strtolower($registerName);
        
        if (str_contains($registerName, 'voltage') || str_contains($registerName, 'volt')) {
            return 'V';
        }
        if (str_contains($registerName, 'current') || str_contains($registerName, 'amp')) {
            return 'A';
        }
        if (str_contains($registerName, 'power') || str_contains($registerName, 'watt')) {
            return 'W';
        }
        if (str_contains($registerName, 'frequency') || str_contains($registerName, 'freq')) {
            return 'Hz';
        }
        if (str_contains($registerName, 'temperature') || str_contains($registerName, 'temp')) {
            return '°C';
        }
        if (str_contains($registerName, 'cpu') || str_contains($registerName, 'memory')) {
            return '%';
        }
        if (str_contains($registerName, 'signal') || str_contains($registerName, 'rssi')) {
            return 'dBm';
        }
        
        return '';
    }

    /**
     * Classify control error for better error handling
     */
    protected function classifyControlError(Exception $e): string
    {
        $message = strtolower($e->getMessage());
        
        if (str_contains($message, 'timeout') || str_contains($message, 'connection')) {
            return 'connection_error';
        }
        if (str_contains($message, 'unauthorized') || str_contains($message, 'forbidden')) {
            return 'authorization_error';
        }
        if (str_contains($message, 'invalid') || str_contains($message, 'parameter')) {
            return 'validation_error';
        }
        
        return 'unknown_error';
    }

    /**
     * Fetch system data from RTU gateway (placeholder for actual API implementation)
     */
    protected function fetchSystemDataFromGateway(Gateway $gateway): array
    {
        // In a real implementation, this would make HTTP requests to the Teltonika RUT956 API
        // For now, we'll simulate the data collection
        
        if (!$gateway->isRTUGateway()) {
            throw new Exception('Gateway is not configured as RTU device');
        }

        // Simulate API call delay and potential failures
        if (rand(1, 100) <= 5) { // 5% chance of failure for testing
            throw new Exception('Simulated API timeout');
        }

        return [
            'uptime_hours' => $gateway->uptime_hours ?? rand(1, 720), // 1 hour to 30 days
            'cpu_load' => $gateway->cpu_load ?? rand(10, 95),
            'memory_usage' => $gateway->memory_usage ?? rand(20, 90),
            'timestamp' => now()
        ];
    }

    /**
     * Fetch network data from RTU gateway (placeholder for actual API implementation)
     */
    protected function fetchNetworkDataFromGateway(Gateway $gateway): array
    {
        // In a real implementation, this would make HTTP requests to the Teltonika RUT956 API
        
        if (!$gateway->isRTUGateway()) {
            throw new Exception('Gateway is not configured as RTU device');
        }

        // Simulate API call delay and potential failures
        if (rand(1, 100) <= 3) { // 3% chance of failure for testing
            throw new Exception('Network API unavailable');
        }

        return [
            'wan_ip' => $gateway->wan_ip ?? '192.168.1.' . rand(100, 200),
            'sim_iccid' => $gateway->sim_iccid ?? '89' . str_pad(rand(1000000000000000, 9999999999999999), 18, '0'),
            'sim_apn' => $gateway->sim_apn ?? 'internet.provider.com',
            'sim_operator' => $gateway->sim_operator ?? 'Mobile Provider',
            'rssi' => $gateway->rssi ?? rand(-120, -50),
            'rsrp' => $gateway->rsrp ?? rand(-140, -70),
            'rsrq' => $gateway->rsrq ?? rand(-20, -3),
            'sinr' => $gateway->sinr ?? rand(-10, 30),
            'connection_status' => $gateway->communication_status ?? 'online',
            'timestamp' => now()
        ];
    }

    /**
     * Fetch I/O data from RTU gateway (placeholder for actual API implementation)
     */
    protected function fetchIODataFromGateway(Gateway $gateway): array
    {
        // In a real implementation, this would make HTTP requests to the Teltonika RUT956 API
        
        if (!$gateway->isRTUGateway()) {
            throw new Exception('Gateway is not configured as RTU device');
        }

        // Simulate API call delay and potential failures
        if (rand(1, 100) <= 2) { // 2% chance of failure for testing
            throw new Exception('I/O module not responding');
        }

        return [
            'di1' => $gateway->di1_status ?? (bool)rand(0, 1),
            'di2' => $gateway->di2_status ?? (bool)rand(0, 1),
            'do1' => $gateway->do1_status ?? (bool)rand(0, 1),
            'do2' => $gateway->do2_status ?? (bool)rand(0, 1),
            'analog_voltage' => $gateway->analog_input_voltage ?? round(rand(0, 1000) / 100, 2), // 0-10V
            'timestamp' => now()
        ];
    }

    /**
     * Send digital output command to RTU gateway (placeholder for actual API implementation)
     */
    protected function sendDigitalOutputCommand(Gateway $gateway, string $output, bool $state): array
    {
        // In a real implementation, this would make HTTP requests to the Teltonika RUT956 API
        
        if (!$gateway->isRTUGateway()) {
            throw new Exception('Gateway is not configured as RTU device');
        }

        // Simulate API call delay and potential failures
        if (rand(1, 100) <= 5) { // 5% chance of failure for testing
            return [
                'success' => false,
                'error' => 'Command timeout - device may be unreachable'
            ];
        }

        // Simulate successful command
        return [
            'success' => true,
            'new_state' => $state,
            'timestamp' => now()
        ];
    }
}