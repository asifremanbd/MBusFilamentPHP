# Implementation Plan

- [ ] 1. Create database migration for unique constraint
  - Create migration file to add unique constraint on (gateway_id, name) combination
  - Include rollback functionality and conflict detection
  - Add composite index for performance optimization
  - _Requirements: 1.4, 3.1, 3.2_

- [ ] 2. Enhance Device model with validation and display methods
  - [ ] 2.1 Add unique validation rules for device names within gateway scope
    - Implement custom validation rule using Laravel's Rule::unique with gateway_id condition
    - Add validation to both create and update scenarios
    - Write unit tests for validation logic
    - _Requirements: 3.1, 3.2, 3.3_

  - [ ] 2.2 Add display name accessor methods to Device model
    - Implement getFullNameAttribute() method returning "Gateway Name - Device Name"
    - Implement getDisplayNameAttribute() method returning "Device Name (Gateway Name)"
    - Add scopeByGateway() method for filtering devices
    - Write unit tests for accessor methods
    - _Requirements: 2.1, 2.2, 4.1_

- [ ] 3. Enhance Gateway model with device name helper methods
  - [ ] 3.1 Add device name availability checking methods
    - Implement isDeviceNameAvailable() method to check name conflicts
    - Add support for excluding current device during updates
    - Write unit tests for availability checking
    - _Requirements: 3.1, 3.3_

  - [ ] 3.2 Add device name suggestion functionality
    - Implement suggestDeviceName() method to generate unique names
    - Add logic to append numbers for duplicate base names
    - Write unit tests for name suggestion logic
    - _Requirements: 3.3_

- [ ] 4. Update DeviceResource form with enhanced validation
  - [ ] 4.1 Add real-time name validation to device creation form
    - Update form schema to include custom validation rules
    - Add reactive validation that checks name availability on gateway selection
    - Implement clear error messaging for naming conflicts
    - _Requirements: 3.3, 3.4_

  - [ ] 4.2 Enhance form with gateway context display
    - Add gateway name display in form header or context area
    - Show device count for selected gateway
    - Add name suggestion functionality to form
    - _Requirements: 2.2, 3.3_

- [ ] 5. Update DeviceResource table with improved display
  - [ ] 5.1 Enhance device table columns and filtering
    - Ensure gateway column is prominently displayed and searchable
    - Add gateway-based filtering functionality
    - Update search to include both gateway and device names
    - _Requirements: 2.1, 2.4_

  - [ ] 5.2 Improve device display formatting
    - Update device name display to show gateway context when needed
    - Add tooltips or additional context for device identification
    - Ensure consistent formatting across all table views
    - _Requirements: 2.1, 4.1_

- [ ] 6. Update dashboard widgets with gateway context
  - [ ] 6.1 Enhance DeviceStatusWidget to show gateway information
    - Update widget template to display gateway name alongside device name
    - Add visual grouping or separation for devices from different gateways
    - Ensure gateway context is clear in device status displays
    - _Requirements: 2.1, 2.3_

  - [ ] 6.2 Update gateway dashboard device display
    - Modify gateway-dashboard.blade.php to show improved device identification
    - Ensure device lists clearly show gateway context
    - Add filtering capabilities for multi-gateway views
    - _Requirements: 2.1, 2.4_

- [ ] 7. Update reading and alert displays with device context
  - [ ] 7.1 Enhance ReadingResource to show full device context
    - Update reading table to display gateway information alongside device names
    - Modify reading display to use enhanced device identification
    - Add gateway-based filtering to readings view
    - _Requirements: 4.2, 4.4_

  - [ ] 7.2 Update AlertResource with improved device identification
    - Modify alert displays to include gateway context in device identification
    - Update alert messages to include full device context
    - Ensure alert notifications contain clear device identification
    - _Requirements: 4.3, 4.4_

- [ ] 8. Create data migration script for existing conflicts
  - [ ] 8.1 Build conflict detection and resolution tool
    - Create Artisan command to identify existing device name conflicts
    - Implement automatic name suggestion for conflicting devices
    - Add manual resolution interface for complex conflicts
    - _Requirements: 5.1, 5.4_

  - [ ] 8.2 Implement safe migration process
    - Create backup functionality before applying changes
    - Add rollback capability for migration failures
    - Implement validation to ensure data integrity after migration
    - Write tests for migration scenarios
    - _Requirements: 5.2, 5.3_

- [ ] 9. Add comprehensive test coverage
  - [ ] 9.1 Write integration tests for device management workflow
    - Test complete device creation process with validation
    - Test device editing with name conflict scenarios
    - Test device deletion and relationship integrity
    - _Requirements: 1.1, 3.1, 3.2_

  - [ ] 9.2 Write feature tests for UI components
    - Test device form validation and error display
    - Test device table filtering and search functionality
    - Test dashboard widget display with gateway context
    - _Requirements: 2.1, 2.2, 2.4_

- [ ] 10. Update API endpoints to handle new validation
  - [ ] 10.1 Enhance ReadingController API validation
    - Update API to validate device identification with gateway context
    - Ensure API error messages include clear device identification
    - Add API documentation for new validation requirements
    - _Requirements: 4.1, 4.2_

  - [ ] 10.2 Add API endpoints for device name validation
    - Create API endpoint for real-time name availability checking
    - Add API endpoint for device name suggestions
    - Write API tests for new validation endpoints
    - _Requirements: 3.1, 3.3_