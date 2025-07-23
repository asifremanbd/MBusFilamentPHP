<?php

namespace App\Services;

use App\Models\User;
use App\Models\DashboardAccessLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class SecurityMonitoringService
{
    protected int $monitoringWindow = 24; // hours
    protected array $threatThresholds = [
        'failed_attempts' => 5,
        'rapid_requests' => 20,
        'suspicious_ips' => 3,
        'off_hours_access' => 10,
    ];

    /**
     * Get comprehensive security dashboard
     */
    public function getSecurityDashboard(int $hours = 24): array
    {
        $startTime = now()->subHours($hours);
        
        return [
            'overview' => $this->getSecurityOverview($startTime),
            'threat_analysis' => $this->getThreatAnalysis($startTime),
            'access_patterns' => $this->getAccessPatterns($startTime),
            'user_activity' => $this->getUserActivitySummary($startTime),
            'ip_analysis' => $this->getIpAnalysis($startTime),
            'recommendations' => $this->getSecurityRecommendations($startTime),
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * Get security overview metrics
     */
    protected function getSecurityOverview(Carbon $startTime): array
    {
        $totalAccess = DashboardAccessLog::where('accessed_at', '>=', $startTime)->count();
        $failedAccess = DashboardAccessLog::failed()->where('accessed_at', '>=', $startTime)->count();
        $uniqueUsers = DashboardAccessLog::where('accessed_at', '>=', $startTime)->distinct('user_id')->count();
        $uniqueIps = DashboardAccessLog::where('accessed_at', '>=', $startTime)->distinct('ip_address')->count();

        return [
            'total_access_attempts' => $totalAccess,
            'failed_access_attempts' => $failedAccess,
            'success_rate' => $totalAccess > 0 ? round((($totalAccess - $failedAccess) / $totalAccess) * 100, 2) : 0,
            'unique_users' => $uniqueUsers,
            'unique_ip_addresses' => $uniqueIps,
            'security_score' => $this->calculateSecurityScore($startTime),
        ];
    }

    /**
     * Analyze security threats
     */
    protected function getThreatAnalysis(Carbon $startTime): array
    {
        return [
            'brute_force_attempts' => $this->detectBruteForceAttempts($startTime),
            'suspicious_ips' => $this->detectSuspiciousIps($startTime),
            'unusual_access_patterns' => $this->detectUnusualAccessPatterns($startTime),
            'off_hours_access' => $this->detectOffHoursAccess($startTime),
            'geographic_anomalies' => $this->detectGeographicAnomalies($startTime),
        ];
    }

    /**
     * Analyze access patterns
     */
    protected function getAccessPatterns(Carbon $startTime): array
    {
        $accessLogs = DashboardAccessLog::where('accessed_at', '>=', $startTime)->get();

        return [
            'hourly_distribution' => $this->getHourlyDistribution($accessLogs),
            'dashboard_type_usage' => $this->getDashboardTypeUsage($accessLogs),
            'widget_access_frequency' => $this->getWidgetAccessFrequency($accessLogs),
            'gateway_access_patterns' => $this->getGatewayAccessPatterns($accessLogs),
        ];
    }

    /**
     * Get user activity summary
     */
    protected function getUserActivitySummary(Carbon $startTime): array
    {
        $userStats = DashboardAccessLog::where('accessed_at', '>=', $startTime)
            ->selectRaw('
                user_id,
                COUNT(*) as total_accesses,
                SUM(CASE WHEN access_granted = 1 THEN 1 ELSE 0 END) as successful_accesses,
                SUM(CASE WHEN access_granted = 0 THEN 1 ELSE 0 END) as failed_accesses,
                COUNT(DISTINCT ip_address) as unique_ips,
                MIN(accessed_at) as first_access,
                MAX(accessed_at) as last_access
            ')
            ->groupBy('user_id')
            ->with('user:id,name,email,role')
            ->get();

        return [
            'most_active_users' => $userStats->sortByDesc('total_accesses')->take(10)->values(),
            'users_with_failures' => $userStats->where('failed_accesses', '>', 0)->sortByDesc('failed_accesses')->take(10)->values(),
            'users_with_multiple_ips' => $userStats->where('unique_ips', '>', 1)->sortByDesc('unique_ips')->take(10)->values(),
        ];
    }

    /**
     * Analyze IP addresses
     */
    protected function getIpAnalysis(Carbon $startTime): array
    {
        $ipStats = DashboardAccessLog::where('accessed_at', '>=', $startTime)
            ->selectRaw('
                ip_address,
                COUNT(*) as total_accesses,
                SUM(CASE WHEN access_granted = 1 THEN 1 ELSE 0 END) as successful_accesses,
                SUM(CASE WHEN access_granted = 0 THEN 1 ELSE 0 END) as failed_accesses,
                COUNT(DISTINCT user_id) as unique_users,
                MIN(accessed_at) as first_access,
                MAX(accessed_at) as last_access
            ')
            ->groupBy('ip_address')
            ->get();

        return [
            'most_active_ips' => $ipStats->sortByDesc('total_accesses')->take(10)->values(),
            'ips_with_failures' => $ipStats->where('failed_accesses', '>', 0)->sortByDesc('failed_accesses')->take(10)->values(),
            'ips_targeting_multiple_users' => $ipStats->where('unique_users', '>', 1)->sortByDesc('unique_users')->take(10)->values(),
            'suspicious_ip_patterns' => $this->identifySuspiciousIpPatterns($ipStats),
        ];
    }

    /**
     * Get security recommendations
     */
    protected function getSecurityRecommendations(Carbon $startTime): array
    {
        $recommendations = [];

        // Check for high failure rate
        $failureRate = $this->getFailureRate($startTime);
        if ($failureRate > 10) {
            $recommendations[] = [
                'priority' => 'high',
                'category' => 'authentication',
                'title' => 'High Authentication Failure Rate',
                'description' => "Current failure rate is {$failureRate}%, which is above the 10% threshold.",
                'action' => 'Review authentication logs and consider implementing additional security measures.',
            ];
        }

        // Check for brute force attempts
        $bruteForceAttempts = $this->detectBruteForceAttempts($startTime);
        if (count($bruteForceAttempts) > 0) {
            $recommendations[] = [
                'priority' => 'critical',
                'category' => 'brute_force',
                'title' => 'Brute Force Attempts Detected',
                'description' => count($bruteForceAttempts) . ' potential brute force attempts detected.',
                'action' => 'Consider implementing account lockout policies and IP blocking.',
            ];
        }

        // Check for off-hours access
        $offHoursAccess = $this->detectOffHoursAccess($startTime);
        if (count($offHoursAccess) > $this->threatThresholds['off_hours_access']) {
            $recommendations[] = [
                'priority' => 'medium',
                'category' => 'access_patterns',
                'title' => 'Unusual Off-Hours Access',
                'description' => count($offHoursAccess) . ' off-hours access attempts detected.',
                'action' => 'Review off-hours access patterns and consider implementing time-based restrictions.',
            ];
        }

        // Check for multiple IP usage
        $multipleIpUsers = $this->detectMultipleIpUsers($startTime);
        if (count($multipleIpUsers) > 0) {
            $recommendations[] = [
                'priority' => 'medium',
                'category' => 'ip_security',
                'title' => 'Users Accessing from Multiple IPs',
                'description' => count($multipleIpUsers) . ' users accessed from multiple IP addresses.',
                'action' => 'Review user access patterns and consider implementing IP whitelisting.',
            ];
        }

        return $recommendations;
    }

    /**
     * Calculate overall security score
     */
    protected function calculateSecurityScore(Carbon $startTime): float
    {
        $score = 100;

        // Deduct points for failed attempts
        $failureRate = $this->getFailureRate($startTime);
        $score -= min(30, $failureRate * 2);

        // Deduct points for brute force attempts
        $bruteForceAttempts = count($this->detectBruteForceAttempts($startTime));
        $score -= min(25, $bruteForceAttempts * 5);

        // Deduct points for suspicious IPs
        $suspiciousIps = count($this->detectSuspiciousIps($startTime));
        $score -= min(20, $suspiciousIps * 3);

        // Deduct points for off-hours access
        $offHoursAccess = count($this->detectOffHoursAccess($startTime));
        $score -= min(15, $offHoursAccess * 0.5);

        return max(0, round($score, 1));
    }

    /**
     * Detect brute force attempts
     */
    protected function detectBruteForceAttempts(Carbon $startTime): array
    {
        return DashboardAccessLog::failed()
            ->where('accessed_at', '>=', $startTime)
            ->selectRaw('user_id, ip_address, COUNT(*) as attempt_count')
            ->groupBy('user_id', 'ip_address')
            ->having('attempt_count', '>=', $this->threatThresholds['failed_attempts'])
            ->with('user:id,name,email')
            ->get()
            ->map(function ($attempt) {
                return [
                    'user_id' => $attempt->user_id,
                    'user_name' => $attempt->user->name ?? 'Unknown',
                    'user_email' => $attempt->user->email ?? 'Unknown',
                    'ip_address' => $attempt->ip_address,
                    'attempt_count' => $attempt->attempt_count,
                    'threat_level' => $attempt->attempt_count >= 10 ? 'high' : 'medium',
                ];
            })
            ->toArray();
    }

    /**
     * Detect suspicious IP addresses
     */
    protected function detectSuspiciousIps(Carbon $startTime): array
    {
        return DashboardAccessLog::where('accessed_at', '>=', $startTime)
            ->selectRaw('
                ip_address,
                COUNT(*) as total_requests,
                COUNT(DISTINCT user_id) as unique_users,
                SUM(CASE WHEN access_granted = 0 THEN 1 ELSE 0 END) as failed_attempts
            ')
            ->groupBy('ip_address')
            ->havingRaw('failed_attempts >= ? OR unique_users >= ?', [
                $this->threatThresholds['failed_attempts'],
                $this->threatThresholds['suspicious_ips']
            ])
            ->get()
            ->map(function ($ip) {
                return [
                    'ip_address' => $ip->ip_address,
                    'total_requests' => $ip->total_requests,
                    'unique_users_targeted' => $ip->unique_users,
                    'failed_attempts' => $ip->failed_attempts,
                    'threat_level' => $this->calculateIpThreatLevel($ip),
                ];
            })
            ->toArray();
    }

    /**
     * Detect unusual access patterns
     */
    protected function detectUnusualAccessPatterns(Carbon $startTime): array
    {
        $patterns = [];

        // Rapid successive requests
        $rapidRequests = DashboardAccessLog::where('accessed_at', '>=', $startTime)
            ->selectRaw('user_id, ip_address, COUNT(*) as request_count')
            ->groupBy('user_id', 'ip_address')
            ->having('request_count', '>=', $this->threatThresholds['rapid_requests'])
            ->with('user:id,name,email')
            ->get();

        foreach ($rapidRequests as $request) {
            $patterns[] = [
                'type' => 'rapid_requests',
                'user_id' => $request->user_id,
                'user_name' => $request->user->name ?? 'Unknown',
                'ip_address' => $request->ip_address,
                'request_count' => $request->request_count,
                'description' => 'Unusually high number of requests in short time period',
            ];
        }

        return $patterns;
    }

    /**
     * Detect off-hours access
     */
    protected function detectOffHoursAccess(Carbon $startTime): array
    {
        return DashboardAccessLog::where('accessed_at', '>=', $startTime)
            ->whereRaw('(HOUR(accessed_at) < 6 OR HOUR(accessed_at) > 22 OR DAYOFWEEK(accessed_at) IN (1, 7))')
            ->with('user:id,name,email')
            ->get()
            ->map(function ($access) {
                return [
                    'user_id' => $access->user_id,
                    'user_name' => $access->user->name ?? 'Unknown',
                    'user_email' => $access->user->email ?? 'Unknown',
                    'ip_address' => $access->ip_address,
                    'accessed_at' => $access->accessed_at->toISOString(),
                    'access_type' => $this->categorizeOffHoursAccess($access->accessed_at),
                ];
            })
            ->toArray();
    }

    /**
     * Detect geographic anomalies (placeholder)
     */
    protected function detectGeographicAnomalies(Carbon $startTime): array
    {
        // This would require GeoIP integration
        return [];
    }

    /**
     * Get hourly access distribution
     */
    protected function getHourlyDistribution(Collection $accessLogs): array
    {
        return $accessLogs->groupBy(function ($log) {
            return $log->accessed_at->format('H');
        })->map->count()->toArray();
    }

    /**
     * Get dashboard type usage statistics
     */
    protected function getDashboardTypeUsage(Collection $accessLogs): array
    {
        return $accessLogs->groupBy('dashboard_type')->map->count()->toArray();
    }

    /**
     * Get widget access frequency
     */
    protected function getWidgetAccessFrequency(Collection $accessLogs): array
    {
        return $accessLogs->whereNotNull('widget_accessed')
            ->groupBy('widget_accessed')
            ->map->count()
            ->sortDesc()
            ->toArray();
    }

    /**
     * Get gateway access patterns
     */
    protected function getGatewayAccessPatterns(Collection $accessLogs): array
    {
        return $accessLogs->whereNotNull('gateway_id')
            ->groupBy('gateway_id')
            ->map(function ($logs, $gatewayId) {
                return [
                    'gateway_id' => $gatewayId,
                    'access_count' => $logs->count(),
                    'unique_users' => $logs->pluck('user_id')->unique()->count(),
                    'success_rate' => $logs->where('access_granted', true)->count() / $logs->count() * 100,
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Get failure rate percentage
     */
    protected function getFailureRate(Carbon $startTime): float
    {
        $total = DashboardAccessLog::where('accessed_at', '>=', $startTime)->count();
        $failed = DashboardAccessLog::failed()->where('accessed_at', '>=', $startTime)->count();

        return $total > 0 ? round(($failed / $total) * 100, 2) : 0;
    }

    /**
     * Detect users accessing from multiple IPs
     */
    protected function detectMultipleIpUsers(Carbon $startTime): array
    {
        return DashboardAccessLog::where('accessed_at', '>=', $startTime)
            ->selectRaw('user_id, COUNT(DISTINCT ip_address) as ip_count')
            ->groupBy('user_id')
            ->having('ip_count', '>', 1)
            ->with('user:id,name,email')
            ->get()
            ->map(function ($user) {
                return [
                    'user_id' => $user->user_id,
                    'user_name' => $user->user->name ?? 'Unknown',
                    'user_email' => $user->user->email ?? 'Unknown',
                    'ip_count' => $user->ip_count,
                ];
            })
            ->toArray();
    }

    /**
     * Calculate IP threat level
     */
    protected function calculateIpThreatLevel($ipData): string
    {
        if ($ipData->failed_attempts >= 10 || $ipData->unique_users >= 5) {
            return 'high';
        } elseif ($ipData->failed_attempts >= 5 || $ipData->unique_users >= 3) {
            return 'medium';
        }
        return 'low';
    }

    /**
     * Categorize off-hours access
     */
    protected function categorizeOffHoursAccess(Carbon $accessTime): string
    {
        if ($accessTime->isWeekend()) {
            return 'weekend';
        } elseif ($accessTime->hour < 6) {
            return 'early_morning';
        } elseif ($accessTime->hour > 22) {
            return 'late_night';
        }
        return 'off_hours';
    }

    /**
     * Identify suspicious IP patterns
     */
    protected function identifySuspiciousIpPatterns(Collection $ipStats): array
    {
        $suspicious = [];

        foreach ($ipStats as $ip) {
            $suspiciousFactors = [];

            if ($ip->failed_accesses > $ip->successful_accesses) {
                $suspiciousFactors[] = 'more_failures_than_successes';
            }

            if ($ip->unique_users >= 3) {
                $suspiciousFactors[] = 'targeting_multiple_users';
            }

            if ($ip->total_accesses >= 50) {
                $suspiciousFactors[] = 'high_volume_requests';
            }

            if (!empty($suspiciousFactors)) {
                $suspicious[] = [
                    'ip_address' => $ip->ip_address,
                    'suspicious_factors' => $suspiciousFactors,
                    'risk_score' => count($suspiciousFactors) * 10,
                ];
            }
        }

        return $suspicious;
    }

    /**
     * Generate security alert if thresholds are exceeded
     */
    public function checkSecurityAlerts(): array
    {
        $alerts = [];
        $startTime = now()->subHours($this->monitoringWindow);

        // Check for brute force attempts
        $bruteForceAttempts = $this->detectBruteForceAttempts($startTime);
        if (count($bruteForceAttempts) > 0) {
            $alerts[] = [
                'type' => 'brute_force',
                'severity' => 'high',
                'count' => count($bruteForceAttempts),
                'message' => 'Brute force attempts detected',
                'details' => $bruteForceAttempts,
            ];
        }

        // Check for suspicious IPs
        $suspiciousIps = $this->detectSuspiciousIps($startTime);
        if (count($suspiciousIps) > 0) {
            $alerts[] = [
                'type' => 'suspicious_ip',
                'severity' => 'medium',
                'count' => count($suspiciousIps),
                'message' => 'Suspicious IP addresses detected',
                'details' => $suspiciousIps,
            ];
        }

        return $alerts;
    }
}