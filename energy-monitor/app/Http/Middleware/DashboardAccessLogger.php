<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\DashboardAccessLog;
use App\Services\SecurityLogService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class DashboardAccessLogger
{
    protected SecurityLogService $securityService;

    public function __construct(SecurityLogService $securityService)
    {
        $this->securityService = $securityService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $user = $request->user();
        
        // Extract dashboard context
        $dashboardType = $this->extractDashboardType($request);
        $gatewayId = $this->extractGatewayId($request);
        $widgetAccessed = $this->extractWidgetAccessed($request);

        // Check for suspicious activity before processing
        if ($user && $this->detectSuspiciousActivity($request, $user)) {
            $this->logSuspiciousActivity($request, $user, $dashboardType, $gatewayId);
        }

        // Process the request
        $response = $next($request);
        
        // Log the access attempt
        $this->logDashboardAccess($request, $response, $user, $dashboardType, $gatewayId, $widgetAccessed);
        
        // Add security headers
        $this->addSecurityHeaders($response, $request);
        
        // Log performance metrics
        $this->logPerformanceMetrics($request, $startTime, $user);

        return $response;
    }

    /**
     * Log dashboard access attempt
     */
    protected function logDashboardAccess(
        Request $request,
        Response $response,
        $user,
        ?string $dashboardType,
        ?int $gatewayId,
        ?string $widgetAccessed
    ): void {
        try {
            if (!$user || !$dashboardType) {
                return;
            }

            $accessGranted = $response->getStatusCode() < 400;
            
            DashboardAccessLog::logAccess(
                $user->id,
                $dashboardType,
                $accessGranted,
                $request->ip(),
                $request->userAgent(),
                $gatewayId,
                $widgetAccessed
            );

            // Log additional security information for failed attempts
            if (!$accessGranted) {
                $this->logFailedAccess($request, $response, $user, $dashboardType, $gatewayId);
            }

        } catch (\Exception $e) {
            Log::error('Failed to log dashboard access', [
                'user_id' => $user?->id,
                'dashboard_type' => $dashboardType,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Log failed access attempt with additional security context
     */
    protected function logFailedAccess(
        Request $request,
        Response $response,
        $user,
        string $dashboardType,
        ?int $gatewayId
    ): void {
        $this->securityService->logFailedDashboardAccess(
            $user,
            $dashboardType,
            $gatewayId,
            $request->ip(),
            $request->userAgent(),
            $response->getStatusCode(),
            [
                'referer' => $request->header('referer'),
                'request_method' => $request->method(),
                'request_uri' => $request->getRequestUri(),
                'session_id' => $request->session()->getId(),
                'csrf_token' => $request->session()->token(),
            ]
        );

        // Check for brute force attempts
        $this->checkBruteForceAttempts($request, $user);
    }

    /**
     * Detect suspicious activity patterns
     */
    protected function detectSuspiciousActivity(Request $request, $user): bool
    {
        $suspiciousIndicators = [];

        // Check for rapid successive requests
        $rateLimitKey = 'dashboard_access:' . $user->id . ':' . $request->ip();
        if (RateLimiter::tooManyAttempts($rateLimitKey, 10)) { // 10 requests per minute
            $suspiciousIndicators[] = 'rapid_requests';
        }
        RateLimiter::hit($rateLimitKey, 60); // 1 minute window

        // Check for unusual user agent patterns
        $userAgent = $request->userAgent();
        if ($this->isUnusualUserAgent($userAgent)) {
            $suspiciousIndicators[] = 'unusual_user_agent';
        }

        // Check for IP address changes within short time
        if ($this->hasRecentIpChange($user, $request->ip())) {
            $suspiciousIndicators[] = 'ip_change';
        }

        // Check for access outside normal hours
        if ($this->isOutsideNormalHours()) {
            $suspiciousIndicators[] = 'off_hours_access';
        }

        // Check for geographic anomalies (if GeoIP is available)
        if ($this->isGeographicAnomaly($request->ip(), $user)) {
            $suspiciousIndicators[] = 'geographic_anomaly';
        }

        return !empty($suspiciousIndicators);
    }

    /**
     * Log suspicious activity
     */
    protected function logSuspiciousActivity(Request $request, $user, ?string $dashboardType, ?int $gatewayId): void
    {
        $this->securityService->logSuspiciousActivity(
            $user,
            'dashboard_access',
            [
                'dashboard_type' => $dashboardType,
                'gateway_id' => $gatewayId,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'referer' => $request->header('referer'),
                'request_uri' => $request->getRequestUri(),
                'session_id' => $request->session()->getId(),
                'timestamp' => now()->toISOString(),
            ]
        );
    }

    /**
     * Check for brute force attempts
     */
    protected function checkBruteForceAttempts(Request $request, $user): void
    {
        $recentFailures = DashboardAccessLog::failed()
            ->forUser($user->id)
            ->where('ip_address', $request->ip())
            ->where('accessed_at', '>=', now()->subMinutes(15))
            ->count();

        if ($recentFailures >= 5) {
            $this->securityService->logSecurityIncident(
                $user,
                'brute_force_attempt',
                [
                    'failed_attempts' => $recentFailures,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'time_window' => '15 minutes',
                ]
            );

            // Optionally trigger additional security measures
            $this->triggerSecurityMeasures($request, $user, 'brute_force');
        }
    }

    /**
     * Add security headers to response
     */
    protected function addSecurityHeaders(Response $response, Request $request): void
    {
        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'X-Request-ID' => $request->header('X-Request-ID', uniqid()),
            'X-Access-Logged' => 'true',
        ];

        foreach ($headers as $name => $value) {
            $response->headers->set($name, $value);
        }

        // Add CSP header for dashboard pages
        if ($this->isDashboardRequest($request)) {
            $csp = "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' https:; connect-src 'self' wss: ws:;";
            $response->headers->set('Content-Security-Policy', $csp);
        }
    }

    /**
     * Log performance metrics
     */
    protected function logPerformanceMetrics(Request $request, float $startTime, $user): void
    {
        $executionTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
        $memoryUsage = memory_get_peak_usage(true);

        if ($executionTime > 5000 || $memoryUsage > 50 * 1024 * 1024) { // 5 seconds or 50MB
            Log::warning('Slow dashboard request detected', [
                'user_id' => $user?->id,
                'url' => $request->fullUrl(),
                'execution_time_ms' => round($executionTime, 2),
                'memory_usage_mb' => round($memoryUsage / 1024 / 1024, 2),
                'ip_address' => $request->ip(),
            ]);
        }
    }

    /**
     * Extract dashboard type from request
     */
    protected function extractDashboardType(Request $request): ?string
    {
        $path = $request->path();
        
        if (str_contains($path, 'dashboard/global')) {
            return 'global';
        }
        
        if (str_contains($path, 'dashboard/gateway')) {
            return 'gateway';
        }
        
        if (str_contains($path, 'api/dashboard')) {
            return $request->input('dashboard_type', 'api');
        }

        return null;
    }

    /**
     * Extract gateway ID from request
     */
    protected function extractGatewayId(Request $request): ?int
    {
        // From route parameter
        $gatewayId = $request->route('gateway') ?? $request->route('gatewayId');
        if ($gatewayId) {
            return (int) $gatewayId;
        }

        // From query parameter
        $gatewayId = $request->input('gateway_id');
        if ($gatewayId) {
            return (int) $gatewayId;
        }

        return null;
    }

    /**
     * Extract widget accessed from request
     */
    protected function extractWidgetAccessed(Request $request): ?string
    {
        // From API endpoints
        if (str_contains($request->path(), 'api/dashboard/widget')) {
            return $request->input('widget_type') ?? $request->route('widget_type');
        }

        // From widget-specific routes
        if (preg_match('/widget\/([^\/]+)/', $request->path(), $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Check if user agent is unusual
     */
    protected function isUnusualUserAgent(?string $userAgent): bool
    {
        if (!$userAgent) {
            return true;
        }

        $suspiciousPatterns = [
            '/bot/i',
            '/crawler/i',
            '/spider/i',
            '/scraper/i',
            '/curl/i',
            '/wget/i',
            '/python/i',
            '/java/i',
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has recent IP address change
     */
    protected function hasRecentIpChange($user, string $currentIp): bool
    {
        $recentAccess = DashboardAccessLog::forUser($user->id)
            ->successful()
            ->where('accessed_at', '>=', now()->subMinutes(30))
            ->where('ip_address', '!=', $currentIp)
            ->exists();

        return $recentAccess;
    }

    /**
     * Check if access is outside normal business hours
     */
    protected function isOutsideNormalHours(): bool
    {
        $hour = now()->hour;
        $dayOfWeek = now()->dayOfWeek;

        // Weekend access
        if ($dayOfWeek === 0 || $dayOfWeek === 6) {
            return true;
        }

        // Outside 6 AM - 10 PM
        if ($hour < 6 || $hour > 22) {
            return true;
        }

        return false;
    }

    /**
     * Check for geographic anomalies
     */
    protected function isGeographicAnomaly(string $ip, $user): bool
    {
        // This would require a GeoIP service
        // For now, return false as placeholder
        return false;
    }

    /**
     * Trigger additional security measures
     */
    protected function triggerSecurityMeasures(Request $request, $user, string $threatType): void
    {
        switch ($threatType) {
            case 'brute_force':
                // Could implement account lockout, IP blocking, etc.
                Log::alert('Brute force attempt detected', [
                    'user_id' => $user->id,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
                break;
                
            case 'suspicious_activity':
                // Could implement additional monitoring, notifications, etc.
                Log::warning('Suspicious activity detected', [
                    'user_id' => $user->id,
                    'ip_address' => $request->ip(),
                ]);
                break;
        }
    }

    /**
     * Check if request is for dashboard
     */
    protected function isDashboardRequest(Request $request): bool
    {
        return str_contains($request->path(), 'dashboard') || 
               str_contains($request->path(), 'api/dashboard');
    }
}