# Design: RTU Dashboard Improvements for Teltonika RUT956 Gateway Monitoring

## Overview

This design enhances the existing Laravel-based gateway dashboard specifically for Layer 1 (Teltonika RUT956 Gateway Monitoring). The solution builds upon the current Filament-based dashboard architecture and existing widget system to provide a streamlined, comprehensive monitoring interface for RTU gateways. The enhancement focuses on removing redundancies, adding RTU-specific monitoring capabilities, and improving the overall user experience for industrial gateway management.

The design leverages the existing Gateway and Device models while extending them with RTU-specific attributes and monitoring capabilities. It maintains compatibility with the current dashboard framework while introducing specialized widgets and data structures for Teltonika RUT956 gateway monitoring.

## Architecture

### RTU Dashboard Enhancement Layer
- **Gateway Type Detection**: Automatic identification of Teltonika RUT956 gateways for specialized dashboard rendering
- **RTU-Specific Widget System**: Specialized widgets for system health, network status, and I/O monitoring
- **Data Aggregation Service**: Consolidated data collection from multiple RTU monitoring endpoints
- **Alert Grouping Engine**: Intelligent alert consolidation and prioritization system

### Widget Architecture Enhancements
- **Collapsible Section System**: Organized widget groups (System, Network, I/O) with persistent state
- **Conditional Widget Rendering**: Smart widget display based on data availability and gateway capabilities
- **Real-time Data Streaming**: WebSocket-based updates for critical RTU metrics
- **Responsive Layout System**: Adaptive layouts optimized for RTU monitoring workflows

### Data Collection Architecture
- **RTU Metrics Collector**: Service for gathering system health data (CPU, memory, uptime)
- **Network Status Monitor**: Real-time collection of WAN IP, SIM details, and signal quality metrics
- **I/O State Manager**: Digital and analog input/output monitoring and control interface
- **Alert Processing Engine**: Advanced filtering, grouping, and prioritization of RTU alerts

## Components and Interfaces

### Enhanced Gateway Model Extensions

#### RTU Gateway Model Extension
```php
class Gateway extends Model
{
    protected $fillable = [
        'name',
        'fixed_ip',
        'sim_number',
        'gsm_signal',
        'gnss_location',
        // RTU-specific fields
        'gateway_type',
        'wan_ip',
        'sim_iccid',
        'sim_apn',
        'sim_operator',
        'cpu_load',
        'memory_usage',
        'uptime_hours',
        'rssi',
        'rsrp',
        'rsrq',
        'sinr',
        'di1_status',
        'di2_status',
        'do1_status',
        'do2_status',
        'analog_input_voltage',
        'last_system_update',
        'communication_status'
    ];

    protected $casts = [
        'last_system_update' => 'datetime',
        'cpu_load' => 'float',
        'memory_usage' => 'float',
        'uptime_hours' => 'integer',
        'analog_input_voltage' => 'float',
        'di1_status' => 'boolean',
        'di2_status' => 'boolean',
        'do1_status' => 'boolean',
        'do2_status' => 'boolean'
    ];

    public function isRTUGateway(): bool
    {
        return $this->gateway_type === 'teltonika_rut956';
    }

    public function getSystemHealthScore(): int
    {
        $score = 100;
        if ($this->cpu_load > 80) $score -= 20;
        if ($this->memory_usage > 90) $score -= 30;
        if ($this->communication_status !== 'online') $score -= 50;
        return max(0, $score);
    }

    public function getSignalQualityStatus(): string
    {
        if ($this->rssi > -70) return 'excellent';
        if ($this->rssi > -85) return 'good';
        if ($this->rssi > -100) return 'fair';
        return 'poor';
    }
}
```

### RTU-Specific Dashboard Controller

