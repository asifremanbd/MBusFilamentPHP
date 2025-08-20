# RTU Alerts Widget Documentation

## Overview

The RTU Alerts Widget provides enhanced alert management and filtering capabilities specifically designed for Teltonika RUT956 RTU gateways. It replaces the cluttered alert display with a clean, organized interface that groups similar alerts and provides intelligent filtering options.

## Features

### 1. Simplified Device Status Indicators
- Replaces "600 Alerts" with clear status indicators:
  - **Critical**: Red indicator with critical alert count
  - **Warning**: Yellow indicator with warning count  
  - **All Systems OK**: Green indicator when no critical alerts

### 2. Alert Grouping
- Groups similar alerts (Router Uptime, Connection State, GSM Signal) into single rows
- Shows occurrence count for repeated alerts
- Displays latest timestamp and current status

### 3. Advanced Filtering
- **Device Filter**: Filter alerts by specific devices
- **Severity Filter**: Filter by Critical, Warning, or Info levels
- **Time Range Filter**: 
  - Last Hour
  - Last Day
  - Last Week
  - Custom date range

### 4. Real-time Updates
- AJAX-based filtering without page refresh
- Loading indicators during filter operations
- Error handling for failed requests

### 5. Off-hours Alert Management
- Automatically moves non-critical alerts to low-priority during off-hours (outside 8 AM - 6 PM)
- Critical alerts always remain visible

## Usage

### Basic Display
The widget automatically displays when viewing an RTU gateway dashboard. It shows:
- Alert summary with counts by severity
- Grouped alerts list with latest information
- "No Active Alerts" message when appropriate

### Filtering Alerts
1. Use the filter controls at the top of the widget
2. Select devices, severity levels, and time ranges
3. Click "Apply Filters" to update the display
4. Use "Clear" to reset all filters

### Custom Date Range
1. Select "Custom Range" from the time range dropdown
2. Enter start and end dates in the date inputs that appear
3. Apply filters to see alerts within the specified range

## API Endpoints

### Filter Alerts
```
POST /api/rtu/gateway/{gateway}/alerts/filter
```

**Request Body:**
```json
{
  "filters": {
    "device_ids": [1, 2, 3],
    "severity": ["critical", "warning"],
    "time_range": "last_day",
    "start_date": "2024-01-01T00:00:00Z",
    "end_date": "2024-01-02T00:00:00Z"
  }
}
```

**Response:**
```json
{
  "success": true,
  "html": "<div>...</div>",
  "counts": {
    "critical": 2,
    "warning": 1,
    "info": 0,
    "total": 3
  },
  "filters_applied": {...},
  "timestamp": "2024-01-01T12:00:00Z"
}
```

## Implementation Details

### Widget Class
- **File**: `app/Filament/Widgets/RTUAlertsWidget.php`
- **Extends**: `Filament\Widgets\Widget`
- **View**: `filament.widgets.rtu-alerts-widget`

### Key Methods
- `getData()`: Retrieves and processes alert data
- `getDeviceStatusIndicator()`: Creates status indicators
- `getStatusSummary()`: Generates alert summary text
- `getSeverityOptions()`: Returns available severity levels
- `getTimeRangeOptions()`: Returns time range options

### Templates
- **Main Widget**: `resources/views/filament/widgets/rtu-alerts-widget.blade.php`
- **Alerts List**: `resources/views/filament/widgets/partials/rtu-alerts-list.blade.php`

### JavaScript Features
- Real-time filtering without page refresh
- Custom date range toggle
- Loading states and error handling
- Filter state management

## Testing

### Unit Tests
- **File**: `tests/Unit/RTUAlertsWidgetBasicTest.php`
- Tests status summary generation
- Tests device status indicators
- Tests filter option generation

### Feature Tests
- **File**: `tests/Feature/RTUAlertsAPITest.php`
- Tests API endpoint authentication
- Tests filter parameter validation
- Tests response structure

## Requirements Satisfied

This implementation satisfies all requirements from the RTU Dashboard Improvements specification:

- **2.1-2.5**: Alert grouping and simplified management
- **4.1-4.4**: Simplified device status indicators
- **8.1-8.6**: Advanced alert filtering capabilities

## Error Handling

The widget includes comprehensive error handling:
- Graceful degradation when RTU gateway is unreachable
- User-friendly error messages for API failures
- Fallback displays for missing data
- Validation of filter parameters

## Performance Considerations

- Efficient alert grouping algorithms
- Pagination for large alert sets (limited to 10 displayed)
- Caching of filter options
- Optimized database queries with proper indexing