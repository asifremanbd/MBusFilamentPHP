# Design: User Management, Email Notification & RBAC

## Overview

This design implements a comprehensive user management system with role-based access control (RBAC) and email notification preferences for the existing Laravel-based energy monitoring system. The solution leverages Laravel Breeze for authentication, Filament for admin panel management, and Laravel's notification system for email alerts.

The system builds upon the existing User model and alert infrastructure while adding role-based permissions, notification preferences, and secure access controls.

## Architecture

### Authentication Layer
- **Laravel Breeze**: Provides secure authentication scaffolding with login, registration, and password reset functionality
- **Session-based Authentication**: Maintains user sessions securely with Laravel's built-in session management
- **Route Protection**: Middleware-based protection for admin routes and API endpoints
- **Role-based Middleware**: Custom middleware to enforce role-based access control

### Authorization Layer
- **Role-based Access Control (RBAC)**: Two-tier role system (admin/operator) with granular permissions
- **Policy-based Authorization**: Laravel policies for model-level access control
- **Gate-based Permissions**: Custom gates for feature-level access control
- **Filament Resource Policies**: Integration with Filament's authorization system

### Notification System
- **Laravel Notifications**: Built-in notification system for email delivery
- **Preference-based Filtering**: User preferences control notification delivery
- **Alert Type Classification**: Support for OutOfRangeAlert, OffHoursAlert, and CriticalAlert
- **Queue-based Processing**: Asynchronous notification delivery for performance

## Components and Interfaces

### User Management Components

#### Enhanced User Model
```php
class User extends Authenticatable
{
    protected $fillable = [
        'name', 'email', 'password', 'role', 'phone',
        'email_notifications', 'notification_critical_only'
    ];
    
    protected $casts = [
        'email_notifications' => 'boolean',
        'notification_critical_only' => 'boolean'
    ];
    
    // Role checking methods
    public function isAdmin(): bool
    public function isOperator(): bool
    
    // Notification preference methods
    public function shouldReceiveNotification(string $alertType): bool
    public function getAssignedDevices(): Collection
}
```

#### User Assignment System
```php
class UserDeviceAssignment extends Model
{
    protected $fillable = ['user_id', 'device_id'];
    
    public function user(): BelongsTo
    public function device(): BelongsTo
}
```

### Authentication Components

#### Custom Middleware
```php
class EnsureUserRole
{
    public function handle(Request $request, Closure $next, string $role): Response
}

class AdminOnly
{
    public function handle(Request $request, Closure $next): Response
}
```

#### Authorization Policies
```php
class DevicePolicy
{
    public function viewAny(User $user): bool
    public function view(User $user, Device $device): bool
    public function create(User $user): bool
    public function update(User $user, Device $device): bool
    public function delete(User $user, Device $device): bool
}
```

### Filament Admin Panel Components

#### User Resource
```php
class UserResource extends Resource
{
    // CRUD operations for user management
    // Role-based form fields and table columns
    // Bulk actions for user management
}
```

#### Role-based Dashboard
```php
class AdminDashboard extends Page
{
    // Full system overview for admin users
    // Device, gateway, and alert statistics
    // User management widgets
}

class OperatorDashboard extends Page
{
    // Limited view for operator users
    // Assigned devices and related alerts only
}
```

### Notification Enhancement Components

#### Enhanced Alert Service
```php
class AlertService
{
    public function sendNotificationToEligibleUsers(Alert $alert): void
    {
        $users = $this->getEligibleUsers($alert);
        foreach ($users as $user) {
            if ($this->shouldUserReceiveAlert($user, $alert)) {
                $user->notify($this->createNotification($alert));
            }
        }
    }
    
    private function getEligibleUsers(Alert $alert): Collection
    private function shouldUserReceiveAlert(User $user, Alert $alert): bool
}
```

## Data Models

