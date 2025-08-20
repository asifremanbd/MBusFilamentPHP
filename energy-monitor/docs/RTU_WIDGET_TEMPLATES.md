# RTU Dashboard Widget Templates

This document provides an overview of the RTU dashboard widget templates, their features, and implementation details.

## Overview

The RTU dashboard includes five specialized widget templates designed for monitoring and controlling Teltonika RUT956 gateways:

1. **RTU System Health Widget** - Monitors CPU, memory, and uptime
2. **RTU Network Status Widget** - Displays WAN connection and cellular signal quality
3. **RTU I/O Monitoring Widget** - Shows digital inputs/outputs and analog input with control capabilities
4. **RTU Alerts Widget** - Manages and filters system alerts with grouping
5. **RTU Trend Widget** - Visualizes historical data with multiple metric support

## Widget Templates

### 1. RTU System Health Widget
**File:** `resources/views/filament/widgets/rtu-system-health-widget.blade.php`

**Features:**
- Overall health score with color-coded status
- Router uptime display in hours
- CPU load percentage with progress bar and thresholds
- Memory usage percentage with progress bar and thresholds
- Visual status indicators (normal, warning, critical)
- Responsive grid layout (1 column on mobile, 2 on tablet, 3 on desktop)
- Error handling with fallback displays

**Key Components:**
- Health score gradient background
- System metrics cards with hover effects
- Progress bars with threshold indicators
- Status badges with appropriate colors
- Last updated timestamp

### 2. RTU Network Status Widget
**File:** `resources/views/filament/widgets/rtu-network-status-widget.blade.php`

**Features:**
- WAN connection status and IP address
- SIM card details (ICCID, APN, Operator)
- Signal quality metrics (RSSI, RSRP, RSRQ, SINR)
- Signal strength visualization with progress bars
- Connection status indicators
- Responsive layout with proper mobile optimization

**Key Components:**
- Network section cards with hover effects
- Signal quality meters with color coding
- SIM details grid layout
- Connection status badges
- Signal strength animations

### 3. RTU I/O Monitoring Widget
**File:** `resources/views/filament/widgets/rtu-io-monitoring-widget.blade.php`

**Features:**
- Digital input status display (DI1, DI2)
- Digital output control with toggle buttons (DO1, DO2)
- Analog input voltage reading (0-10V)
- Real-time I/O control via AJAX
- Visual state indicators with color coding
- Error handling and user feedback
- Responsive grid layout

**Key Components:**
- I/O cards with state-based styling
- Control buttons with loading states
- AJAX-powered digital output control
- Success/error message system
- Analog input display with precision formatting

**JavaScript Functions:**
- `toggleDigitalOutput()` - Controls digital outputs
- Real-time UI updates
- Network status monitoring
- Error handling and retry logic

### 4. RTU Alerts Widget
**File:** `resources/views/filament/widgets/rtu-alerts-widget.blade.php`

**Features:**
- Alert filtering by device, severity, and time range
- Grouped alert display with occurrence counts
- Real-time filtering without page refresh
- Alert summary with status indicators
- Custom date range selection
- "No alerts" state with positive messaging

**Key Components:**
- Filter panel with multiple selection options
- Alert summary cards
- Grouped alert list with severity icons
- Filter controls with AJAX functionality
- Custom date range picker

**JavaScript Functions:**
- Real-time alert filtering
- Filter state management
- AJAX-powered filter application
- Custom date range handling

### 5. RTU Trend Widget
**File:** `resources/views/filament/widgets/rtu-trend-widget.blade.php`

**Features:**
- Multiple metric selection (Signal Strength, CPU, Memory, Analog Input)
- Interactive chart with ApexCharts
- Time range selection (1h, 6h, 24h, 7d)
- Current value display for selected metrics
- Conditional widget rendering based on data availability
- Multi-series chart support with different scales

**Key Components:**
- Metric selection interface with checkboxes
- ApexCharts integration for data visualization
- Current values grid
- Time range selector
- Chart configuration with multiple Y-axes

**JavaScript Functions:**
- Chart initialization and rendering
- Metric selection handling
- Time range updates
- Livewire integration for real-time updates

## Styling and CSS

### Main Stylesheets
- `resources/css/app.css` - Main application styles with imports
- `resources/css/rtu-widgets.css` - RTU-specific widget styles
- `resources/css/dashboard-customization.css` - General dashboard styles

### Key CSS Classes

#### System Health Widget
- `.health-score-gradient` - Gradient background for health score
- `.system-metric-card` - Individual metric cards with hover effects
- `.metric-progress-bar` - Progress bar containers
- `.metric-progress-fill` - Progress bar fills with status colors

#### Network Status Widget
- `.network-section-card` - Network information cards
- `.signal-quality-meter` - Signal strength visualization
- `.network-metric-grid` - Responsive grid for network metrics
- `.signal-strength-bar` - Animated signal bars

#### I/O Monitoring Widget
- `.io-card` - I/O status cards with state-based styling
- `.io-control-button` - Control buttons with hover effects
- `.analog-input-display` - Analog input value display
- `.io-grid` - Responsive grid for I/O elements

