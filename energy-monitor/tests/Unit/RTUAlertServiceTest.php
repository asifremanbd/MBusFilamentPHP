<?php

namespace Tests\Unit;

use App\Services\RTUAlertService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class RTUAlertServiceTest extends TestCase
{
    protected RTUAlertService $rtuAlertService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->rtuAlertService = new RTUAlertService();
    }

    public function test_normalize_alert_type_maps_parameters_correctly()
    {
        $testCases = [
            'router_uptime' => 'Router Uptime',
            'uptime' => 'Router Uptime',
            'system_uptime' => 'Router Uptime',
            'connection_state' => 'Connection State',
            'connection_status' => 'Connection State',
            'gsm_signal' => 'GSM Signal',
            'rssi' => 'GSM Signal',
            'rsrp' => 'GSM Signal',
            'cpu_load' => 'System Performance',
            'memory_usage' => 'System Performance',
            'di1' => 'I/O Status',
            'do2' => 'I/O Status',
            'analog_input' => 'I/O Status',
            'wan_ip' => 'Network Configuration',
            'sim_iccid' => 'SIM Card Status',
            'unknown_parameter' => 'Unknown Parameter'
        ];

        $reflection = new \ReflectionClass($this->rtuAlertService);
        $method = $reflection->getMethod('normalizeAlertType');
        $method->setAccessible(true);

        foreach ($testCases as $input => $expected) {
            $result = $method->invoke($this->rtuAlertService, $input);
            $this->assertEquals($expected, $result, "Failed for input: {$input}");
        }
    }

    public function test_get_highest_severity()
    {
        $alerts = collect([
            (object)['severity' => 'info'],
            (object)['severity' => 'critical'],
            (object)['severity' => 'warning']
        ]);

        $reflection = new \ReflectionClass($this->rtuAlertService);
        $method = $reflection->getMethod('getHighestSeverity');
        $method->setAccessible(true);

        $result = $method->invoke($this->rtuAlertService, $alerts);
        $this->assertEquals('critical', $result);

        // Test with only warnings and info
        $alerts = collect([
            (object)['severity' => 'info'],
            (object)['severity' => 'warning']
        ]);

        $result = $method->invoke($this->rtuAlertService, $alerts);
        $this->assertEquals('warning', $result);
    }

    public function test_get_alert_status_summary_with_different_scenarios()
    {
        // Test with critical alerts
        $alerts = collect([
            (object)['severity' => 'critical'],
            (object)['severity' => 'critical']
        ]);
        $result = $this->rtuAlertService->getAlertStatusSummary($alerts);
        $this->assertEquals('2 Critical Alerts', $result);

        // Test with single critical alert
        $alerts = collect([
            (object)['severity' => 'critical']
        ]);
        $result = $this->rtuAlertService->getAlertStatusSummary($alerts);
        $this->assertEquals('1 Critical Alert', $result);

        // Test with warnings only
        $alerts = collect([
            (object)['severity' => 'warning'],
            (object)['severity' => 'warning'],
            (object)['severity' => 'warning']
        ]);
        $result = $this->rtuAlertService->getAlertStatusSummary($alerts);
        $this->assertEquals('3 Warnings', $result);

        // Test with no alerts
        $alerts = collect([]);
        $result = $this->rtuAlertService->getAlertStatusSummary($alerts);
        $this->assertEquals('All Systems OK', $result);
    }

    public function test_group_similar_alerts_logic()
    {
        // Create mock alerts collection
        $alerts = collect([
            (object)[
                'parameter_name' => 'router_uptime',
                'message' => 'Router uptime alert 1',
                'severity' => 'warning',
                'created_at' => now()->subMinutes(10),
                'device_id' => 1,
                'value' => 100,
                'id' => 1
            ],
            (object)[
                'parameter_name' => 'uptime',
                'message' => 'Router uptime alert 2',
                'severity' => 'critical',
                'created_at' => now()->subMinutes(5),
                'device_id' => 1,
                'value' => 200,
                'id' => 2
            ],
            (object)[
                'parameter_name' => 'gsm_signal',
                'message' => 'GSM signal alert',
                'severity' => 'info',
                'created_at' => now()->subMinutes(3),
                'device_id' => 1,
                'value' => -80,
                'id' => 3
            ]
        ]);

        $reflection = new \ReflectionClass($this->rtuAlertService);
        $method = $reflection->getMethod('groupSimilarAlerts');
        $method->setAccessible(true);

        $result = $method->invoke($this->rtuAlertService, $alerts);

        // Should have 2 groups (Router Uptime and GSM Signal)
        $this->assertEquals(2, $result->count());

        // Check Router Uptime group (should consolidate router_uptime and uptime)
        $routerUptimeGroup = $result->firstWhere('type', 'Router Uptime');
        $this->assertNotNull($routerUptimeGroup);
        $this->assertEquals(2, $routerUptimeGroup->count);
        $this->assertTrue($routerUptimeGroup->is_grouped);
        $this->assertEquals('critical', $routerUptimeGroup->severity); // Highest severity

        // Check GSM Signal group
        $gsmSignalGroup = $result->firstWhere('type', 'GSM Signal');
        $this->assertNotNull($gsmSignalGroup);
        $this->assertEquals(1, $gsmSignalGroup->count);
        $this->assertFalse($gsmSignalGroup->is_grouped);
    }

    public function test_filter_off_hours_alerts_during_business_hours()
    {
        // Mock business hours (10 AM)
        Carbon::setTestNow(Carbon::create(2025, 1, 1, 10, 0, 0));

        $groupedAlerts = collect([
            (object)['severity' => 'critical', 'type' => 'Router Uptime'],
            (object)['severity' => 'warning', 'type' => 'GSM Signal'],
            (object)['severity' => 'info', 'type' => 'Network Status']
        ]);

        $reflection = new \ReflectionClass($this->rtuAlertService);
        $method = $reflection->getMethod('filterOffHoursAlerts');
        $method->setAccessible(true);

        $result = $method->invoke($this->rtuAlertService, $groupedAlerts);

        // During business hours, all alerts should be shown
        $this->assertEquals(3, $result->count());
    }

    public function test_filter_off_hours_alerts_during_off_hours()
    {
        // Mock off hours (11 PM)
        Carbon::setTestNow(Carbon::create(2025, 1, 1, 23, 0, 0));

        $groupedAlerts = collect([
            (object)['severity' => 'critical', 'type' => 'Router Uptime'],
            (object)['severity' => 'warning', 'type' => 'GSM Signal'],
            (object)['severity' => 'info', 'type' => 'Network Status']
        ]);

        $reflection = new \ReflectionClass($this->rtuAlertService);
        $method = $reflection->getMethod('filterOffHoursAlerts');
        $method->setAccessible(true);

        $result = $method->invoke($this->rtuAlertService, $groupedAlerts);

        // During off hours, only critical alerts should be shown
        $this->assertEquals(1, $result->count());
        $this->assertEquals('critical', $result->first()->severity);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(); // Reset Carbon mock
        parent::tearDown();
    }
}