### Enhanced User Table Schema
```sql
CREATE TABLE users (
    id BIGINT UNSIGNED PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    email_verified_at TIMESTAMP NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'operator') DEFAULT 'operator',
    phone VARCHAR(20) NULL,
    email_notifications BOOLEAN DEFAULT TRUE,
    notification_critical_only BOOLEAN DEFAULT FALSE,
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

### User Device Assignment Table
```sql
CREATE TABLE user_device_assignments (
    id BIGINT UNSIGNED PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    device_id BIGINT UNSIGNED NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_by BIGINT UNSIGNED NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_user_device (user_id, device_id)
);
```

### Role Permission Matrix
```php
const ROLE_PERMISSIONS = [
    'admin' => [
        'devices' => ['create', 'read', 'update', 'delete'],
        'gateways' => ['create', 'read', 'update', 'delete'],
        'alerts' => ['read', 'resolve'],
        'users' => ['create', 'read', 'update', 'delete'],
        'settings' => ['read', 'update'],
        'notifications' => ['manage_all']
    ],
    'operator' => [
        'devices' => ['read_assigned'],
        'gateways' => ['read_assigned'],
        'alerts' => ['read_assigned'],
        'notifications' => ['manage_own']
    ]
];
```

## Error Handling

### Authentication Errors
- **Invalid Credentials**: Clear error messages for failed login attempts
- **Session Expiry**: Automatic redirect to login with session restoration
- **Unauthorized Access**: HTTP 403 responses with appropriate error pages
- **Role Mismatch**: Graceful handling of insufficient permissions

### Authorization Errors
- **Resource Access Denied**: Policy-based error responses
- **Assignment Violations**: Validation for device assignment constraints
- **Role Escalation Prevention**: Strict validation of role changes

### Notification Errors
- **Email Delivery Failures**: Retry mechanism with exponential backoff
- **Invalid Email Addresses**: Validation and error logging
- **Queue Processing Errors**: Dead letter queue for failed notifications
- **Preference Conflicts**: Fallback to safe defaults

### Error Logging Strategy
```php
// Structured logging for security events
Log::channel('security')->warning('Unauthorized access attempt', [
    'user_id' => $user->id,
    'resource' => $resource,
    'action' => $action,
    'ip_address' => $request->ip()
]);

// Notification delivery tracking
Log::channel('notifications')->info('Alert notification sent', [
    'user_id' => $user->id,
    'alert_id' => $alert->id,
    'notification_type' => get_class($notification),
    'delivery_status' => 'success'
]);
```

## Testing Strategy

### Unit Testing
- **User Model Tests**: Role checking, notification preferences, device assignments
- **Policy Tests**: Authorization logic for all resources and actions
- **Notification Tests**: Preference filtering, alert type handling
- **Service Tests**: AlertService notification logic, user eligibility

### Integration Testing
- **Authentication Flow**: Login, logout, session management
- **Authorization Integration**: Middleware, policies, and gates working together
- **Notification Delivery**: End-to-end alert processing and email delivery
- **Filament Integration**: Admin panel access control and resource management

### Feature Testing
- **Role-based Access**: Complete user journeys for admin and operator roles
- **Device Assignment**: Assignment creation, modification, and enforcement
- **Alert Processing**: Full alert lifecycle with notification delivery
- **User Management**: CRUD operations with proper authorization

### Security Testing
- **Authentication Bypass**: Attempt to access protected routes without authentication
- **Authorization Escalation**: Attempt to perform actions beyond user role
- **Session Security**: Session fixation, hijacking, and timeout testing
- **Input Validation**: SQL injection, XSS, and other injection attacks

### Test Data Strategy
```php
// Factory for test users with different roles
class UserFactory extends Factory
{
    public function admin(): static
    public function operator(): static
    public function withNotificationPreferences(array $preferences): static
}

// Seeder for test scenarios
class RoleBasedTestSeeder extends Seeder
{
    public function run(): void
    {
        // Create admin users with various notification preferences
        // Create operator users with device assignments
        // Create test alerts for notification testing
    }
}
```

### Performance Testing
- **Notification Scalability**: Test with large numbers of users and alerts
- **Database Query Optimization**: Ensure efficient queries for role-based filtering
- **Session Management**: Test concurrent user sessions and memory usage
- **Queue Processing**: Test notification queue performance under load