#### Enhanced RTU Dashboard Controller
```php
class RTUDashboardController extends Controller
{
    protected RTUDataService $rtuDataService;
    protected RTUAlertService $rtuAlertService;
    protected DashboardConfigService $configService;

    public function rtuDashboard(Request $request, Gateway $gateway): Response
    {
        $this->authorize('view', $gateway);
        
        if (!$gateway->isRTUGateway()) {
            return redirect()->route('dashboard.gateway', $gateway)
                ->with('warning', 'This gateway is not configured as an RTU device.');
        }

        $dashboardConfig = $this->configService->getUserDashboardConfig($request->user(), 'rtu');
        $systemHealth = $this->rtuDataService->getSystemHealth($gateway);
        $networkStatus = $this->rtuDataService->getNetworkStatus($gateway);
        $ioStatus = $this->rtuDataService->getIOStatus($gateway);
        $groupedAlerts = $this->rtuAlertService->getGroupedAlerts($gateway);
        $trendData = $this->rtuDataService->getTrendData($gateway, '24h');

        return view('dashboard.rtu', [
            'gateway' => $gateway,
            'systemHealth' => $systemHealth,
            'networkStatus' => $networkStatus,
            'ioStatus' => $ioStatus,
            'groupedAlerts' => $groupedAlerts,
            'trendData' => $trendData,
            'config' => $dashboardConfig
        ]);
    }

    public function updateDigitalOutput(Request $request, Gateway $gateway, string $output): JsonResponse
    {
        $this->authorize('control', $gateway);
        
        $validated = $request->validate([
            'state' => 'required|boolean'
        ]);

        $result = $this->rtuDataService->setDigitalOutput($gateway, $output, $validated['state']);
        
        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'new_state' => $result['new_state'] ?? null
        ]);
    }
}
```

### RTU Data Service

#### RTU Data Collection Service
```php
class RTUDataService
{
    protected ModbusClient $modbusClient;
    protected TeltonikaAPIClient $teltonikaClient;

    public function getSystemHealth(Gateway $gateway): array
    {
        try {
            $systemData = $this->teltonikaClient->getSystemInfo($gateway);
            
            return [
                'uptime_hours' => $systemData['uptime'] ?? 0,
                'cpu_load' => $systemData['cpu_load'] ?? 0,
                'memory_usage' => $systemData['memory_usage'] ?? 0,
                'health_score' => $gateway->getSystemHealthScore(),
                'status' => $this->determineSystemStatus($systemData),
                'last_updated' => now()
            ];
        } catch (Exception $e) {
            Log::error('RTU system health collection failed', [
                'gateway_id' => $gateway->id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'uptime_hours' => null,
                'cpu_load' => null,
                'memory_usage' => null,
                'health_score' => 0,
                'status' => 'unavailable',
                'last_updated' => $gateway->last_system_update
            ];
        }
    }

    public function getNetworkStatus(Gateway $gateway): array
    {
        try {
            $networkData = $this->teltonikaClient->getNetworkInfo($gateway);
            
            return [
                'wan_ip' => $networkData['wan_ip'] ?? 'Not assigned',
                'sim_iccid' => $networkData['sim_iccid'] ?? 'Unknown',
                'sim_apn' => $networkData['apn'] ?? 'Not configured',
                'sim_operator' => $networkData['operator'] ?? 'Unknown',
                'signal_quality' => [
                    'rssi' => $networkData['rssi'] ?? null,
                    'rsrp' => $networkData['rsrp'] ?? null,
                    'rsrq' => $networkData['rsrq'] ?? null,
                    'sinr' => $networkData['sinr'] ?? null,
                    'status' => $gateway->getSignalQualityStatus()
                ],
                'connection_status' => $networkData['connection_status'] ?? 'unknown',
                'last_updated' => now()
            ];
        } catch (Exception $e) {
            Log::error('RTU network status collection failed', [
                'gateway_id' => $gateway->id,
                'error' => $e->getMessage()
            ]);
            
            return [
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
                'last_updated' => $gateway->last_system_update
            ];
        }
    }

    public function getIOStatus(Gateway $gateway): array
    {
        try {
            $ioData = $this->teltonikaClient->getIOStatus($gateway);
            
            return [
                'digital_inputs' => [
                    'di1' => [
                        'status' => $ioData['di1'] ?? false,
                        'label' => 'Digital Input 1'
                    ],
                    'di2' => [
                        'status' => $ioData['di2'] ?? false,
                        'label' => 'Digital Input 2'
                    ]
                ],
                'digital_outputs' => [
                    'do1' => [
                        'status' => $ioData['do1'] ?? false,
                        'label' => 'Digital Output 1',
                        'controllable' => true
                    ],
                    'do2' => [
                        'status' => $ioData['do2'] ?? false,
                        'label' => 'Digital Output 2',
                        'controllable' => true
                    ]
                ],
                'analog_input' => [
                    'voltage' => $ioData['analog_voltage'] ?? 0.0,
                    'unit' => 'V',
                    'range' => '0-10V',
                    'precision' => 2
                ],
                'last_updated' => now()
            ];
        } catch (Exception $e) {
            Log::error('RTU I/O status collection failed', [
                'gateway_id' => $gateway->id,
                'error' => $e->getMessage()
            ]);
            
            return [
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
                'last_updated' => $gateway->last_system_update
            ];
        }
    }

    public function setDigitalOutput(Gateway $gateway, string $output, bool $state): array
    {
        try {
            $result = $this->teltonikaClient->setDigitalOutput($gateway, $output, $state);
            
            // Update gateway model
            $gateway->update([
                $output . '_status' => $state,
                'last_system_update' => now()
            ]);
            
            return [
                'success' => true,
                'message' => "Digital output {$output} set to " . ($state ? 'ON' : 'OFF'),
                'new_state' => $state
            ];
        } catch (Exception $e) {
            Log::error('RTU digital output control failed', [
                'gateway_id' => $gateway->id,
                'output' => $output,
                'state' => $state,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to control digital output: ' . $e->getMessage()
            ];
        }
    }

    public function getTrendData(Gateway $gateway, string $timeRange): array
    {
        $endTime = now();
        $startTime = match($timeRange) {
            '1h' => $endTime->copy()->subHour(),
            '6h' => $endTime->copy()->subHours(6),
            '24h' => $endTime->copy()->subDay(),
            '7d' => $endTime->copy()->subWeek(),
            default => $endTime->copy()->subDay()
        };

        $readings = Reading::where('gateway_id', $gateway->id)
            ->whereBetween('timestamp', [$startTime, $endTime])
            ->orderBy('timestamp')
            ->get();

        if ($readings->isEmpty()) {
            return [
                'has_data' => false,
                'message' => 'No data available for selected period',
                'available_metrics' => []
            ];
        }

        return [
            'has_data' => true,
            'time_range' => $timeRange,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'metrics' => [
                'signal_strength' => $this->extractMetricData($readings, 'rssi'),
                'cpu_load' => $this->extractMetricData($readings, 'cpu_load'),
                'memory_usage' => $this->extractMetricData($readings, 'memory_usage'),
                'analog_input' => $this->extractMetricData($readings, 'analog_voltage')
            ],
            'available_metrics' => ['signal_strength', 'cpu_load', 'memory_usage', 'analog_input']
        ];
    }
}
```

