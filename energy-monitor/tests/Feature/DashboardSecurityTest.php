<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Gateway;
use App\Models\Device;
use App\Models\DashboardAccessLog;
use App\Models\UserDeviceAssignment;
use App\Services\SecurityLogService;
use App\Services\SecurityMonitoringService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DashboardSecurityTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected SecurityMonitoringService $monitoringService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->monitoringService = app(SecurityMonitoringService::class);
        
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->operator = User::factory()->create(['role' => 'operator']);
        
        $this->gateway = Gateway::factory()->create();
        $this->device = Device::factory()->create(['gateway_id' => $this->gateway->id]);
    }

    public function test_dashboard_access_is_logged_successfully()
    {
        $this->actingAs($this->admin);
        
        $response = $this->get('/dashboard/global');
        
        $this->assertDatabaseHas('dashboard_access_logs', [
            'user_id' => $this->admin->id,
            'dashboard_type' => 'global',
            'access_granted' => true,
        ]);
    }

    public function test_failed_dashboard_access_is_logged()
    {
        $this->actingAs($this->operator);
        
        $response = $this->get('/dashboard/gateway/' . $this->gateway->id);
        
        $this->assertDatabaseHas('dashboard_access_logs', [
            'user_id' => $this->operator->id,
            'dashboard_type' => 'gateway',
            'gateway_id' => $this->gateway->id,
            'access_granted' => false,
        ]);
    }

    public function test_widget_access_is_logged()
    {
        $this->actingAs($this->admin);
        
        $response = $this->getJson('/api/dashboard/data', [
            'dashboard_type' => 'global',
            'widget_type' => 'system-overview',
        ]);
        
        $this->assertDatabaseHas('dashboard_access_logs', [
            'user_id' => $this->admin->id,
            'dashboard_type' => 'global',
            'widget_accessed' => 'system-overview',
        ]);
    }

    public function test_ip_address_and_user_agent_are_logged()
    {
        $this->actingAs($this->admin);
        
        $response = $this->withHeaders([
            'User-Agent' => 'Test Browser 1.0',
        ])->get('/dashboard/global');
        
        $log = DashboardAccessLog::where('user_id', $this->admin->id)->first();
        
        $this->assertNotNull($log->ip_address);
        $this->assertEquals('Test Browser 1.0', $log->user_agent);
    }

    public function test_access_log_provides_summary_statistics()
    {
        // Create test access logs
        DashboardAccessLog::create([
            'user_id' => $this->operator->id,
            'dashboard_type' => 'global',
            'access_granted' => true,
            'ip_address' => '192.168.1.1',
            'accessed_at' => now()->subHours(2),
        ]);

        DashboardAccessLog::create([
            'user_id' => $this->operator->id,
            'dashboard_type' => 'gateway',
            'gateway_id' => $this->gateway->id,
            'access_granted' => false,
            'ip_address' => '192.168.1.1',
            'accessed_at' => now()->subHour(),
        ]);

        $summary = DashboardAccessLog::getAccessSummary($this->operator->id);

        $this->assertEquals(2, $summary['total_accesses']);
        $this->assertEquals(1, $summary['successful_accesses']);
        $this->assertEquals(1, $summary['failed_accesses']);
        $this->assertArrayHasKey('dashboard_types', $summary);
    }

    public function test_security_alerts_detect_failed_attempts()
    {
        // Create multiple failed attempts
        for ($i = 0; $i < 6; $i++) {
            DashboardAccessLog::create([
                'user_id' => $this->operator->id,
                'dashboard_type' => 'gateway',
                'gateway_id' => $this->gateway->id,
                'access_granted' => false,
                'ip_address' => '192.168.1.100',
                'accessed_at' => now()->subMinutes($i * 2),
            ]);
        }

        $alerts = DashboardAccessLog::getSecurityAlerts();

        $this->assertGreaterThan(0, $alerts['total_failed_attempts']);
        $this->assertNotEmpty($alerts['suspicious_ips']);
    }

    public function test_security_monitoring_service_provides_dashboard()
    {
        // Create test data
        DashboardAccessLog::create([
            'user_id' => $this->admin->id,
            'dashboard_type' => 'global',
            'access_granted' => true,
            'ip_address' => '192.168.1.1',
            'accessed_at' => now()->subHour(),
        ]);

        DashboardAccessLog::create([
            'user_id' => $this->operator->id,
            'dashboard_type' => 'gateway',
            'access_granted' => false,
            'ip_address' => '192.168.1.2',
            'accessed_at' => now()->subMinutes(30),
        ]);

        $dashboard = $this->monitoringService->getSecurityDashboard();

        $this->assertArrayHasKey('overview', $dashboard);
        $this->assertArrayHasKey('threat_analysis', $dashboard);
        $this->assertArrayHasKey('access_patterns', $dashboard);
        $this->assertArrayHasKey('user_activity', $dashboard);
        $this->assertArrayHasKey('ip_analysis', $dashboard);
        $this->assertArrayHasKey('recommendations', $dashboard);
    }

    public function test_brute_force_detection_works()
    {
        // Create brute force attempt pattern
        for ($i = 0; $i < 8; $i++) {
            DashboardAccessLog::create([
                'user_id' => $this->operator->id,
                'dashboard_type' => 'global',
                'access_granted' => false,
                'ip_address' => '192.168.1.100',
                'accessed_at' => now()->subMinutes($i * 2),
            ]);
        }

        $dashboard = $this->monitoringService->getSecurityDashboard();
        $bruteForceAttempts = $dashboard['threat_analysis']['brute_force_attempts'];

        $this->assertNotEmpty($bruteForceAttempts);
        $this->assertEquals($this->operator->id, $bruteForceAttempts[0]['user_id']);
        $this->assertEquals('192.168.1.100', $bruteForceAttempts[0]['ip_address']);
        $this->assertEquals(8, $bruteForceAttempts[0]['attempt_count']);
    }

    public function test_suspicious_ip_detection_works()
    {
        // Create pattern of one IP targeting multiple users
        $users = [$this->admin, $this->operator];
        
        foreach ($users as $user) {
            for ($i = 0; $i < 3; $i++) {
                DashboardAccessLog::create([
                    'user_id' => $user->id,
                    'dashboard_type' => 'global',
                    'access_granted' => false,
                    'ip_address' => '192.168.1.200',
                    'accessed_at' => now()->subMinutes($i * 5),
                ]);
            }
        }

        $dashboard = $this->monitoringService->getSecurityDashboard();
        $suspiciousIps = $dashboard['threat_analysis']['suspicious_ips'];

        $this->assertNotEmpty($suspiciousIps);
        $this->assertEquals('192.168.1.200', $suspiciousIps[0]['ip_address']);
        $this->assertEquals(2, $suspiciousIps[0]['unique_users_targeted']);
    }

    public function test_off_hours_access_detection_works()
    {
        // Create off-hours access (weekend)
        $weekendTime = now()->next(Carbon::SATURDAY)->setHour(14);
        
        DashboardAccessLog::create([
            'user_id' => $this->operator->id,
            'dashboard_type' => 'global',
            'access_granted' => true,
            'ip_address' => '192.168.1.1',
            'accessed_at' => $weekendTime,
        ]);

        // Create late night access
        $lateNightTime = now()->setHour(23)->setMinute(30);
        
        DashboardAccessLog::create([
            'user_id' => $this->operator->id,
            'dashboard_type' => 'global',
            'access_granted' => true,
            'ip_address' => '192.168.1.1',
            'accessed_at' => $lateNightTime,
        ]);

        $dashboard = $this->monitoringService->getSecurityDashboard();
        $offHoursAccess = $dashboard['threat_analysis']['off_hours_access'];

        $this->assertCount(2, $offHoursAccess);
    }

    public function test_security_score_calculation_works()
    {
        // Create mostly successful accesses (should have high score)
        for ($i = 0; $i < 10; $i++) {
            DashboardAccessLog::create([
                'user_id' => $this->admin->id,
                'dashboard_type' => 'global',
                'access_granted' => true,
                'ip_address' => '192.168.1.1',
                'accessed_at' => now()->subMinutes($i * 5),
            ]);
        }

        $dashboard = $this->monitoringService->getSecurityDashboard();
        $securityScore = $dashboard['overview']['security_score'];

        $this->assertGreaterThan(80, $securityScore);

        // Add failed attempts (should lower score)
        for ($i = 0; $i < 5; $i++) {
            DashboardAccessLog::create([
                'user_id' => $this->operator->id,
                'dashboard_type' => 'global',
                'access_granted' => false,
                'ip_address' => '192.168.1.2',
                'accessed_at' => now()->subMinutes($i * 2),
            ]);
        }

        $dashboard2 = $this->monitoringService->getSecurityDashboard();
        $newSecurityScore = $dashboard2['overview']['security_score'];

        $this->assertLessThan($securityScore, $newSecurityScore);
    }

    public function test_security_recommendations_are_generated()
    {
        // Create conditions that should trigger recommendations
        
        // High failure rate
        for ($i = 0; $i < 5; $i++) {
            DashboardAccessLog::create([
                'user_id' => $this->operator->id,
                'dashboard_type' => 'global',
                'access_granted' => false,
                'ip_address' => '192.168.1.1',
                'accessed_at' => now()->subMinutes($i * 2),
            ]);
        }

        // Brute force attempts
        for ($i = 0; $i < 6; $i++) {
            DashboardAccessLog::create([
                'user_id' => $this->operator->id,
                'dashboard_type' => 'gateway',
                'access_granted' => false,
                'ip_address' => '192.168.1.100',
                'accessed_at' => now()->subMinutes($i * 3),
            ]);
        }

        $dashboard = $this->monitoringService->getSecurityDashboard();
        $recommendations = $dashboard['recommendations'];

        $this->assertNotEmpty($recommendations);
        
        $recommendationTypes = array_column($recommendations, 'category');
        $this->assertContains('brute_force', $recommendationTypes);
    }

    public function test_access_patterns_analysis_works()
    {
        // Create access patterns throughout the day
        $hours = [8, 10, 14, 16, 20];
        
        foreach ($hours as $hour) {
            DashboardAccessLog::create([
                'user_id' => $this->admin->id,
                'dashboard_type' => 'global',
                'access_granted' => true,
                'ip_address' => '192.168.1.1',
                'accessed_at' => now()->setHour($hour),
            ]);
        }

        $dashboard = $this->monitoringService->getSecurityDashboard();
        $accessPatterns = $dashboard['access_patterns'];

        $this->assertArrayHasKey('hourly_distribution', $accessPatterns);
        $this->assertArrayHasKey('dashboard_type_usage', $accessPatterns);
        
        $hourlyDistribution = $accessPatterns['hourly_distribution'];
        $this->assertCount(5, $hourlyDistribution);
    }

    public function test_user_activity_summary_works()
    {
        // Create activity for multiple users
        $users = [$this->admin, $this->operator];
        
        foreach ($users as $index => $user) {
            for ($i = 0; $i < ($index + 1) * 3; $i++) {
                DashboardAccessLog::create([
                    'user_id' => $user->id,
                    'dashboard_type' => 'global',
                    'access_granted' => $i % 2 === 0, // Mix of success/failure
                    'ip_address' => '192.168.1.' . ($index + 1),
                    'accessed_at' => now()->subMinutes($i * 10),
                ]);
            }
        }

        $dashboard = $this->monitoringService->getSecurityDashboard();
        $userActivity = $dashboard['user_activity'];

        $this->assertArrayHasKey('most_active_users', $userActivity);
        $this->assertArrayHasKey('users_with_failures', $userActivity);
        
        $mostActiveUsers = $userActivity['most_active_users'];
        $this->assertNotEmpty($mostActiveUsers);
    }

    public function test_ip_analysis_works()
    {
        // Create activity from multiple IPs
        $ips = ['192.168.1.1', '192.168.1.2', '10.0.0.1'];
        
        foreach ($ips as $index => $ip) {
            for ($i = 0; $i < ($index + 1) * 2; $i++) {
                DashboardAccessLog::create([
                    'user_id' => $this->admin->id,
                    'dashboard_type' => 'global',
                    'access_granted' => $i % 3 !== 0, // Mostly successful
                    'ip_address' => $ip,
                    'accessed_at' => now()->subMinutes($i * 5),
                ]);
            }
        }

        $dashboard = $this->monitoringService->getSecurityDashboard();
        $ipAnalysis = $dashboard['ip_analysis'];

        $this->assertArrayHasKey('most_active_ips', $ipAnalysis);
        $this->assertArrayHasKey('ips_with_failures', $ipAnalysis);
        $this->assertArrayHasKey('suspicious_ip_patterns', $ipAnalysis);
        
        $mostActiveIps = $ipAnalysis['most_active_ips'];
        $this->assertNotEmpty($mostActiveIps);
    }

    public function test_security_alerts_are_generated()
    {
        // Create conditions for security alerts
        
        // Brute force attempts
        for ($i = 0; $i < 6; $i++) {
            DashboardAccessLog::create([
                'user_id' => $this->operator->id,
                'dashboard_type' => 'global',
                'access_granted' => false,
                'ip_address' => '192.168.1.100',
                'accessed_at' => now()->subMinutes($i * 2),
            ]);
        }

        $alerts = $this->monitoringService->checkSecurityAlerts();

        $this->assertNotEmpty($alerts);
        $this->assertEquals('brute_force', $alerts[0]['type']);
        $this->assertEquals('high', $alerts[0]['severity']);
    }

    public function test_security_log_service_logs_events()
    {
        Log::shouldReceive('channel')
            ->with('security')
            ->andReturnSelf();
        
        Log::shouldReceive('warning')
            ->once()
            ->with('Dashboard access denied', \Mockery::type('array'));

        SecurityLogService::logFailedDashboardAccess(
            $this->operator,
            'gateway',
            $this->gateway->id,
            '192.168.1.1',
            'Test Browser',
            403
        );
    }

    public function test_dashboard_access_logger_middleware_adds_security_headers()
    {
        $this->actingAs($this->admin);
        
        $response = $this->get('/dashboard/global');
        
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('X-Access-Logged', 'true');
    }

    public function test_multiple_ip_usage_detection_works()
    {
        // User accessing from multiple IPs
        $ips = ['192.168.1.1', '192.168.1.2', '10.0.0.1'];
        
        foreach ($ips as $ip) {
            DashboardAccessLog::create([
                'user_id' => $this->operator->id,
                'dashboard_type' => 'global',
                'access_granted' => true,
                'ip_address' => $ip,
                'accessed_at' => now()->subMinutes(rand(1, 30)),
            ]);
        }

        $dashboard = $this->monitoringService->getSecurityDashboard();
        $userActivity = $dashboard['user_activity'];

        $this->assertNotEmpty($userActivity['users_with_multiple_ips']);
        $this->assertEquals($this->operator->id, $userActivity['users_with_multiple_ips'][0]['user_id']);
        $this->assertEquals(3, $userActivity['users_with_multiple_ips'][0]['ip_count']);
    }

    public function test_widget_access_frequency_tracking_works()
    {
        $widgets = ['system-overview', 'cross-gateway-alerts', 'system-overview'];
        
        foreach ($widgets as $widget) {
            DashboardAccessLog::create([
                'user_id' => $this->admin->id,
                'dashboard_type' => 'global',
                'widget_accessed' => $widget,
                'access_granted' => true,
                'ip_address' => '192.168.1.1',
                'accessed_at' => now()->subMinutes(rand(1, 60)),
            ]);
        }

        $dashboard = $this->monitoringService->getSecurityDashboard();
        $accessPatterns = $dashboard['access_patterns'];

        $this->assertArrayHasKey('widget_access_frequency', $accessPatterns);
        $widgetFrequency = $accessPatterns['widget_access_frequency'];
        
        $this->assertEquals(2, $widgetFrequency['system-overview']);
        $this->assertEquals(1, $widgetFrequency['cross-gateway-alerts']);
    }
}