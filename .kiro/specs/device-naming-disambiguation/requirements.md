# Requirements Document

## Introduction

The energy monitoring system currently allows devices across different gateways to have identical names, which creates confusion when identifying and managing devices. This feature will implement a comprehensive device naming and identification system that ensures unique device identification while maintaining user-friendly display names and providing clear context about which gateway each device belongs to.

## Requirements

### Requirement 1

**User Story:** As a system administrator, I want devices to have unique identifiers across all gateways, so that I can unambiguously identify any device in the system.

#### Acceptance Criteria

1. WHEN a device is created THEN the system SHALL generate a unique identifier that combines gateway name and device name
2. WHEN displaying device lists THEN the system SHALL show both the device name and gateway context
3. WHEN two devices have the same name on different gateways THEN the system SHALL clearly distinguish them in all interfaces
4. IF a device name already exists on the same gateway THEN the system SHALL prevent creation and show an error message

### Requirement 2

**User Story:** As a user viewing the dashboard, I want to easily identify which gateway each device belongs to, so that I can understand the physical location and context of each device.

#### Acceptance Criteria

1. WHEN viewing device lists THEN the system SHALL display gateway information alongside device names
2. WHEN creating or editing devices THEN the system SHALL show the associated gateway name
3. WHEN viewing readings or alerts THEN the system SHALL include gateway context in the display
4. WHEN searching for devices THEN the system SHALL allow filtering by gateway

### Requirement 3

**User Story:** As a system administrator, I want to enforce unique device names within each gateway, so that devices on the same gateway are clearly distinguishable.

#### Acceptance Criteria

1. WHEN creating a new device THEN the system SHALL validate that the name is unique within the selected gateway
2. WHEN updating a device name THEN the system SHALL validate uniqueness within the gateway
3. IF a duplicate name is attempted THEN the system SHALL display a clear error message with suggestions
4. WHEN validation fails THEN the system SHALL highlight the conflicting field and provide guidance

### Requirement 4

**User Story:** As a user managing registers and readings, I want device identification to be consistent across all system components, so that data relationships remain clear and accurate.

#### Acceptance Criteria

1. WHEN viewing registers THEN the system SHALL display full device context (gateway + device name)
2. WHEN viewing readings THEN the system SHALL show complete device identification
3. WHEN creating alerts THEN the system SHALL include gateway context in alert messages
4. WHEN exporting data THEN the system SHALL include both gateway and device identification

### Requirement 5

**User Story:** As a system administrator, I want to migrate existing devices to the new naming system, so that current data remains accessible and properly identified.

#### Acceptance Criteria

1. WHEN the system is updated THEN existing devices SHALL maintain their current functionality
2. WHEN displaying existing devices THEN the system SHALL show gateway context even for legacy data
3. WHEN migrating data THEN the system SHALL preserve all existing relationships between devices, registers, and readings
4. IF naming conflicts exist in legacy data THEN the system SHALL provide tools to resolve them