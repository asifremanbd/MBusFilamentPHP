<?php

namespace Tests\Mocks;

use App\Models\Gateway;
use Illuminate\Support\Carbon;

class RTUGatewayMock
{
    /**
     * Generate mock system health data for RTU gateway
     */
    public static function getSystemHealthData(Gateway $gateway, array $overrides = []): array
    {
        $baseData = [
            'uptime_hours' => 168,
            'cpu_load' => 45.2,
            'memory_usage' => 67.8,
            'health_score' => 85,
            'status' => 'normal',
            'last_updated' => Carbon::now()
        ];

        return array_merge($baseData, $overrides);
    }

    /**
     * Generate mock network status data for RTU gateway
     */
    public static function getNetworkStatusData(Gateway $gateway, array $overrides = []): array
    {
        $baseData = [
            'wan_ip' => '192.168.1.100',
            'sim_iccid' => '89012345678901234567',
            'sim_apn' => 'internet.provider.com',
            'sim_operator' => 'Test Operator',
            'signal_quality' => [
                'rssi' => -65,
                'rsrp' => -95,
                'rsrq' => -10,
                'sinr' => 15,
                'status' => 'good'
            ],
            'connection_status' => 'connected',
            'last_updated' => Carbon::now()
        ];

        return array_merge($baseData, $overrides);
    }

    /**
     * Generate mock I/O status data for RTU gateway
     */
    public static function getIOStatusData(Gateway $gateway, array $overrides = []): array
    {
        $baseData = [
            'digital_inputs' => [
                'di1' => [
                    'status' => true,
                    'label' => 'Digital Input 1'
                ],
                'di2' => [
                    'status' => false,
                    'label' => 'Digital Input 2'
                ]
            ],
            'digital_outputs' => [
                'do1' => [
                    'status' => false,
                    'label' => 'Digital Output 1',
                    'controllable' => true
                ],
                'do2' => [
                    'status' => true,
                    'label' => 'Digital Output 2',
                    'controllable' => true
                ]
            ],
            'analog_input' => [
                'voltage' => 5.2,
                'unit' => 'V',
                'range' => '0-10V',
                'precision' => 2
            ],
            'last_updated' => Carbon::now()
        ];

        return array_merge($baseData, $overrides);
    }

    /**
     * Generate mock trend data for RTU gateway
     */
    public static function getTrendData(Gateway $gateway, string $timeRange = '24h', array $overrides = []): array
    {
        $endTime = Carbon::now();
        $startTime = match($timeRange) {
            '1h' => $endTime->copy()->subHour(),
            '6h' => $endTime->copy()->subHours(6),
            '24h' => $endTime->copy()->subDay(),
            '7d' => $endTime->copy()->subWeek(),
            default => $endTime->copy()->subDay()
        };

        $baseData = [
            'has_data' => true,
            'time_range' => $timeRange,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'metrics' => [
                'signal_strength' => self::generateMetricData($startTime, $endTime, -65, 10),
                'cpu_load' => self::generateMetricData($startTime, $endTime, 45, 15),
                'memory_usage' => self::generateMetricData($startTime, $endTime, 67, 10),
                'analog_input' => self::generateMetricData($startTime, $endTime, 5.2, 1.5)
            ],
            'available_metrics' => ['signal_strength', 'cpu_load', 'memory_usage', 'analog_input']
        ];

        return array_merge($baseData, $overrides);
    }

    /**
     * Generate mock alert data for RTU gateway
     */
    public static function getGroupedAlertsData(Gateway $gateway, array $overrides = []): array
    {
        $baseData = [
            'critical_count' => 1,
            'warning_count' => 2,
            'info_count' => 0,
            'grouped_alerts' => [
                (object) [
                    'type' => 'High CPU Usage',
                    'message' => 'CPU load exceeded 80% threshold',
                    'severity' => 'critical',
                    'count' => 1,
                    'latest_timestamp' => Carbon::now()->subMinutes(5),
                    'first_occurrence' => Carbon::now()->subMinutes(5),
                    'is_grouped' => false
                ],
                (object) [
                    'type' => 'Signal Quality',
                    'message' => 'GSM signal strength below optimal level',
                    'severity' => 'warning',
                    'count' => 3,
                    'latest_timestamp' => Carbon::now()->subMinutes(2),
                    'first_occurrence' => Carbon::now()->subHour(),
                    'is_grouped' => true
                ]
            ],
            'has_alerts' => true,
            'status_summary' => '1 Critical Alert'
        ];

        return array_merge($baseData, $overrides);
    }

    /**
     * Generate mock data for digital output control response
     */
    public static function getDigitalOutputControlResponse(bool $success = true, array $overrides = []): array
    {
        if ($success) {
            $baseData = [
                'success' => true,
                'message' => 'Digital output do1 set to ON',
                'new_state' => true
            ];
        } else {
            $baseData = [
                'success' => false,
                'message' => 'Communication timeout with RTU gateway'
            ];
        }

        return array_merge($baseData, $overrides);
    }