### RTU Alert Service

#### Alert Grouping and Management Service
```php
class RTUAlertService
{
    public function getGroupedAlerts(Gateway $gateway): array
    {
        $alerts = Alert::where('gateway_id', $gateway->id)
            ->where('status', 'active')
            ->orderBy('created_at', 'desc')
            ->get();

        $groupedAlerts = $this->groupSimilarAlerts($alerts);
        $filteredAlerts = $this->filterOffHoursAlerts($groupedAlerts);

        return [
            'critical_count' => $filteredAlerts->where('severity', 'critical')->count(),
            'warning_count' => $filteredAlerts->where('severity', 'warning')->count(),
            'info_count' => $filteredAlerts->where('severity', 'info')->count(),
            'grouped_alerts' => $filteredAlerts->take(10)->values(),
            'has_alerts' => $filteredAlerts->isNotEmpty(),
            'status_summary' => $this->getAlertStatusSummary($filteredAlerts)
        ];
    }

    protected function groupSimilarAlerts(Collection $alerts): Collection
    {
        $grouped = $alerts->groupBy(function ($alert) {
            // Group by alert type (e.g., "Router Uptime", "Connection State", "GSM Signal")
            return $this->normalizeAlertType($alert->type);
        });

        return $grouped->map(function ($alertGroup, $type) {
            $latest = $alertGroup->first();
            $count = $alertGroup->count();
            
            return (object) [
                'type' => $type,
                'message' => $latest->message,
                'severity' => $this->getHighestSeverity($alertGroup),
                'count' => $count,
                'latest_timestamp' => $latest->created_at,
                'first_occurrence' => $alertGroup->last()->created_at,
                'is_grouped' => $count > 1
            ];
        });
    }

    protected function filterOffHoursAlerts(Collection $groupedAlerts): Collection
    {
        $currentHour = now()->hour;
        $isBusinessHours = $currentHour >= 8 && $currentHour <= 18;

        if ($isBusinessHours) {
            return $groupedAlerts;
        }

        // During off-hours, only show critical alerts in main view
        return $groupedAlerts->filter(function ($alert) {
            return $alert->severity === 'critical';
        });
    }

    public function getFilteredAlerts(Gateway $gateway, array $filters): Collection
    {
        $query = Alert::where('gateway_id', $gateway->id);

        if (isset($filters['severity']) && !empty($filters['severity'])) {
            $query->whereIn('severity', $filters['severity']);
        }

        if (isset($filters['device_ids']) && !empty($filters['device_ids'])) {
            $query->whereIn('device_id', $filters['device_ids']);
        }

        if (isset($filters['time_range'])) {
            $startTime = match($filters['time_range']) {
                'last_hour' => now()->subHour(),
                'last_day' => now()->subDay(),
                'last_week' => now()->subWeek(),
                'custom' => $filters['start_date'] ?? now()->subDay(),
                default => now()->subDay()
            };

            $endTime = $filters['end_date'] ?? now();
            $query->whereBetween('created_at', [$startTime, $endTime]);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    protected function getAlertStatusSummary(Collection $alerts): string
    {
        $criticalCount = $alerts->where('severity', 'critical')->count();
        $warningCount = $alerts->where('severity', 'warning')->count();

        if ($criticalCount > 0) {
            return $criticalCount === 1 ? '1 Critical Alert' : "{$criticalCount} Critical Alerts";
        }

        if ($warningCount > 0) {
            return $warningCount === 1 ? '1 Warning' : "{$warningCount} Warnings";
        }

        return 'All Systems OK';
    }
}
```

