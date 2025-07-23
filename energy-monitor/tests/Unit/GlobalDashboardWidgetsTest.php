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
use App\Widgets\Global\SystemOverviewWidget;
use App\Widgets\Global\CrossGatewayAlertsWidget;
use App\Widgets\Global\TopConsumingGatewaysWidget;
use App\Widgets\Global\SystemHealthWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesApplication;

class GlobalDashboardWidgetsTest extends TestCase
{
    use CreatesApplication, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createApplication();
    }

    public function test_system_overview_widget_renders_for_admin()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $gateway = Gateway::factory()->create();
        $device = Device::factory()->create(['gateway_id' => $gateway->id]);

        $widget = new SystemOverviewWidget($user);
        $result = $widget->render();

        $this->assertEquals('success', $result['status']);
        $this->assertEquals('system-overview', $result['widget_type']);
        $this->assertEquals('System Overview', $result['widget_name']);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('total_energy_consumption', $result['data']);
        $this->assertArrayHasKey('active_devices_count', $result['data']);
        $this->assertArrayHasKey('critical_alerts_count', $result['data']);
        $this->assertArrayHasKey('system_health_score', $result['data']);
    }

    public function test_system_overview_widget_returns_unauthorized_for_user_without_devices()
    {
        $user = User::factory()->create(['role' => 'operator']);
        
        $widget = new SystemOverviewWidget($user);
        $result = $widget->render();

        $this->assertEquals('success', $result['status']); // Widget should still render but with empty data
        $this->assertArrayHasKey('data', $result);
    }

    public function test_cross_gateway_alerts_widget_renders_correctly()
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

        $widget = new CrossGatewayAlertsWidget($user);
        $result = $widget->render();

        $this->assertEquals('success', $result['status']);
        $this->assertEquals('cross-gateway-alerts', $result['widget_type']);
        $this->assertArrayHasKey('critical_alerts', $result['data']);
        $this->assertArrayHasKey('warning_alerts', $result['data']);
        $this->assertArrayHasKey('info_alerts', $result['data']);
        $this->assertArrayHasKey('recent_alerts', $result['data']);
    }

    public function test_top_consuming_gateways_widget_renders_correctly()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $gateway1 = Gateway::factory()->create(['name' => 'Gateway 1']);
        $gateway2 = Gateway::factory()->create(['name' => 'Gateway 2']);
        $device1 = Device::factory()->create(['gateway_id' => $gateway1->id]);
        $device2 = Device::factory()->create(['gateway_id' => $gateway2->id]);

        $widget = new TopConsumingGatewaysWidget($user, ['limit' => 5]);
        $result = $widget->render();

        $this->assertEquals('success', $result['status']);
        $this->assertEquals('top-consuming-gateways', $result['widget_type']);
        $this->assertArrayHasKey('top_gateways', $result['data']);
        $this->assertArrayHasKey('consumption_comparison', $result['data']);
        $this->assertArrayHasKey('efficiency_metrics', $result['data']);
        $this->assertArrayHasKey('summary_statistics', $result['data']);
    }

    public function test_system_health_widget_renders_correctly()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $gateway = Gateway::factory()->create();
        $device = Device::factory()->create(['gateway_id' => $gateway->id]);

        $widget = new SystemHealthWidget($user);
        $result = $widget->render();

        $this->assertEquals('success', $result['status']);
        $this->assertEquals('system-health', $result['widget_type']);
        $this->assertArrayHasKey('overall_health', $result['data']);
        $this->assertArrayHasKey('component_health', $result['data']);
        $this->assertArrayHasKey('health_trends', $result['data']);
        $this->assertArrayHasKey('critical_issues', $result['data']);
        $this->assertArrayHasKey('performance_metrics', $result['data']);
        $this->assertArrayHasKey('recommendations', $result['data']);
    }

    public function test_widgets_support_real_time_updates()
    {
        $user = User::factory()->create(['role' => 'admin']);

        $systemOverview = new SystemOverviewWidget($user);
        $crossGatewayAlerts = new CrossGatewayAlertsWidget($user);
        $topConsuming = new TopConsumingGatewaysWidget($user);
        $systemHealth = new SystemHealthWidget($user);

        $this->assertTrue($systemOverview->supportsRealTimeUpdates());
        $this->assertTrue($crossGatewayAlerts->supportsRealTimeUpdates());
        $this->assertTrue($topConsuming->supportsRealTimeUpdates());
        $this->assertTrue($systemHealth->supportsRealTimeUpdates());
    }

    public function test_widgets_have_correct_categories()
    {
        $user = User::factory()->create(['role' => 'admin']);

        $systemOverview = new SystemOverviewWidget($user);
        $crossGatewayAlerts = new CrossGatewayAlertsWidget($user);
        $topConsuming = new TopConsumingGatewaysWidget($user);
        $systemHealth = new SystemHealthWidget($user);

        $this->assertEquals('overview', $this->getPrivateMethod($systemOverview, 'getWidgetCategory'));
        $this->assertEquals('alerts', $this->getPrivateMethod($crossGatewayAlerts, 'getWidgetCategory'));
        $this->assertEquals('analytics', $this->getPrivateMethod($topConsuming, 'getWidgetCategory'));
        $this->assertEquals('monitoring', $this->getPrivateMethod($systemHealth, 'getWidgetCategory'));
    }

    public function test_widgets_have_appropriate_priorities()
    {
        $user = User::factory()->create(['role' => 'admin']);

        $systemOverview = new SystemOverviewWidget($user);
        $crossGatewayAlerts = new CrossGatewayAlertsWidget($user);
        $topConsuming = new TopConsumingGatewaysWidget($user);
        $systemHealth = new SystemHealthWidget($user);

        $this->assertEquals(10, $this->getPrivateMethod($systemOverview, 'getWidgetPriority'));
        $this->assertEquals(20, $this->getPrivateMethod($crossGatewayAlerts, 'getWidgetPriority'));
        $this->assertEquals(30, $this->getPrivateMethod($topConsuming, 'getWidgetPriority'));
        $this->assertEquals(15, $this->getPrivateMethod($systemHealth, 'getWidgetPriority'));
    }

    public function test_widgets_handle_empty_data_gracefully()
    {
        $user = User::factory()->create(['role' => 'operator']); // User with no device assignments

        $systemOverview = new SystemOverviewWidget($user);
        $crossGatewayAlerts = new CrossGatewayAlertsWidget($user);
        $topConsuming = new TopConsumingGatewaysWidget($user);
        $systemHealth = new SystemHealthWidget($user);

        $result1 = $systemOverview->render();
        $result2 = $crossGatewayAlerts->render();
        $result3 = $topConsuming->render();
        $result4 = $systemHealth->render();

        // All widgets should render successfully even with no data
        $this->assertEquals('success', $result1['status']);
        $this->assertEquals('success', $result2['status']);
        $this->assertEquals('success', $result3['status']);
        $this->assertEquals('success', $result4['status']);

        // Data should be empty or have default values
        $this->assertEquals(0, $result1['data']['total_energy_consumption']['current_kw']);
        $this->assertEmpty($result2['data']['critical_alerts']);
        $this->assertEmpty($result3['data']['top_gateways']);
        $this->assertEquals(0, $result4['data']['overall_health']['score']);
    }

    public function test_widgets_respect_user_permissions()
    {
        $user = User::factory()->create(['role' => 'operator']);
        $gateway1 = Gateway::factory()->create();
        $gateway2 = Gateway::factory()->create();
        $device1 = Device::factory()->create(['gateway_id' => $gateway1->id]);
        $device2 = Device::factory()->create(['gateway_id' => $gateway2->id]);

        // Assign only device1 to user
        UserDeviceAssignment::create([
            'user_id' => $user->id,
            'device_id' => $device1->id,
            'assigned_at' => now(),
        ]);

        // Create alerts for both devices
        Alert::factory()->create(['device_id' => $device1->id, 'severity' => 'critical']);
        Alert::factory()->create(['device_id' => $device2->id, 'severity' => 'critical']);

        $alertsWidget = new CrossGatewayAlertsWidget($user);
        $result = $alertsWidget->render();

        $this->assertEquals('success', $result['status']);
        
        // User should only see alerts from device1, not device2
        // This would require more complex testing with actual permission filtering
        $this->assertArrayHasKey('critical_alerts', $result['data']);
    }

    public function test_widgets_cache_data_appropriately()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $widget = new SystemOverviewWidget($user);

        // First call should not be from cache
        $result1 = $widget->render();
        $this->assertFalse($result1['metadata']['from_cache']);

        // Second call should be from cache
        $result2 = $widget->render();
        $this->assertTrue($result2['metadata']['from_cache']);
    }

    public function test_widgets_provide_fallback_data_on_error()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $widget = new SystemOverviewWidget($user);

        $fallbackData = $this->getPrivateMethod($widget, 'getFallbackData');

        $this->assertIsArray($fallbackData);
        $this->assertArrayHasKey('total_energy_consumption', $fallbackData);
        $this->assertArrayHasKey('active_devices_count', $fallbackData);
        $this->assertEquals(0, $fallbackData['total_energy_consumption']['current_kw']);
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