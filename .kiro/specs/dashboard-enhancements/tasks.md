# Implementation Plan

- [x] 1. Set up database schema for user permissions and dashboard configuration


  - Create migration for user_gateway_assignments table with foreign key relationships
  - Create migration for user_dashboard_configs table with JSON columns for widget and layout configuration
  - Create migration for dashboard_access_logs table for audit logging
  - Write database seeders to populate test data for user assignments
  - _Requirements: 1.1, 1.2, 3.1, 3.2_

- [x] 2. Implement core permission service and user model enhancements


  - Extend User model with methods for getAssignedDeviceIds(), getAssignedGatewayIds(), and permission checking
  - Create UserPermissionService class with methods for getAuthorizedGateways() and getAuthorizedDevices()
  - Implement canAccessWidget() method in UserPermissionService for widget-level authorization
  - Write unit tests for User model permission methods and UserPermissionService
  - _Requirements: 1.1, 1.3, 1.4, 1.5_

- [x] 3. Create user assignment models and relationships


  - Create UserGatewayAssignment model with user and gateway relationships
  - Update existing UserDeviceAssignment model if needed for consistency
  - Create UserDashboardConfig model with JSON casting for widget and layout configurations
  - Implement model methods for getWidgetVisibility(), getWidgetPosition(), and getWidgetSize()
  - Write unit tests for all assignment models and their relationships
  - _Requirements: 3.1, 3.2, 3.3, 4.4, 4.5_

- [x] 4. Implement dashboard configuration service


  - Create DashboardConfigService class with getUserDashboardConfig() method
  - Implement updateWidgetVisibility() and updateWidgetLayout() methods
  - Add getDefaultWidgetConfig() and getDefaultLayoutConfig() methods for initial setup
  - Create configuration validation methods to ensure data integrity
  - Write unit tests for DashboardConfigService methods
  - _Requirements: 4.1, 4.4, 4.5, 4.6_



- [x] 5. Create base widget system with permission awareness


  - Create BaseWidget abstract class with permission checking capabilities
  - Implement widget authorization middleware that checks user permissions before rendering
  - Create widget factory pattern for dynamic widget instantiation based on permissions
  - Add error handling for unauthorized widget access with graceful degradation
  - Write unit tests for BaseWidget class and permission checking logic
  - _Requirements: 1.1, 1.6, 4.1, 4.7_

- [x] 6. Implement Global Dashboard widgets


  - Create SystemOverviewWidget with calculateTotalConsumption() and getActiveDevicesCount() methods
  - Implement CrossGatewayAlertsWidget with getAlertsByType() and getRecentAlerts() methods
  - Create TopConsumingGatewaysWidget with data aggregation from authorized gateways only
  - Add SystemHealthWidget with calculateSystemHealth() method for authorized resources
  - Write unit tests for each Global Dashboard widget with permission filtering
  - _Requirements: 1.1, 1.6, 4.2, 4.7_

- [x] 7. Implement Gateway-Based Dashboard widgets


  - Create GatewayDeviceListWidget with getDeviceStatus() and getLastReading() methods
  - Implement RealTimeReadingsWidget with getLiveReadings() and getReadingTrends() methods
  - Create GatewayStatsWidget showing gateway communication status and device health indicators
  - Add GatewayAlertsWidget for gateway-specific alert management
  - Write unit tests for each Gateway-Based Dashboard widget with device permission filtering
  - _Requirements: 1.1, 1.6, 4.3, 4.7_

- [x] 8. Create enhanced dashboard controllers with permission integration


  - Update DashboardController with globalDashboard() method that filters data by user permissions
  - Implement gatewayDashboard() method with gateway authorization checks
  - Add getAuthorizedWidgets() method to filter widgets based on user permissions
  - Create API endpoints for widget configuration updates (visibility, positioning, sizing)
  - Write integration tests for dashboard controllers with various user permission scenarios
  - _Requirements: 1.1, 1.4, 4.1, 4.4, 4.5_

- [x] 9. Implement user management interface for permission assignment


  - Create Filament UserResource with device and gateway assignment fields
  - Add bulk assignment functionality for assigning multiple devices/gateways to users
  - Implement permission hierarchy display showing gateway-device relationships
  - Create audit logging for all permission changes with administrator tracking
  - Write feature tests for user management interface including bulk operations
  - _Requirements: 3.1, 3.2, 3.3, 3.6, 3.7_

- [x] 10. Add real-time permission updates and session management


  - Implement permission caching system with automatic invalidation on changes
  - Create real-time dashboard updates when user permissions are modified
  - Add session-based permission validation with periodic refresh
  - Implement WebSocket or polling mechanism for live permission updates
  - Write integration tests for real-time permission changes and dashboard updates
  - _Requirements: 1.2, 1.5, 3.6_

- [x] 11. Create dashboard access logging and security features


  - Implement DashboardAccessLogger middleware for all dashboard requests
  - Create dashboard_access_logs table population with user, resource, and access details
  - Add IP address and user agent tracking for security audit purposes
  - Implement access attempt monitoring with failed access alerts
  - Write security tests for access logging and unauthorized access attempts
  - _Requirements: 1.3, 3.6_

- [x] 12. Implement error handling and fallback mechanisms


  - Create DashboardErrorHandler class with handlePermissionError() and handleWidgetError() methods
  - Implement graceful widget degradation when permissions are insufficient
  - Add fallback data display for temporarily unavailable authorized resources
  - Create user-friendly error messages for permission violations
  - Write error handling tests covering permission errors, widget failures, and network issues
  - _Requirements: 1.3, 1.5, 4.7_

- [x] 13. Add frontend dashboard customization interface


  - Create drag-and-drop widget positioning interface using JavaScript/Alpine.js
  - Implement widget show/hide toggles with real-time preview
  - Add widget resizing handles with grid-based positioning system
  - Create dashboard type switcher (Global vs Gateway-Based) with smooth transitions
  - Write frontend tests for widget customization functionality
  - _Requirements: 4.1, 4.4, 4.5, 4.6_

- [x] 14. Implement gateway selector and navigation
  - Create gateway dropdown selector that shows only authorized gateways
  - Add URL-based gateway persistence for bookmarking and sharing
  - Implement breadcrumb navigation showing current gateway context
  - Create quick gateway switching with keyboard shortcuts
  - Write navigation tests for gateway selection and URL handling
  - _Requirements: 1.4, 4.6_

- [x] 15. Add comprehensive testing and performance optimization
  - Create comprehensive test suite covering all permission scenarios
  - Implement database query optimization for permission filtering
  - Add caching layers for frequently accessed permission data
  - Create performance benchmarks for dashboard loading with large datasets
  - Write load tests simulating multiple concurrent users with different permissions
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7_