### RTU-Specific Widgets

#### System Health Widget
```php
class RTUSystemHealthWidget extends BaseWidget
{
    protected function getData(): array
    {
        $systemHealth = app(RTUDataService::class)->getSystemHealth($this->gateway);
        
        return [
            'uptime' => [
                'value' => $systemHealth['uptime_hours'],
                'unit' => 'hours',
                'status' => $systemHealth['uptime_hours'] > 0 ? 'online' : 'offline',
                'icon' => 'clock'
            ],
            'cpu_load' => [
                'value' => $systemHealth['cpu_load'],
                'unit' => '%',
                'status' => $this->getCPUStatus($systemHealth['cpu_load']),
                'icon' => 'cpu-chip',
                'threshold_warning' => 80,
                'threshold_critical' => 95
            ],
            'memory_usage' => [
                'value' => $systemHealth['memory_usage'],
                'unit' => '%',
                'status' => $this->getMemoryStatus($systemHealth['memory_usage']),
                'icon' => 'memory',
                'threshold_warning' => 85,
                'threshold_critical' => 95
            ],
            'health_score' => $systemHealth['health_score'],
            'overall_status' => $systemHealth['status'],
            'last_updated' => $systemHealth['last_updated']
        ];
    }

    protected function getCPUStatus(?float $cpuLoad): string
    {
        if ($cpuLoad === null) return 'unknown';
        if ($cpuLoad >= 95) return 'critical';
        if ($cpuLoad >= 80) return 'warning';
        return 'normal';
    }

    protected function getMemoryStatus(?float $memoryUsage): string
    {
        if ($memoryUsage === null) return 'unknown';
        if ($memoryUsage >= 95) return 'critical';
        if ($memoryUsage >= 85) return 'warning';
        return 'normal';
    }
}
```

#### Network Status Widget
```php
class RTUNetworkStatusWidget extends BaseWidget
{
    protected function getData(): array
    {
        $networkStatus = app(RTUDataService::class)->getNetworkStatus($this->gateway);
        
        return [
            'wan_connection' => [
                'ip_address' => $networkStatus['wan_ip'],
                'status' => $networkStatus['connection_status'],
                'icon' => 'globe-alt'
            ],
            'sim_details' => [
                'iccid' => $networkStatus['sim_iccid'],
                'apn' => $networkStatus['sim_apn'],
                'operator' => $networkStatus['sim_operator'],
                'icon' => 'device-mobile'
            ],
            'signal_quality' => [
                'rssi' => [
                    'value' => $networkStatus['signal_quality']['rssi'],
                    'unit' => 'dBm',
                    'label' => 'RSSI'
                ],
                'rsrp' => [
                    'value' => $networkStatus['signal_quality']['rsrp'],
                    'unit' => 'dBm',
                    'label' => 'RSRP'
                ],
                'rsrq' => [
                    'value' => $networkStatus['signal_quality']['rsrq'],
                    'unit' => 'dB',
                    'label' => 'RSRQ'
                ],
                'sinr' => [
                    'value' => $networkStatus['signal_quality']['sinr'],
                    'unit' => 'dB',
                    'label' => 'SINR'
                ],
                'overall_status' => $networkStatus['signal_quality']['status'],
                'icon' => 'signal'
            ],
            'last_updated' => $networkStatus['last_updated']
        ];
    }
}
```

