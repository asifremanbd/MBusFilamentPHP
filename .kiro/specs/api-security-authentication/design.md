# Design Document - API Security and Authentication

## Overview

This design implements secure API authentication for the Energy Monitor system using Laravel Sanctum's token-based authentication. The solution provides a seamless transition from the current unauthenticated API to a secure, token-based system while maintaining backward compatibility during migration and ensuring the Python Modbus service can authenticate automatically.

## Architecture

### Authentication Flow
```
Python Service → API Request with Bearer Token → Laravel Sanctum Middleware → API Controller → Database
```

### Token Lifecycle
```
Admin Creates Token → Token Stored (Hashed) → Python Service Uses Token → Token Validated → API Access Granted
```

### Security Layers
1. **Transport Security**: HTTPS in production
2. **Token Authentication**: Laravel Sanctum Bearer tokens
3. **Rate Limiting**: Built-in Laravel rate limiting
4. **Audit Logging**: Comprehensive authentication event logging

## Components and Interfaces

### 1. Laravel Sanctum Integration

**TokenController** (`app/Http/Controllers/Api/TokenController.php`)
- Handles token creation, listing, and revocation
- Integrates with Filament admin interface
- Provides secure token generation

**API Middleware Configuration**
- Update `routes/api.php` to use Sanctum authentication
- Maintain health check endpoint without authentication
- Configure rate limiting for API endpoints

### 2. Filament Admin Interface

**API Token Resource** (`app/Filament/Resources/ApiTokenResource.php`)
- List existing tokens with creation dates and last used timestamps
- Create new tokens with configurable names and permissions
- Revoke/delete tokens with confirmation dialogs
- Display token value once upon creation (security best practice)

**Token Management Page**
- Dedicated admin page for token management
- Search and filter capabilities
- Bulk operations for token management

### 3. Python Service Authentication

**Environment Configuration Updates**
```env
# API Authentication
API_TOKEN=sanctum_token_here
API_AUTH_HEADER=Authorization: Bearer
```

**Authentication Handler** (in `poller.py`)
- Load token from environment variables
- Add authentication headers to all API requests
- Handle authentication errors gracefully
- Retry logic for temporary authentication failures

### 4. Database Schema

**Personal Access Tokens Table** (Laravel Sanctum default)
- Already exists from Sanctum installation
- Stores hashed tokens with metadata
- Tracks token usage and expiration

**Additional Audit Table** (`api_access_logs`)
```sql
CREATE TABLE api_access_logs (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    token_id BIGINT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    endpoint VARCHAR(255),
    method VARCHAR(10),
    status_code INT,
    created_at TIMESTAMP
);
```

## Data Models

### ApiToken Model Extensions
```php
class PersonalAccessToken extends Laravel\Sanctum\PersonalAccessToken
{
    protected $fillable = ['name', 'token', 'abilities', 'last_used_at'];
    
    public function scopeActive($query) {
        return $query->whereNull('revoked_at');
    }
    
    public function revoke() {
        $this->update(['revoked_at' => now()]);
    }
}
```

### API Access Log Model
```php
class ApiAccessLog extends Model
{
    protected $fillable = [
        'token_id', 'ip_address', 'user_agent', 
        'endpoint', 'method', 'status_code'
    ];
    
    public function token() {
        return $this->belongsTo(PersonalAccessToken::class);
    }
}
```

## Error Handling

### Authentication Error Responses
```json
{
    "success": false,
    "message": "Unauthenticated",
    "error_code": "AUTH_TOKEN_MISSING",
    "timestamp": "2025-07-15T10:00:00Z"
}
```

### Token Validation Errors
```json
{
    "success": false,
    "message": "Invalid or expired token",
    "error_code": "AUTH_TOKEN_INVALID",
    "timestamp": "2025-07-15T10:00:00Z"
}
```

### Rate Limiting Responses
```json
{
    "success": false,
    "message": "Too many requests",
    "error_code": "RATE_LIMIT_EXCEEDED",
    "retry_after": 60,
    "timestamp": "2025-07-15T10:00:00Z"
}
```

## Testing Strategy

### Unit Tests
- Token creation and validation
- Authentication middleware functionality
- Error handling for various scenarios
- Token revocation and cleanup

### Integration Tests
- End-to-end API authentication flow
- Python service authentication integration
- Admin interface token management
- Rate limiting and security features

### Security Tests
- Token entropy and uniqueness validation
- Authentication bypass attempts
- Rate limiting effectiveness
- SQL injection and XSS prevention

## Security Considerations

### Token Security
- Tokens generated using cryptographically secure random functions
- Tokens hashed using Laravel's secure hashing (bcrypt/argon2)
- Token transmission only via Authorization header
- No token logging in plain text

### Rate Limiting
- Implement per-IP and per-token rate limiting
- Different limits for authenticated vs unauthenticated requests
- Configurable rate limits via environment variables

### Audit Trail
- Log all authentication attempts (success and failure)
- Track token usage patterns
- Monitor for suspicious activity
- Retention policy for audit logs

## Migration Strategy

### Phase 1: Preparation
1. Install and configure Laravel Sanctum
2. Create admin interface for token management
3. Update Python service with authentication capability
4. Create comprehensive tests

### Phase 2: Soft Launch
1. Deploy authentication system with API endpoints still open
2. Generate tokens and configure Python service
3. Test authenticated requests alongside unauthenticated
4. Monitor logs for any issues

### Phase 3: Security Enforcement
1. Enable authentication requirement on API endpoints
2. Monitor for authentication failures
3. Provide support for any integration issues
4. Document the new authentication process

### Phase 4: Cleanup
1. Remove temporary unauthenticated access
2. Implement additional security hardening
3. Set up monitoring and alerting
4. Create operational runbooks

## Performance Considerations

### Token Validation Optimization
- Cache frequently used tokens in Redis
- Implement token validation middleware early in request lifecycle
- Use database indexes on token lookup fields

### Logging Efficiency
- Asynchronous logging using Laravel queues
- Log rotation and archival policies
- Efficient database queries for audit trails

### Rate Limiting Implementation
- Use Redis for distributed rate limiting
- Implement sliding window rate limiting
- Graceful degradation under high load

## Monitoring and Alerting

### Key Metrics
- Authentication success/failure rates
- Token usage patterns
- API response times with authentication
- Rate limiting trigger frequency

### Alerts
- High authentication failure rates
- Suspicious token usage patterns
- API performance degradation
- Rate limiting threshold breaches

### Dashboards
- Real-time authentication metrics
- Token usage analytics
- Security event timeline
- Performance impact analysis