# RTU Dashboard Configuration and Preferences

## Overview

This document describes the RTU dashboard configuration and preferences system implemented for task 13 of the RTU Dashboard Improvements specification.

## Components Implemented

### 1. Database Schema

- **Migration**: `2025_08_20_102548_create_rtu_trend_preferences_table.php`
- **Table**: `rtu_trend_preferences`
- **Fields**:
  - `id` - Primary key
  - `user_id` - Foreign key to users table
  - `gateway_id` - Foreign key to gateways table
  - `selected_metrics` - JSON array of selected metrics
  - `time_range` - Time range for trend data (1h, 6h, 24h, 7d)
  - `chart_type` - Chart visualization type (line, area, bar)
  - `created_at`, `updated_at` - Timestamps
  - **Unique constraint**: `user_id` + `gateway_id`

### 2. Model

- **File**: `app/Models/RTUTrendPreference.php`
- **Features**:
  - Eloquent relationships to User and Gateway models
  - JSON casting for selected_metrics
  - Static methods for available options
  - Validation methods for data integrity
  - Default configuration constants

### 3. Service Layer

- **File**: `app/Services/RTUDashboardConfigService.php`
- **Methods**:
  - `getTrendPreferences()` - Get or create user preferences
  - `updateTrendPreferences()` - Update user preferences with validation
  - `createDefaultPreferences()` - Create default preferences for new users
  - `resetToDefaults()` - Reset preferences to system defaults
  - `deletePreferences()` - Remove user preferences
  - `getBulkPreferences()` - Get preferences for multiple gateways
  - `exportUserPreferences()` - Export preferences for backup
  - `importUserPreferences()` - Import preferences from backup
  - `getDashboardConfig()` - Get dashboard configuration options

### 4. API Controller

- **File**: `app/Http/Controllers/RTUPreferencesController.php`
- **Endpoints**:
  - `GET /api/rtu/gateway/{gateway}/preferences` - Get preferences
  - `POST /api/rtu/gateway/{gateway}/preferences` - Update preferences
  - `POST /api/rtu/gateway/{gateway}/preferences/reset` - Reset to defaults
  - `DELETE /api/rtu/gateway/{gateway}/preferences` - Delete preferences
  - `GET /api/rtu/preferences/config` - Get configuration options
  - `POST /api/rtu/preferences/bulk` - Get bulk preferences
  - `GET /api/rtu/preferences/export` - Export preferences
  - `POST /api/rtu/preferences/import` - Import preferences

### 5. Widget Integration

- **Updated**: `app/Filament/Widgets/RTUTrendWidget.php`
- **Features**:
  - Automatic preference loading in `mount()` method
  - User-specific preference integration
  - Fallback to defaults when no preferences exist

### 6. User Interface Components

- **File**: `resources/views/components/rtu-preference-manager.blade.php`
- **Features**:
  - Modal-based preference management interface
  - Real-time form validation
  - AJAX-based preference updates
  - Reset to defaults functionality
  - User-friendly error handling

- **Updated**: `resources/views/filament/widgets/rtu-trend-widget.blade.php`
- **Features**:
  - Preferences button in widget header
  - Modal integration for preference management
  - JavaScript event handling for preference updates

### 7. Factory for Testing

- **File**: `database/factories/RTUTrendPreferenceFactory.php`
- **Features**:
  - Factory methods for test data generation
  - State methods for common scenarios
  - Relationship handling for users and gateways

### 8. Comprehensive Test Suite

- **Unit Tests**:
  - `tests/Unit/RTUDashboardConfigServiceTest.php` - Service layer testing
  - `tests/Unit/RTUTrendPreferenceTest.php` - Model testing

- **Feature Tests**:
  - `tests/Feature/RTUPreferencesControllerTest.php` - API endpoint testing

## Available Metrics

The system supports the following metrics for trend visualization:

- **signal_strength**: Signal Strength (RSSI) in dBm
- **cpu_load**: CPU Load as percentage
- **memory_usage**: Memory Usage as percentage
- **analog_input**: Analog Input voltage (0-10V)

## Available Time Ranges

- **1h**: 1 Hour
- **6h**: 6 Hours
- **24h**: 24 Hours (default)
- **7d**: 7 Days

## Available Chart Types

- **line**: Line Chart (default)
- **area**: Area Chart
- **bar**: Bar Chart

## Default Configuration

When a user first accesses the RTU dashboard for a gateway, the system automatically creates default preferences:

- **Selected Metrics**: `["signal_strength"]`
- **Time Range**: `"24h"`
- **Chart Type**: `"line"`

## Validation and Error Handling

The system includes comprehensive validation:

- **Metrics Validation**: Ensures selected metrics are from available options
- **Time Range Validation**: Validates against supported time ranges
- **Chart Type Validation**: Ensures chart type is supported
- **Empty Metrics Prevention**: Requires at least one metric to be selected
- **Database Constraints**: Unique constraint prevents duplicate preferences

## Security Features

- **Authorization**: All API endpoints require authentication
- **Gateway Access Control**: Users can only manage preferences for gateways they have access to
- **Input Validation**: All user inputs are validated before processing
- **SQL Injection Prevention**: Uses Eloquent ORM for database operations

## Integration Points

### RTU Trend Widget Integration

The RTU Trend Widget automatically loads user preferences when mounted:

```php
protected function loadUserPreferences(): void
{
    if (!$this->gateway || !auth()->check()) {
        $this->selectedMetrics = ['signal_strength'];
        $this->timeRange = '24h';
        return;
    }

    $configService = app(\App\Services\RTUDashboardConfigService::class);
    $preferences = $configService->getTrendPreferences(auth()->user(), $this->gateway);
    
    $this->selectedMetrics = $preferences->selected_metrics ?? ['signal_strength'];
    $this->timeRange = $preferences->time_range ?? '24h';
}
```

### JavaScript Integration

The preference manager includes JavaScript for real-time updates:

```javascript
// Listen for preference updates
window.addEventListener('refresh-trend-widget', function() {
    Livewire.find('{{ $this->getId() }}').call('$refresh');
});
```

## Usage Examples

### Getting User Preferences

```php
$configService = app(RTUDashboardConfigService::class);
$preferences = $configService->getTrendPreferences($user, $gateway);
```

### Updating Preferences

```php
$data = [
    'selected_metrics' => ['signal_strength', 'cpu_load'],
    'time_range' => '6h',
    'chart_type' => 'area'
];

$preferences = $configService->updateTrendPreferences($user, $gateway, $data);
```

### API Usage

```javascript
// Update preferences via API
fetch('/api/rtu/gateway/1/preferences', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer ' + token
    },
    body: JSON.stringify({
        selected_metrics: ['signal_strength', 'cpu_load'],
        time_range: '6h',
        chart_type: 'area'
    })
});
```

## Requirements Satisfied

This implementation satisfies all requirements from the specification:

- ✅ **9.1-9.6**: Enhanced trend visualization with user preferences
- ✅ **10.4-10.5**: User interface improvements with preference management
- ✅ **Database Schema**: RTU trend preferences table created
- ✅ **User Preference Storage**: Complete preference management system
- ✅ **Configuration Service**: RTU dashboard configuration service
- ✅ **Management Interface**: Preference management UI components
- ✅ **Default Setup**: Automatic default configuration for new users
- ✅ **Validation**: Comprehensive preference validation and error handling
- ✅ **Unit Tests**: Complete test coverage for all components

## Future Enhancements

Potential future improvements could include:

1. **Preference Templates**: Pre-defined preference sets for different use cases
2. **Sharing Preferences**: Allow users to share preference configurations
3. **Advanced Validation**: More sophisticated metric compatibility checking
4. **Preference History**: Track changes to user preferences over time
5. **Bulk Operations**: Mass preference updates across multiple gateways