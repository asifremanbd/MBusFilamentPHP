# RTU Dashboard Collapsible Sections System

## Overview

The RTU Dashboard Collapsible Sections system provides an organized, user-friendly interface for RTU monitoring widgets. It allows users to collapse/expand sections and remembers their preferences across browser sessions.

## Features

- **Persistent User Preferences**: Section states are saved per user and persist across sessions
- **Smooth Animations**: CSS transitions for expand/collapse operations
- **Responsive Design**: Works on various screen sizes
- **API Integration**: RESTful API for managing section states
- **Error Handling**: Graceful degradation when API is unavailable
- **Icon Support**: Appropriate icons for different section types

## Architecture

### Database Schema

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
```

### Default Sections

1. **System Health** (`system_health`)
   - Icon: `heroicon-o-cpu-chip`
   - Display Order: 1
   - Contains: CPU load, memory usage, uptime

2. **Network Status** (`network_status`)
   - Icon: `heroicon-o-signal`
   - Display Order: 2
   - Contains: WAN IP, SIM details, signal quality

3. **I/O Monitoring** (`io_monitoring`)
   - Icon: `heroicon-o-bolt`
   - Display Order: 3
   - Contains: Digital inputs/outputs, analog inputs

4. **Alerts** (`alerts`)
   - Icon: `heroicon-o-exclamation-triangle`
   - Display Order: 4
   - Contains: Filtered and grouped alerts

5. **Trends** (`trends`)
   - Icon: `heroicon-o-chart-bar`
   - Display Order: 5
   - Contains: Historical data visualization

## Usage

### Backend Integration

#### Using the Collapsible Section Component

```blade
<x-rtu-collapsible-section 
    section-key="system_health"
    title="System Health"
    icon="heroicon-o-cpu-chip"
    :is-collapsed="$sectionConfig['is_collapsed'] ?? false"
    :display-order="$sectionConfig['display_order'] ?? 1">
    
    <!-- Your widget content here -->
    
</x-rtu-collapsible-section>
```

#### Getting Section Configuration

```php
use App\Services\RTUDashboardSectionService;

$sectionService = app(RTUDashboardSectionService::class);
$sectionConfig = $sectionService->getSectionConfiguration(auth()->user());
```

#### Updating Section State

```php
$sectionService->updateSectionState(
    $user, 
    'system_health', 
    true, // is_collapsed
    2     // display_order (optional)
);
```

### Frontend Integration

#### JavaScript API

```javascript
// Toggle a section
toggleSection('system_health');

// Check if section manager is ready
if (window.rtuSectionManager?.isReady()) {
    // Manager is initialized
}

// Get current section state
const state = window.rtuSectionManager.getSectionState('system_health');

// Reset all sections to defaults
window.rtuSectionManager.resetToDefaults();
```

#### CSS Classes

- `.rtu-collapsible-section`: Main section container
- `.section-header`: Clickable header area
- `.section-content`: Collapsible content area
- `.collapsed`: Applied when section is collapsed
- `.expanded`: Applied when section is expanded
- `.loading`: Applied during state transitions

## API Endpoints

### GET `/api/rtu/sections`
Get user's section configuration.

**Response:**
```json
{
    "success": true,
    "sections": {
        "system_health": {
            "name": "System Health",
            "icon": "heroicon-o-cpu-chip",
            "display_order": 1,
            "is_collapsed": false
        }
    }
}
```

### POST `/api/rtu/sections/update`
Update section state.

**Request:**
```json
{
    "section_name": "system_health",
    "is_collapsed": true,
    "display_order": 2
}
```

**Response:**
```json
{
    "success": true,
    "message": "Section state updated successfully"
}
```

### POST `/api/rtu/sections/reset`
Reset all sections to default state.

**Response:**
```json
{
    "success": true,
    "message": "Sections reset to defaults"
}
```

## Configuration

### Available Icons

The system provides a mapping of common icons:

```php
$icons = [
    'cpu' => 'heroicon-o-cpu-chip',
    'memory' => 'heroicon-o-memory',
    'sim' => 'heroicon-o-device-phone-mobile',
    'input' => 'heroicon-o-arrow-down-on-square',
    'output' => 'heroicon-o-arrow-up-on-square',
    'signal' => 'heroicon-o-signal',
    'network' => 'heroicon-o-globe-alt',
    'alert' => 'heroicon-o-exclamation-triangle',
    'chart' => 'heroicon-o-chart-bar',
    'system' => 'heroicon-o-cog-6-tooth',
];
```

### Customization

#### Adding New Sections

1. Update `DEFAULT_SECTIONS` in `RTUDashboardSectionService`
2. Create corresponding widget component
3. Add section to widget templates

#### Styling Customization

Override CSS classes in your theme:

```css
.rtu-collapsible-section {
    /* Custom section styling */
}

.rtu-collapsible-section .section-header:hover {
    /* Custom hover effects */
}
```

## Error Handling

### Network Failures
- Sections continue to work with cached states
- Failed API calls are logged but don't break functionality
- Retry mechanisms for temporary failures

### Data Validation
- Section names are validated against allowed values
- Invalid requests return appropriate error responses
- Client-side validation prevents invalid states

### Graceful Degradation
- Sections work without JavaScript (no collapse functionality)
- Missing icons fall back to default
- API failures don't prevent section display

## Performance Considerations

### Debouncing
- Section state updates are debounced (500ms) to prevent excessive API calls
- Multiple rapid toggles are batched into single requests

### Caching
- Section configurations are cached in memory
- Browser session storage for offline functionality
- Efficient database queries with proper indexing

### Animations
- CSS transitions for smooth user experience
- Hardware-accelerated transforms where possible
- Configurable animation duration

## Testing

### Unit Tests
- `RTUDashboardSectionServiceTest`: Service functionality
- `RTUDashboardSectionTest`: Model operations (requires database)

### Feature Tests
- `RTUDashboardSectionControllerTest`: API endpoint testing
- Authentication and authorization testing
- Cross-user isolation testing

### JavaScript Testing
- Section toggle functionality
- State persistence
- Error handling scenarios

## Migration Guide

### From Previous Widget System

1. Wrap existing widgets in `<x-rtu-collapsible-section>` components
2. Update widget templates to use section configuration
3. Run database migration for section storage
4. Include JavaScript files in build process

### Database Migration

```bash
php artisan migrate --path=database/migrations/2025_08_20_095647_create_rtu_dashboard_sections_table.php
```

## Troubleshooting

### Common Issues

1. **Sections not collapsing**: Check JavaScript console for errors
2. **State not persisting**: Verify API endpoints are accessible
3. **Icons not displaying**: Ensure Heroicons are properly loaded
4. **Animation glitches**: Check CSS conflicts with existing styles

### Debug Mode

Enable debug logging in JavaScript:

```javascript
window.rtuSectionManager.debugMode = true;
```

This will log all section operations to the browser console.