#### I/O Monitoring Widget
```php
class RTUIOMonitoringWidget extends BaseWidget
{
    protected function getData(): array
    {
        $ioStatus = app(RTUDataService::class)->getIOStatus($this->gateway);
        
        return [
            'digital_inputs' => [
                'di1' => [
                    'status' => $ioStatus['digital_inputs']['di1']['status'],
                    'label' => $ioStatus['digital_inputs']['di1']['label'],
                    'state_text' => $ioStatus['digital_inputs']['di1']['status'] ? 'ON' : 'OFF',
                    'icon' => 'switch-horizontal'
                ],
                'di2' => [
                    'status' => $ioStatus['digital_inputs']['di2']['status'],
                    'label' => $ioStatus['digital_inputs']['di2']['label'],
                    'state_text' => $ioStatus['digital_inputs']['di2']['status'] ? 'ON' : 'OFF',
                    'icon' => 'switch-horizontal'
                ]
            ],
            'digital_outputs' => [
                'do1' => [
                    'status' => $ioStatus['digital_outputs']['do1']['status'],
                    'label' => $ioStatus['digital_outputs']['do1']['label'],
                    'controllable' => $ioStatus['digital_outputs']['do1']['controllable'],
                    'state_text' => $ioStatus['digital_outputs']['do1']['status'] ? 'ON' : 'OFF',
                    'icon' => 'switch-horizontal'
                ],
                'do2' => [
                    'status' => $ioStatus['digital_outputs']['do2']['status'],
                    'label' => $ioStatus['digital_outputs']['do2']['label'],
                    'controllable' => $ioStatus['digital_outputs']['do2']['controllable'],
                    'state_text' => $ioStatus['digital_outputs']['do2']['status'] ? 'ON' : 'OFF',
                    'icon' => 'switch-horizontal'
                ]
            ],
            'analog_input' => [
                'voltage' => $ioStatus['analog_input']['voltage'],
                'unit' => $ioStatus['analog_input']['unit'],
                'range' => $ioStatus['analog_input']['range'],
                'precision' => $ioStatus['analog_input']['precision'],
                'formatted_value' => number_format($ioStatus['analog_input']['voltage'], $ioStatus['analog_input']['precision']) . ' ' . $ioStatus['analog_input']['unit'],
                'icon' => 'lightning-bolt'
            ],
            'last_updated' => $ioStatus['last_updated']
        ];
    }
}
```

## Data Models

### Enhanced Gateway Schema
```sql
ALTER TABLE gateways ADD COLUMN gateway_type VARCHAR(50) DEFAULT 'generic';
ALTER TABLE gateways ADD COLUMN wan_ip VARCHAR(45) NULL;
ALTER TABLE gateways ADD COLUMN sim_iccid VARCHAR(50) NULL;
ALTER TABLE gateways ADD COLUMN sim_apn VARCHAR(100) NULL;
ALTER TABLE gateways ADD COLUMN sim_operator VARCHAR(100) NULL;
ALTER TABLE gateways ADD COLUMN cpu_load DECIMAL(5,2) NULL;
ALTER TABLE gateways ADD COLUMN memory_usage DECIMAL(5,2) NULL;
ALTER TABLE gateways ADD COLUMN uptime_hours INTEGER NULL;
ALTER TABLE gateways ADD COLUMN rssi INTEGER NULL;
ALTER TABLE gateways ADD COLUMN rsrp INTEGER NULL;
ALTER TABLE gateways ADD COLUMN rsrq INTEGER NULL;
ALTER TABLE gateways ADD COLUMN sinr INTEGER NULL;
ALTER TABLE gateways ADD COLUMN di1_status BOOLEAN DEFAULT FALSE;
ALTER TABLE gateways ADD COLUMN di2_status BOOLEAN DEFAULT FALSE;
ALTER TABLE gateways ADD COLUMN do1_status BOOLEAN DEFAULT FALSE;
ALTER TABLE gateways ADD COLUMN do2_status BOOLEAN DEFAULT FALSE;
ALTER TABLE gateways ADD COLUMN analog_input_voltage DECIMAL(6,3) NULL;
ALTER TABLE gateways ADD COLUMN last_system_update TIMESTAMP NULL;
ALTER TABLE gateways ADD COLUMN communication_status ENUM('online', 'warning', 'offline') DEFAULT 'offline';

CREATE INDEX idx_gateways_type ON gateways(gateway_type);
CREATE INDEX idx_gateways_communication_status ON gateways(communication_status);
```