#### Alerts Widget
- `.alert-filters-panel` - Filter controls container
- `.alert-item` - Individual alert cards
- `.alert-summary-card` - Alert summary display
- `.status-indicator` - Status dots and labels

#### Trend Widget
- `.metric-selector-panel` - Metric selection interface
- `.chart-container` - Chart display area
- `.current-values-grid` - Current value cards grid
- `.metric-checkbox-grid` - Checkbox selection grid

### Responsive Design

All widgets implement responsive design with the following breakpoints:
- **Mobile (< 480px):** Single column layout, compact spacing
- **Tablet (480px - 768px):** Two-column layout where appropriate
- **Desktop (> 768px):** Full multi-column layout

### Dark Mode Support

All widgets include dark mode support through:
- CSS custom properties for theme colors
- `@media (prefers-color-scheme: dark)` queries
- Filament's built-in dark mode classes

## JavaScript Functionality

### Main JavaScript File
`resources/js/rtu-widgets.js` - RTU Widget Manager class

### Key Features
- **RTUWidgetManager Class** - Central management for all widget interactions
- **I/O Control** - Digital output control with AJAX
- **Alert Filtering** - Real-time alert filtering
- **Metric Selection** - Trend chart metric selection
- **Auto-refresh** - Periodic widget data updates
- **Error Handling** - Comprehensive error handling and user feedback

### Global Functions
- `toggleDigitalOutput()` - Backward compatibility for I/O control
- Network status monitoring
- Notification system
- Widget refresh mechanisms

## Integration with Filament

### Widget Base Classes
All widgets extend Filament's base widget classes:
- `BaseWidget` - Core widget functionality
- Livewire integration for real-time updates
- Filament section components for consistent styling

### Data Flow
1. Widget classes fetch data from RTU services
2. Data is passed to Blade templates
3. Templates render with Filament components
4. JavaScript enhances interactivity
5. AJAX calls update data without page refresh

## Error Handling

### Template-Level Error Handling
- Graceful degradation when data is unavailable
- Error state displays with helpful messages
- Fallback content for missing data

### JavaScript Error Handling
- Try-catch blocks for all AJAX operations
- User-friendly error notifications
- Automatic retry mechanisms
- Network status monitoring

## Performance Considerations

### Optimization Features
- Lazy loading of chart libraries
- Efficient DOM updates
- Debounced user interactions
- Minimal re-renders with Livewire
- CSS animations for smooth transitions

### Caching Strategy
- Widget data caching in services
- Browser-side state persistence
- Efficient API calls with proper headers

## Accessibility

### ARIA Support
- Proper ARIA labels for interactive elements
- Screen reader friendly content
- Keyboard navigation support
- Focus management

### Visual Accessibility
- High contrast color schemes
- Scalable text and icons
- Clear visual hierarchy
- Status indicators with multiple cues (color + text + icons)

## Browser Compatibility

### Supported Browsers
- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

### Polyfills and Fallbacks
- CSS Grid fallbacks for older browsers
- JavaScript ES6+ features with Babel transpilation
- Progressive enhancement approach

## Maintenance and Updates

### File Structure
```
resources/
├── css/
│   ├── app.css                    # Main styles with imports
│   ├── rtu-widgets.css           # RTU-specific styles
│   └── dashboard-customization.css # General dashboard styles
├── js/
│   ├── app.js                    # Main JavaScript entry
│   ├── rtu-widgets.js           # RTU widget functionality
│   └── dashboard-manager.js      # Dashboard management
└── views/filament/widgets/
    ├── rtu-system-health-widget.blade.php
    ├── rtu-network-status-widget.blade.php
    ├── rtu-io-monitoring-widget.blade.php
    ├── rtu-alerts-widget.blade.php
    ├── rtu-trend-widget.blade.php
    └── partials/
        └── rtu-alerts-list.blade.php
```

### Update Guidelines
1. Test all widgets after Filament updates
2. Verify JavaScript compatibility with new browser versions
3. Update CSS for new design requirements
4. Maintain backward compatibility for existing installations
5. Document any breaking changes

## Testing

### Manual Testing Checklist
- [ ] All widgets render correctly on different screen sizes
- [ ] I/O controls work properly with real RTU devices
- [ ] Alert filtering functions correctly
- [ ] Charts display data accurately
- [ ] Error states display appropriately
- [ ] Dark mode works correctly
- [ ] Accessibility features function properly

### Automated Testing
- Unit tests for JavaScript functions
- Integration tests for widget data flow
- Visual regression tests for UI consistency
- Performance tests for large datasets

## Troubleshooting

### Common Issues
1. **Charts not rendering:** Check ApexCharts library loading
2. **I/O controls not working:** Verify CSRF token and API endpoints
3. **Styles not applying:** Check CSS file imports and build process
4. **JavaScript errors:** Check browser console for specific errors
5. **Responsive issues:** Test on actual devices, not just browser resize

### Debug Mode
Enable debug mode by adding to `.env`:
```
RTU_WIDGET_DEBUG=true
```

This enables additional console logging and error details.