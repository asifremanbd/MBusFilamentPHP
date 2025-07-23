<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardAccessLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'dashboard_type',
        'gateway_id',
        'widget_accessed',
        'access_granted',
        'ip_address',
        'user_agent',
        'accessed_at'
    ];

    protected $casts = [
        'access_granted' => 'boolean',
        'accessed_at' => 'datetime'
    ];

    public $timestamps = false;

    /**
     * Get the user that made the access attempt
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the gateway that was accessed (if applicable)
     */
    public function gateway(): BelongsTo
    {
        return $this->belongsTo(Gateway::class);
    }

    /**
     * Create a new access log entry
     */
    public static function logAccess(
        int $userId,
        string $dashboardType,
        bool $accessGranted,
        string $ipAddress,
        ?string $userAgent = null,
        ?int $gatewayId = null,
        ?string $widgetAccessed = null
    ): self {
        return self::create([
            'user_id' => $userId,
            'dashboard_type' => $dashboardType,
            'gateway_id' => $gatewayId,
            'widget_accessed' => $widgetAccessed,
            'access_granted' => $accessGranted,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'accessed_at' => now()
        ]);
    }

    /**
     * Log successful dashboard access
     */
    public static function logSuccess(
        int $userId,
        string $dashboardType,
        string $ipAddress,
        ?string $userAgent = null,
        ?int $gatewayId = null,
        ?string $widgetAccessed = null
    ): self {
        return self::logAccess(
            $userId,
            $dashboardType,
            true,
            $ipAddress,
            $userAgent,
            $gatewayId,
            $widgetAccessed
        );
    }

    /**
     * Log failed dashboard access
     */
    public static function logFailure(
        int $userId,
        string $dashboardType,
        string $ipAddress,
        ?string $userAgent = null,
        ?int $gatewayId = null,
        ?string $widgetAccessed = null
    ): self {
        return self::logAccess(
            $userId,
            $dashboardType,
            false,
            $ipAddress,
            $userAgent,
            $gatewayId,
            $widgetAccessed
        );
    }

    /**
     * Scope to get successful access logs
     */
    public function scopeSuccessful($query)
    {
        return $query->where('access_granted', true);
    }

    /**
     * Scope to get failed access logs
     */
    public function scopeFailed($query)
    {
        return $query->where('access_granted', false);
    }

    /**
     * Scope to get logs for specific user
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get logs for specific dashboard type
     */
    public function scopeForDashboardType($query, string $dashboardType)
    {
        return $query->where('dashboard_type', $dashboardType);
    }

    /**
     * Scope to get logs for specific gateway
     */
    public function scopeForGateway($query, int $gatewayId)
    {
        return $query->where('gateway_id', $gatewayId);
    }

    /**
     * Scope to get logs within date range
     */
    public function scopeWithinDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('accessed_at', [$startDate, $endDate]);
    }

    /**
     * Get access summary for a user
     */
    public static function getAccessSummary(int $userId, int $days = 30): array
    {
        $startDate = now()->subDays($days);
        
        $logs = self::forUser($userId)
            ->withinDateRange($startDate, now())
            ->get();

        return [
            'total_accesses' => $logs->count(),
            'successful_accesses' => $logs->where('access_granted', true)->count(),
            'failed_accesses' => $logs->where('access_granted', false)->count(),
            'dashboard_types' => $logs->groupBy('dashboard_type')->map->count(),
            'widgets_accessed' => $logs->whereNotNull('widget_accessed')
                ->groupBy('widget_accessed')->map->count(),
            'last_access' => $logs->max('accessed_at')
        ];
    }

    /**
     * Get security alerts for failed access attempts
     */
    public static function getSecurityAlerts(int $hours = 24): array
    {
        $startTime = now()->subHours($hours);
        
        $failedAttempts = self::failed()
            ->withinDateRange($startTime, now())
            ->get();

        $suspiciousIps = $failedAttempts
            ->groupBy('ip_address')
            ->filter(function ($attempts) {
                return $attempts->count() >= 5; // 5 or more failed attempts
            })
            ->map(function ($attempts, $ip) {
                return [
                    'ip_address' => $ip,
                    'failed_attempts' => $attempts->count(),
                    'users_targeted' => $attempts->pluck('user_id')->unique()->count(),
                    'last_attempt' => $attempts->max('accessed_at')
                ];
            });

        return [
            'total_failed_attempts' => $failedAttempts->count(),
            'suspicious_ips' => $suspiciousIps->values()->toArray(),
            'targeted_users' => $failedAttempts->groupBy('user_id')->map->count()
        ];
    }
}