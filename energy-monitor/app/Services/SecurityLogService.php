<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Models\User;

class SecurityLogService
{
    /**
     * Log unauthorized access attempt
     */
    public static function logUnauthorizedAccess(Request $request, string $resource, string $action): void
    {
        $user = $request->user();
        
        Log::channel('security')->warning('Unauthorized access attempt', [
            'user_id' => $user?->id,
            'user_email' => $user?->email,
            'user_role' => $user?->role,
            'resource' => $resource,
            'action' => $action,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
    
    /**
     * Log user role change
     */
    public static function logRoleChange(User $user, string $oldRole, string $newRole, ?User $changedBy = null): void
    {
        Log::channel('security')->info('User role changed', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'old_role' => $oldRole,
            'new_role' => $newRole,
            'changed_by' => $changedBy?->id,
            'changed_by_email' => $changedBy?->email,
        ]);
    }
    
    /**
     * Log notification preference change
     */
    public static function logNotificationPreferenceChange(User $user, array $oldPreferences, array $newPreferences, ?User $changedBy = null): void
    {
        Log::channel('security')->info('Notification preferences changed', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'old_preferences' => $oldPreferences,
            'new_preferences' => $newPreferences,
            'changed_by' => $changedBy?->id,
            'changed_by_email' => $changedBy?->email,
        ]);
    }
    
    /**
     * Log successful authentication
     */
    public static function logSuccessfulAuth(User $user, Request $request): void
    {
        Log::channel('security')->info('User authenticated successfully', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'user_role' => $user->role,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
    
    /**
     * Log failed authentication
     */
    public static function logFailedAuth(string $email, Request $request): void
    {
        Log::channel('security')->warning('Authentication failed', [
            'email' => $email,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    /**
     * Log failed dashboard access
     */
    public static function logFailedDashboardAccess(
        User $user,
        string $dashboardType,
        ?int $gatewayId,
        string $ipAddress,
        ?string $userAgent,
        int $statusCode,
        array $additionalContext = []
    ): void {
        Log::channel('security')->warning('Dashboard access denied', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'user_role' => $user->role,
            'dashboard_type' => $dashboardType,
            'gateway_id' => $gatewayId,
            'status_code' => $statusCode,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'additional_context' => $additionalContext,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Log suspicious activity
     */
    public static function logSuspiciousActivity(User $user, string $activityType, array $context = []): void
    {
        Log::channel('security')->alert('Suspicious activity detected', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'user_role' => $user->role,
            'activity_type' => $activityType,
            'context' => $context,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Log security incident
     */
    public static function logSecurityIncident(User $user, string $incidentType, array $details = []): void
    {
        Log::channel('security')->critical('Security incident detected', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'user_role' => $user->role,
            'incident_type' => $incidentType,
            'details' => $details,
            'timestamp' => now()->toISOString(),
        ]);

        // Could trigger additional actions like notifications, alerts, etc.
        static::triggerSecurityAlert($user, $incidentType, $details);
    }

    /**
     * Log permission changes
     */
    public static function logPermissionChange(
        User $targetUser,
        User $changedBy,
        string $changeType,
        array $changes
    ): void {
        Log::channel('security')->info('User permissions changed', [
            'target_user_id' => $targetUser->id,
            'target_user_email' => $targetUser->email,
            'changed_by_id' => $changedBy->id,
            'changed_by_email' => $changedBy->email,
            'change_type' => $changeType,
            'changes' => $changes,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Log data export/download
     */
    public static function logDataExport(
        User $user,
        string $dataType,
        array $filters = [],
        string $format = 'unknown'
    ): void {
        Log::channel('security')->info('Data export performed', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'user_role' => $user->role,
            'data_type' => $dataType,
            'filters' => $filters,
            'format' => $format,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Log configuration changes
     */
    public static function logConfigurationChange(
        User $user,
        string $configType,
        array $oldConfig,
        array $newConfig
    ): void {
        Log::channel('security')->info('Configuration changed', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'user_role' => $user->role,
            'config_type' => $configType,
            'old_config' => $oldConfig,
            'new_config' => $newConfig,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Log session events
     */
    public static function logSessionEvent(
        User $user,
        string $eventType,
        string $sessionId,
        array $context = []
    ): void {
        Log::channel('security')->info('Session event', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'event_type' => $eventType,
            'session_id' => $sessionId,
            'context' => $context,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Log API access
     */
    public static function logApiAccess(
        User $user,
        string $endpoint,
        string $method,
        int $statusCode,
        string $ipAddress,
        ?string $userAgent = null,
        array $requestData = []
    ): void {
        $logLevel = $statusCode >= 400 ? 'warning' : 'info';
        
        Log::channel('security')->{$logLevel}('API access', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'user_role' => $user->role,
            'endpoint' => $endpoint,
            'method' => $method,
            'status_code' => $statusCode,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'request_data' => static::sanitizeRequestData($requestData),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Get security log summary
     */
    public static function getSecurityLogSummary(int $hours = 24): array
    {
        // This would typically query log files or a log aggregation service
        // For now, return a placeholder structure
        return [
            'time_period' => "{$hours} hours",
            'total_events' => 0,
            'failed_authentications' => 0,
            'unauthorized_access_attempts' => 0,
            'suspicious_activities' => 0,
            'security_incidents' => 0,
            'top_source_ips' => [],
            'most_targeted_users' => [],
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * Check for security threats
     */
    public static function checkSecurityThreats(User $user, string $ipAddress): array
    {
        $threats = [];

        // Check for recent failed attempts
        $recentFailures = \App\Models\DashboardAccessLog::failed()
            ->forUser($user->id)
            ->where('ip_address', $ipAddress)
            ->where('accessed_at', '>=', now()->subHour())
            ->count();

        if ($recentFailures >= 3) {
            $threats[] = [
                'type' => 'repeated_failures',
                'severity' => 'medium',
                'count' => $recentFailures,
                'description' => 'Multiple failed access attempts in the last hour',
            ];
        }

        // Check for IP address changes
        $recentIpChange = \App\Models\DashboardAccessLog::forUser($user->id)
            ->successful()
            ->where('accessed_at', '>=', now()->subMinutes(30))
            ->where('ip_address', '!=', $ipAddress)
            ->exists();

        if ($recentIpChange) {
            $threats[] = [
                'type' => 'ip_change',
                'severity' => 'low',
                'description' => 'IP address changed within the last 30 minutes',
            ];
        }

        return $threats;
    }

    /**
     * Trigger security alert
     */
    protected static function triggerSecurityAlert(User $user, string $incidentType, array $details): void
    {
        // This could send notifications, trigger webhooks, etc.
        // For now, just log that an alert should be triggered
        Log::channel('security')->emergency('Security alert triggered', [
            'user_id' => $user->id,
            'incident_type' => $incidentType,
            'details' => $details,
            'alert_triggered_at' => now()->toISOString(),
        ]);
    }

    /**
     * Sanitize request data for logging
     */
    protected static function sanitizeRequestData(array $data): array
    {
        $sensitiveFields = ['password', 'token', 'secret', 'key', 'csrf_token'];
        
        foreach ($sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '[REDACTED]';
            }
        }

        return $data;
    }
}