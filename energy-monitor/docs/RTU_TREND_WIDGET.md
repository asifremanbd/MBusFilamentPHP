# RTU Trend Widget Documentation

## Overview

The RTU Trend Widget provides enhanced trend visualization for RTU (Remote Terminal Unit) gateways, specifically designed for Teltonika RUT956 devices. It supports multiple metrics display with intelligent data handling and user-friendly interfaces.

## Features

### Multi-Metric Support
- **Signal Strength**: RSSI values in dBm with color-coded status indicators
- **CPU Load**: Percentage-based CPU utilization with warning thresholds
- **Memory Usage**: RAM utilization with critical level indicators  
- **Analog Input**: 0-10V analog input readings with precision formatting

### Intelligent Data Handling
- **Conditional Rendering**: Widget automatically hides when no data is available
- **Default Fallback**: Shows Signal Strength as default metric when available
- **Metric Selection**: Users can choose which metrics to display simultaneously
- **Multi-Series Charts**: Different colors and scales for selected metrics

### Time Range Support
- 1 Hour, 6 Hours, 24 Hours, 7 Days, 30 Days
- Real-time updates without page refresh
- Persistent user preferences across sessions

## Implementation Details

### Widget Class: `RTUTrendWidget`

**Location**: `app/Filament/Widgets/RTUTrendWidget.php`

**Key Methods**:
- `getData()`: Main data retrieval and processing
- `determineMetricsToShow()`: Intelligent metric selection logic
- `prepareChartData()`: Multi-series chart data preparation
- `getMetricConfigurations()`: Metric display configurations
- `shouldHide()`: Conditional rendering logic

### Template: `rtu-trend-widget.blade.php`

**Location**: `resources/views/filament/widgets/rtu-trend-widget.blade.php`

**Features**:
- ApexCharts integration for interactive visualizations
- Responsive design with mobile optimization
- Real-time metric selection interface
- Current value displays with status indicators

### Data Service Integration

**Service**: `RTUDataService::getTrendData()`

**Data Sources**:
- Gateway model attributes (rssi, cpu_load, memory_usage, analog_input_voltage)
- Device readings from associated Modbus devices
- Historical data with configurable time ranges

## Configuration

### Metric Configurations

```php
'signal_strength' => [
    'label' => 'Signal Strength',
    'unit' => 'dBm',
    'color' => '#10B981', // Green
    'yAxis' => 0,
    'min' => -120,
    'max' => -30,
    'icon' => 'heroicon-o-signal'
],
'cpu_load' => [
    'label' => 'CPU Load', 
    'unit' => '%',
    'color' => '#F59E0B', // Amber
    'yAxis' => 1,
    'min' => 0,
    'max' => 100,
    'icon' => 'heroicon-o-cpu-chip'
]
```

### Chart Options

- **Type**: Line chart with smooth curves
- **Height**: 350px responsive
- **Animations**: Smooth transitions enabled
- **Toolbar**: Download, zoom, pan controls
- **Tooltips**: Detailed value display on hover

## Usage Examples

### Basic Widget Mount

```php
$widget = new RTUTrendWidget();
$widget->mount($rtuGateway, ['signal_strength'], '24h');
```

### Multiple Metrics Selection

```php
$selectedMetrics = ['signal_strength', 'cpu_load', 'memory_usage'];
$widget->mount($rtuGateway, $selectedMetrics, '7d');
```

### Dynamic Updates

```javascript
// Update selected metrics via AJAX
$widget->updateSelectedMetrics(['cpu_load', 'analog_input']);

// Change time range
$widget->updateTimeRange('1h');
```

## Status Indicators

### Signal Strength
- **Excellent**: > -70 dBm (Green)
- **Good**: -70 to -85 dBm (Yellow) 
- **Fair**: -85 to -100 dBm (Orange)
- **Poor**: < -100 dBm (Red)

### CPU Load
- **Normal**: < 60% (Green)
- **Warning**: 60-80% (Yellow)
- **Critical**: > 80% (Red)

### Memory Usage
- **Normal**: < 70% (Green)
- **Warning**: 70-85% (Yellow)
- **Critical**: > 85% (Red)

## Error Handling

### No Data Scenarios
- **Complete Hide**: Widget hidden when no metrics available
- **Partial Display**: Shows available metrics with "No data" message
- **Fallback Values**: Uses last known values with timestamps

### Gateway Validation
- **RTU Check**: Validates gateway_type = 'teltonika_rut956'
- **Error Messages**: Clear feedback for invalid gateways
- **Graceful Degradation**: Maintains functionality with limited data

### Service Failures
- **Timeout Handling**: Graceful handling of API timeouts
- **Retry Logic**: Automatic retry for transient failures
- **User Feedback**: Clear error messages with troubleshooting hints

## Testing

### Unit Tests: `RTUTrendWidgetBasicTest`
- Metric configuration validation
- Chart data preparation logic
- Status class determination
- Value formatting functions

### Integration Tests: `RTUTrendWidgetIntegrationTest`
- Service integration testing
- Error scenario handling
- Multi-metric functionality
- Time range validation

## Performance Considerations

### Data Optimization
- **Efficient Queries**: Optimized database queries for readings
- **Caching**: Gateway-level metric caching
- **Lazy Loading**: Chart rendering only when data available

### Frontend Performance
- **Progressive Loading**: Widgets load independently
- **Chart Optimization**: ApexCharts performance tuning
- **Memory Management**: Proper chart cleanup on updates

## Requirements Compliance

This widget implementation satisfies the following requirements:

- **3.1-3.5**: Intelligent data visualization with conditional rendering
- **9.1-9.6**: Enhanced trend visualization with multiple metrics support

## Future Enhancements

- Real-time WebSocket updates
- Advanced filtering options
- Export functionality for trend data
- Custom metric threshold configuration
- Historical data comparison features