### RTU Dashboard Configuration Schema
```sql
CREATE TABLE rtu_dashboard_sections (
    id BIGINT UNSIGNED PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    section_name VARCHAR(50) NOT NULL,
    is_collapsed BOOLEAN DEFAULT FALSE,
    display_order INTEGER DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_section (user_id, section_name)
);

CREATE TABLE rtu_trend_preferences (
    id BIGINT UNSIGNED PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    gateway_id BIGINT UNSIGNED NOT NULL,
    selected_metrics JSON NOT NULL,
    time_range VARCHAR(10) DEFAULT '24h',
    chart_type VARCHAR(20) DEFAULT 'line',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (gateway_id) REFERENCES gateways(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_gateway_prefs (user_id, gateway_id)
);
```

## Error Handling

### RTU Communication Errors
- **Gateway Offline**: Graceful handling when RTU gateway is unreachable with cached data display
- **API Timeout**: Fallback to last known values with clear timestamp indicators
- **Authentication Failures**: Secure error logging without exposing credentials
- **Partial Data Availability**: Progressive widget loading with available data only

### I/O Control Errors
- **Digital Output Control Failures**: Clear error messages with retry options
- **Permission Denied**: Appropriate authorization error handling for control operations
- **Hardware Malfunction**: Detection and reporting of I/O module failures
- **Network Interruption**: Graceful handling of control command failures

### Data Validation Errors
- **Invalid Sensor Readings**: Outlier detection and validation for analog inputs
- **Signal Quality Anomalies**: Validation of cellular signal strength readings
- **System Metric Validation**: Range checking for CPU and memory usage values
- **Timestamp Synchronization**: Handling of clock drift and synchronization issues

### Widget Error Recovery
```php
class RTUWidgetErrorHandler
{
    public function handleDataCollectionError(Gateway $gateway, string $dataType, Exception $e): array
    {
        Log::error('RTU data collection failed', [
            'gateway_id' => $gateway->id,
            'data_type' => $dataType,
            'error' => $e->getMessage(),
            'gateway_type' => $gateway->gateway_type
        ]);

        return [
            'status' => 'error',
            'message' => $this->getUserFriendlyMessage($dataType, $e),
            'fallback_data' => $this->getFallbackData($gateway, $dataType),
            'retry_available' => true,
            'last_successful_update' => $gateway->last_system_update
        ];
    }

    public function handleControlError(Gateway $gateway, string $operation, Exception $e): array
    {
        Log::error('RTU control operation failed', [
            'gateway_id' => $gateway->id,
            'operation' => $operation,
            'error' => $e->getMessage()
        ]);

        return [
            'success' => false,
            'message' => "Control operation failed: {$operation}",
            'error_type' => $this->classifyError($e),
            'retry_suggested' => $this->shouldSuggestRetry($e),
            'troubleshooting_steps' => $this->getTroubleshootingSteps($operation, $e)
        ];
    }
}
```

## Testing Strategy

### RTU Data Collection Testing
- **Mock RTU Gateway Responses**: Test data collection with simulated Teltonika API responses
- **Network Failure Scenarios**: Test graceful degradation when RTU gateway is unreachable
- **Data Validation Testing**: Verify proper handling of invalid or out-of-range sensor readings
- **Performance Testing**: Test data collection performance with multiple concurrent RTU gateways

### Widget Functionality Testing
- **System Health Widget**: Test CPU, memory, and uptime display with various data states
- **Network Status Widget**: Test signal quality indicators and SIM information display
- **I/O Monitoring Widget**: Test digital input/output status and analog input readings
- **Alert Grouping**: Test alert consolidation and filtering logic

### User Interface Testing
- **Collapsible Sections**: Test section collapse/expand functionality and state persistence
- **Responsive Layout**: Test dashboard layout on various screen sizes and devices
- **Real-time Updates**: Test WebSocket-based live data updates
- **Digital Output Control**: Test I/O control interface and error handling

### Integration Testing
- **RTU Gateway Integration**: Test integration with actual Teltonika RUT956 devices
- **Database Performance**: Test query performance with large datasets and multiple gateways
- **Alert Processing**: Test end-to-end alert generation, grouping, and display
- **User Permission Integration**: Test RTU dashboard access with existing permission system

### Security Testing
- **I/O Control Authorization**: Test digital output control permissions and access logging
- **API Security**: Test secure communication with RTU gateways
- **Data Sanitization**: Test input validation for all RTU data inputs
- **Session Security**: Test secure handling of RTU control sessions and timeouts