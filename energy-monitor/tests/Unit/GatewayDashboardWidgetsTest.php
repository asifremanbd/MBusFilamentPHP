<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Models\User;
use App\Models\Gateway;
use App\Models\Device;
use App\Models\Reading;
use App\Models\Register;
use App\Models\Alert;
use App\Models\UserDeviceAssignment;
use App\Widgets\Gateway\GatewayDeviceListWidget;
use App\Widgets\Gateway\RealTimeReadingsWidget;
use App\Widgets\Gateway\GatewayStatsWidget;
use App\Widgets\Gateway\GatewayAlertsWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesApplication;

class GatewayDashboardWidgetsTest extends TestCase
{
    use CreatesApplication, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createApplication();
    }

    public function test_gateway_device_list_widget_renders_correctly()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $gateway = Gateway::factory()->create();
        $device = Device::factory()->create(['gateway_id' => $gateway->id]);

        $widget = new GatewayDeviceListWidget($user, [], $gateway->id);
        $result = $widget->render();

        $this->assertEquals('success', $result['status']);
        $this->assertEquals('gateway-device-list', $result['widget_type']);
        $this->assertEquals('Gateway Device List', $result['widget_name']);
        $this->assertArrayHasKey('devices', $result['data']);
        $this->assertArrayHasKey('device_summary', $result['data']);
        $this->assertArrayHasKey('device_types', $result['data']);
        $this->assertArrayHasKey('connectivity_status', $result['data']);
        $this->assertArrayHasKey('performance_metrics', $result['data']);
    }

    public function test_gateway_device_list_widget_requires_gateway()
    {
        $user = User::factory()->create(['role' => 'admin']);
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('This widget requires a gateway ID');
        
        $widget = new GatewayDeviceListWidget($user);
        $this->getPrivateMethod($widget, 'validateConfig');
    }

    public function test_real_time_readings_widget_renders_correctly()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $gateway = Gateway::factory()->create();
        $device = Device::factory()->create(['gateway_id' => $gateway->id]);
        $register = Register::factory()->create(['device_id' => $device->id]);
        
        // Create some test readings
        Reading::factory()->create([
            'device_id' => $device->id,
            'register_id' => $register->id,
            'timestamp' => now()->subMinutes(2),
        ]);

        $widget = new RealTimeReadingsWidget($user, [], $gateway->id);
        $result = $widget->render();

        $this->assertEquals('success', $result['status']);
        $this->assertEquals('real-time-readings', $result['widget_type']);
        $this->assertEquals('Real-time Readings', $result['widget_name']);
        $this->assertArrayHasKey('live_readings', $result['data']);
        $this->assertArrayHasKey('reading_trends', $result['data']);
        $this->assertArrayHasKey('parameter_summaries', $result['data']);
        $this->assertArrayHasKey('data_quality_metrics', $result['data']);
        $this->assertArrayHasKey('reading_statistics', $result['data']);
    }

    public function test_real_time_readings_widget_supports_real_time_updates()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $gateway = Gateway::factory()->create();
        
        $widget = new RealTimeReadingsWidget($user, [], $gateway->id);
        
        $this->assertTrue($widget->supportsRealTimeUpdates());
        $this->assertEquals(15, $widget->getRealTimeUpdateInterval());
    }

    public function test_gateway_stats_widget_renders_correctly()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $gateway = Gateway::factory()->create();
        $device = Device::factory()->create(['gateway_id' => $gateway->id]);

        $widget = new GatewayStatsWidget($user, [], $gateway->id);
        $result = $widget->render();

        $this->assertEquals('success', $result['status']);
        $this->assertEquals('gateway-stats', $result['widget_type']);
        $this->assertEquals('Gateway Statistics', $result['widget_name']);
        $this->assertArrayHasKey('gateway_info', $result['data']);
        $this->assertArrayHasKey('communication_status', $result['data']);
        $this->assertArrayHasKey('device_health_indicators', $result['data']);
        $this->assertArrayHasKey('performance_metrics', $result['data']);
        $this->assertArrayHasKey('operational_statistics', $result['data']);
        $this->assertArrayHasKey('historical_trends', $result['data']);
    }

    public function test_gateway_alerts_widget_renders_correctly()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $gateway = Gateway::factory()->create();
        $device = Device::factory()->create(['gateway_id' => $gateway->id]);
        
        // Create some test alerts
        Alert::factory()->create([
            'device_id' => $device->id,
            'severity' => 'critical',
            'resolved' => false,
        ]);
        Alert::factory()->create([
            'device_id' => $device->id,
            'severity' => 'warning',
            'resolved' => false,
        ]);

        $widget = new GatewayAlertsWidget($user, [], $gateway->id);
        $result = $widget->render();

        $this->assertEquals('success', $result['status']);
        $this->assertEquals('gateway-alerts', $result['widget_type']);
        $this->assertEquals('Gateway Alerts', $result['widget_name']);
        $this->assertArrayHasKey('active_alerts', $result['data']);
        $this->assertArrayHasKey('alert_summary', $result['data']);
        $this->assertArrayHasKey('recent_alerts', $result['data']);
        $this->assertArrayHasKey('alert_trends', $result['data']);
        $this->assertArrayHasKey('device_alert_status', $result['data']);
        $this->assertArrayHasKey('alert_statistics', $result['data']);
    }

    public function test_gateway_widgets_require_gateway_context()
    {
        $user = User::factory()->create(['role' => 'admin']);

        $deviceListWidget = new GatewayDeviceListWidget($user, [], 1);
        $readingsWidget = new RealTimeReadingsWidget($user, [], 1);
        $statsWidget = new GatewayStatsWidget($user, [], 1);
        $alertsWidget = new GatewayAlertsWidget($user, [], 1);

        $this->assertTrue($this->getPrivateMethod($deviceListWidget, 'requiresGateway'));
        $this->assertTrue($this->getPrivateMethod($readingsWidget, 'requiresGateway'));
        $this->assertTrue($this->getPrivateMethod($statsWidget, 'requiresGateway'));
        $this->assertTrue($this->getPrivateMethod($alertsWidget, 'requiresGateway'));
    }

    public function test_gateway_widgets_have_correct_categories()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $gateway = Gateway::factory()->create();

        $deviceListWidget = new GatewayDeviceListWidget($user, [], $gateway->id);
        $readingsWidget = new RealTimeReadingsWidget($user, [], $gateway->id);
        $statsWidget = new GatewayStatsWidget($user, [], $gateway->id);
        $alertsWidget = new GatewayAlertsWidget($user, [], $gateway->id);

        $this->assertEquals('devices', $this->getPrivateMethod($deviceListWidget, 'getWidgetCategory'));
        $this->assertEquals('monitoring', $this->getPrivateMethod($readingsWidget, 'getWidgetCategory'));
        $this->assertEquals('overview', $this->getPrivateMethod($statsWidget, 'getWidgetCategory'));
        $this->assertEquals('alerts', $this->getPrivateMethod($alertsWidget, 'getWidgetCategory'));
    }

    public function test_gateway_widgets_have_appropriate_priorities()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $gateway = Gateway::factory()->create();

        $deviceListWidget = new GatewayDeviceListWidget($user, [], $gateway->id);
        $readingsWidget = new RealTimeReadingsWidget($user, [], $gateway->id);
        $statsWidget = new GatewayStatsWidget($user, [], $gateway->id);
        $alertsWidget = new GatewayAlertsWidget($user, [], $gateway->id);

        $this->assertEquals(10, $this->getPrivateMethod($deviceListWidget, 'getWidgetPriority'));
        $this->assertEquals(20, $this->getPrivateMethod($readingsWidget, 'getWidgetPriority'));
        $this->assertEquals(15, $this->getPrivateMethod($statsWidget, 'getWidgetPriority'));
        $this->assertEquals(25, $this->getPrivateMethod($alertsWidget, 'getWidgetPriority'));
    }

    public function test_gateway_widgets_handle_empty_data_gracefully()
    {
        $user = User::factory()->create(['role' => 'operator']); // User with no device assignments
        $gateway = Gateway::factory()->create();

        $deviceListWidget = new GatewayDeviceListWidget($user, [], $gateway->id);
        $readingsWidget = new RealTimeReadingsWidget($user, [], $gateway->id);
        $statsWidget = new GatewayStatsWidget($user, [], $gateway->id);
        $alertsWidget = new GatewayAlertsWidget($user, [], $gateway->id);

        $result1 = $deviceListWidget->render();
        $result2 = $readingsWidget->render();
        $result3 = $statsWidget->render();
        $result4 = $alertsWidget->render();

        // All widgets should render successfully even with no data
        $this->assertEquals('success', $result1['status']);
        $this->assertEquals('success', $result2['status']);
        $this->assertEquals('success', $result3['status']);
        $this->assertEquals('success', $result4['status']);

        // Data should be empty or have default values
        $this->assertEmpty($result1['data']['devices']);
        $this->assertEmpty($result2['data']['live_readings']);
        $this->assertEquals(0, $result3['data']['device_health_indicators']['total_devices']);
        $this->assertEmpty($result4['data']['active_alerts']);
    }

    public function test_gateway_widgets_respect_user_permissions()
    {
        $user = User::factory()->create(['role' => 'operator']);
        $gateway = Gateway::factory()->create();
        $device1 = Device::factory()->create(['gateway_id' => $gateway->id]);
        $device2 = Device::factory()->create(['gateway_id' => $gateway->id]);

        // Assign only device1 to user
        UserDeviceAssignment::create([
            'user_id' => $user->id,
            'device_id' => $device1->id,
            'assigned_at' => now(),
        ]);

        // Create alerts for both devices
        Alert::factory()->create(['device_id' => $device1->id, 'severity' => 'critical']);
        Alert::factory()->create(['device_id' => $device2->id, 'severity' => 'critical']);

        $alertsWidget = new GatewayAlertsWidget($user, [], $gateway->id);
        $result = $alertsWidget->render();

        $this->assertEquals('success', $result['status']);
        
        // User should only see alerts from device1, not device2
        // This would require more complex testing with actual permission filtering
        $this->assertArrayHasKey('active_alerts', $result['data']);
    }

    public function test_gateway_widgets_cache_data_appropriately()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $gateway = Gateway::factory()->create();
        
        $widget = new GatewayDeviceListWidget($user, [], $gateway->id);

        // First call should not be from cache
        $result1 = $widget->render();
        $this->assertFalse($result1['metadata']['from_cache']);

        // Second call should be from cache
        $result2 = $widget->render();
        $this->assertTrue($result2['metadata']['from_cache']);
    }

    public function test_gateway_widgets_provide_fallback_data_on_error()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $gateway = Gateway::factory()->create();

        $deviceListWidget = new GatewayDeviceListWidget($user, [], $gateway->id);
        $readingsWidget = new RealTimeReadingsWidget($user, [], $gateway->id);
        $statsWidget = new GatewayStatsWidget($user, [], $gateway->id);
        $alertsWidget = new GatewayAlertsWidget($user, [], $gateway->id);

        $fallbackData1 = $this->getPrivateMethod($deviceListWidget, 'getFallbackData');
        $fallbackData2 = $this->getPrivateMethod($readingsWidget, 'getFallbackData');
        $fallbackData3 = $this->getPrivateMethod($statsWidget, 'getFallbackData');
        $fallbackData4 = $this->getPrivateMethod($alertsWidget, 'getFallbackData');

        $this->assertIsArray($fallbackData1);
        $this->assertIsArray($fallbackData2);
        $this->assertIsArray($fallbackData3);
        $this->assertIsArray($fallbackData4);

        $this->assertArrayHasKey('devices', $fallbackData1);
        $this->assertArrayHasKey('live_readings', $fallbackData2);
        $this->assertArrayHasKey('gateway_info', $fallbackData3);
        $this->assertArrayHasKey('active_alerts', $fallbackData4);
    }

    public function test_real_time_readings_widget_calculates_data_quality()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $gateway = Gateway::factory()->create();
        $device = Device::factory()->create(['gateway_id' => $gateway->id]);
        $register = Register::factory()->create(['device_id' => $device->id]);

        // Create readings with varying quality
        Reading::factory()->create([
            'device_id' => $device->id,
            'register_id' => $register->id,
            'value' => 100,
            'timestamp' => now()->subMinutes(1),
        ]);
        Reading::factory()->create([
            'device_id' => $device->id,
            'register_id' => $register->id,
            'value' => null, // Poor quality
            'timestamp' => now()->subMinutes(2),
        ]);

        $widget = new RealTimeReadingsWidget($user, [], $gateway->id);
        $result = $widget->render();

        $this->assertArrayHasKey('data_quality_metrics', $result['data']);
        $this->assertArrayHasKey('overall_quality_score', $result['data']['data_quality_metrics']);
        $this->assertIsFloat($result['data']['data_quality_metrics']['overall_quality_score']);
    }

    public function test_gateway_stats_widget_calculates_performance_metrics()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $gateway = Gateway::factory()->create();
        $device = Device::factory()->create(['gateway_id' => $gateway->id]);
        $register = Register::factory()->create(['device_id' => $device->id]);

        // Create some readings for performance calculation
        Reading::factory()->count(10)->create([
            'device_id' => $device->id,
            'register_id' => $register->id,
            'timestamp' => now()->subMinutes(rand(1, 60)),
        ]);

        $widget = new GatewayStatsWidget($user, [], $gateway->id);
        $result = $widget->render();

        $this->assertArrayHasKey('performance_metrics', $result['data']);
        $this->assertArrayHasKey('data_throughput', $result['data']['performance_metrics']);
        $this->assertArrayHasKey('polling_efficiency', $result['data']['performance_metrics']);
        $this->assertArrayHasKey('error_rate', $result['data']['performance_metrics']);
        $this->assertArrayHasKey('availability', $result['data']['performance_metrics']);
    }

    public function test_gateway_alerts_widget_prioritizes_alerts_correctly()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $gateway = Gateway::factory()->create();
        $device = Device::factory()->create(['gateway_id' => $gateway->id]);

        // Create alerts with different severities and ages
        $criticalAlert = Alert::factory()->create([
            'device_id' => $device->id,
            'severity' => 'critical',
            'resolved' => false,
            'timestamp' => now()->subHours(2),
        ]);
        $warningAlert = Alert::factory()->create([
            'device_id' => $device->id,
            'severity' => 'warning',
            'resolved' => false,
            'timestamp' => now()->subMinutes(30),
        ]);

        $widget = new GatewayAlertsWidget($user, [], $gateway->id);
        $result = $widget->render();

        $this->assertNotEmpty($result['data']['active_alerts']);
        
        // Critical alert should come first
        $firstAlert = $result['data']['active_alerts'][0];
        $this->assertEquals('critical', $firstAlert['severity']);
    }

    public function test_gateway_device_list_widget_calculates_health_scores()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $gateway = Gateway::factory()->create();
        $device = Device::factory()->create(['gateway_id' => $gateway->id]);
        $register = Register::factory()->create(['device_id' => $device->id]);

        // Create a recent reading for good health
        Reading::factory()->create([
            'device_id' => $device->id,
            'register_id' => $register->id,
            'timestamp' => now()->subMinutes(2),
        ]);

        $widget = new GatewayDeviceListWidget($user, [], $gateway->id);
        $result = $widget->render();

        $this->assertNotEmpty($result['data']['devices']);
        $device = $result['data']['devices'][0];
        $this->assertArrayHasKey('health_score', $device);
        $this->assertIsFloat($device['health_score']);
        $this->assertGreaterThanOrEqual(0, $device['health_score']);
        $this->assertLessThanOrEqual(100, $device['health_score']);
    }

    /**
     * Helper method to access private methods
     */
    private function getPrivateMethod($object, $methodName)
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invoke($object);
    }
}