    /**
     * Generate mock error responses for various failure scenarios
     */
    public static function getErrorResponse(string $errorType = 'communication'): array
    {
        return match($errorType) {
            'communication' => [
                'status' => 'error',
                'message' => 'Unable to communicate with RTU gateway',
                'error_code' => 'RTU_COMM_TIMEOUT',
                'retry_available' => true
            ],
            'authentication' => [
                'status' => 'error',
                'message' => 'Authentication failed with RTU gateway',
                'error_code' => 'RTU_AUTH_FAILED',
                'retry_available' => false
            ],
            'hardware' => [
                'status' => 'error',
                'message' => 'RTU hardware malfunction detected',
                'error_code' => 'RTU_HARDWARE_ERROR',
                'retry_available' => false
            ],
            'network' => [
                'status' => 'error',
                'message' => 'Network connectivity issues',
                'error_code' => 'RTU_NETWORK_ERROR',
                'retry_available' => true
            ],
            default => [
                'status' => 'error',
                'message' => 'Unknown RTU error occurred',
                'error_code' => 'RTU_UNKNOWN_ERROR',
                'retry_available' => true
            ]
        };
    }

    /**
     * Generate mock unavailable data responses
     */
    public static function getUnavailableDataResponse(string $dataType): array
    {
        return match($dataType) {
            'system_health' => [
                'uptime_hours' => null,
                'cpu_load' => null,
                'memory_usage' => null,
                'health_score' => 0,
                'status' => 'unavailable',
                'last_updated' => null
            ],
            'network_status' => [
                'wan_ip' => 'Data unavailable',
                'sim_iccid' => 'Data unavailable',
                'sim_apn' => 'Data unavailable',
                'sim_operator' => 'Data unavailable',
                'signal_quality' => [
                    'rssi' => null,
                    'rsrp' => null,
                    'rsrq' => null,
                    'sinr' => null,
                    'status' => 'unknown'
                ],
                'connection_status' => 'unavailable',
                'last_updated' => null
            ],
            'io_status' => [
                'digital_inputs' => [
                    'di1' => ['status' => null, 'label' => 'Digital Input 1'],
                    'di2' => ['status' => null, 'label' => 'Digital Input 2']
                ],
                'digital_outputs' => [
                    'do1' => ['status' => null, 'label' => 'Digital Output 1', 'controllable' => false],
                    'do2' => ['status' => null, 'label' => 'Digital Output 2', 'controllable' => false]
                ],
                'analog_input' => [
                    'voltage' => null,
                    'unit' => 'V',
                    'range' => '0-10V',
                    'precision' => 2
                ],
                'last_updated' => null
            ],
            'trend_data' => [
                'has_data' => false,
                'message' => 'No data available for selected period',
                'available_metrics' => []
            ],
            default => [
                'status' => 'unavailable',
                'message' => 'Data temporarily unavailable'
            ]
        };
    }

    /**
     * Generate time-series data for metrics
     */
    private static function generateMetricData(Carbon $startTime, Carbon $endTime, float $baseValue, float $variance): array
    {
        $data = [];
        $current = $startTime->copy();
        $interval = $endTime->diffInMinutes($startTime) / 50; // Generate ~50 data points

        while ($current <= $endTime) {
            $value = $baseValue + (rand(-100, 100) / 100) * $variance;
            $data[] = [
                'timestamp' => $current->toISOString(),
                'value' => round($value, 2)
            ];
            $current->addMinutes($interval);
        }

        return $data;
    }

    /**
     * Generate mock data for multiple concurrent gateways
     */
    public static function getMultiGatewayData(array $gateways): array
    {
        $data = [];
        
        foreach ($gateways as $gateway) {
            $data[$gateway->id] = [
                'system_health' => self::getSystemHealthData($gateway),
                'network_status' => self::getNetworkStatusData($gateway),
                'io_status' => self::getIOStatusData($gateway),
                'alerts' => self::getGroupedAlertsData($gateway),
                'trend_data' => self::getTrendData($gateway)
            ];
        }

        return $data;
    }

    /**
     * Simulate Teltonika RUT956 specific responses
     */
    public static function getTeltonikaRUT956Response(string $endpoint, array $params = []): array
    {
        return match($endpoint) {
            'system_info' => [
                'uptime' => 604800, // 7 days in seconds
                'cpu_load' => 42.5,
                'memory_usage' => 68.3,
                'temperature' => 45.2,
                'firmware_version' => 'RUT9_R_00.07.06.1',
                'model' => 'RUT956'
            ],
            'network_info' => [
                'wan_ip' => '10.0.0.100',
                'sim_iccid' => '89012345678901234567',
                'apn' => 'internet',
                'operator' => 'Test Mobile',
                'rssi' => -67,
                'rsrp' => -97,
                'rsrq' => -12,
                'sinr' => 13,
                'connection_status' => 'connected',
                'data_usage' => ['tx' => 1024000, 'rx' => 2048000]
            ],
            'io_status' => [
                'di1' => true,
                'di2' => false,
                'do1' => false,
                'do2' => true,
                'analog_voltage' => 5.24
            ],
            'set_digital_output' => [
                'success' => true,
                'output' => $params['output'] ?? 'do1',
                'state' => $params['state'] ?? false,
                'timestamp' => Carbon::now()->toISOString()
            ],
            default => [
                'error' => 'Unknown endpoint',
                'available_endpoints' => ['system_info', 'network_info', 'io_status', 'set_digital_output']
            ]
        };
    }
}