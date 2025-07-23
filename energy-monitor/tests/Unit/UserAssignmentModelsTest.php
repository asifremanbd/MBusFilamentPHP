<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Models\User;
use App\Models\Device;
use App\Models\Gateway;
use App\Models\UserDeviceAssignment;
use App\Models\UserGatewayAssignment;
use App\Models\UserDashboardConfig;
use App\Models\DashboardAccessLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesApplication;

class UserAssignmentModelsTest extends TestCase
{
    use CreatesApplication, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createApplication();
    }

    public function test_user_gateway_assignment_model()
    {
        $user = User::factory()->create();
        $gateway = Gateway::factory()->create();
        $assignedBy = User::factory()->create(['role' => 'admin']);

        $assignment = UserGatewayAssignment::create([
            'user_id' => $user->id,
            'gateway_id' => $gateway->id,
            'assigned_at' => now(),
            'assigned_by' => $assignedBy->id,
        ]);

        $this->assertInstanceOf(UserGatewayAssignment::class, $assignment);
        $this->assertEquals($user->id, $assignment->user_id);
        $this->assertEquals($gateway->id, $assignment->gateway_id);
        $this->assertEquals($assignedBy->id, $assignment->assigned_by);
    }

    public function test_user_gateway_assignment_relationships()
    {
        $user = User::factory()->create();
        $gateway = Gateway::factory()->create();
        $assignedBy = User::factory()->create(['role' => 'admin']);

        $assignment = UserGatewayAssignment::create([
            'user_id' => $user->id,
            'gateway_id' => $gateway->id,
            'assigned_at' => now(),
            'assigned_by' => $assignedBy->id,
        ]);

        $this->assertEquals($user->id, $assignment->user->id);
        $this->assertEquals($gateway->id, $assignment->gateway->id);
        $this->assertEquals($assignedBy->id, $assignment->assignedBy->id);
    }

    public function test_user_dashboard_config_model()
    {
        $user = User::factory()->create();

        $config = UserDashboardConfig::create([
            'user_id' => $user->id,
            'dashboard_type' => 'global',
            'widget_config' => [
                'visibility' => [
                    'system-overview' => true,
                    'alerts-summary' => false,
                ]
            ],
            'layout_config' => [
                'positions' => [
                    'system-overview' => ['row' => 0, 'col' => 0],
                ],
                'sizes' => [
                    'system-overview' => ['width' => 12, 'height' => 4],
                ]
            ],
        ]);

        $this->assertInstanceOf(UserDashboardConfig::class, $config);
        $this->assertEquals($user->id, $config->user_id);
        $this->assertEquals('global', $config->dashboard_type);
        $this->assertIsArray($config->widget_config);
        $this->assertIsArray($config->layout_config);
    }

    public function test_user_dashboard_config_widget_methods()
    {
        $user = User::factory()->create();

        $config = UserDashboardConfig::create([
            'user_id' => $user->id,
            'dashboard_type' => 'global',
            'widget_config' => [
                'visibility' => [
                    'system-overview' => true,
                    'alerts-summary' => false,
                ]
            ],
            'layout_config' => [
                'positions' => [
                    'system-overview' => ['row' => 0, 'col' => 0],
                ],
                'sizes' => [
                    'system-overview' => ['width' => 12, 'height' => 4],
                ]
            ],
        ]);

        // Test widget visibility
        $this->assertTrue($config->getWidgetVisibility('system-overview'));
        $this->assertFalse($config->getWidgetVisibility('alerts-summary'));
        $this->assertTrue($config->getWidgetVisibility('non-existent')); // Default true

        // Test widget position
        $position = $config->getWidgetPosition('system-overview');
        $this->assertEquals(['row' => 0, 'col' => 0], $position);

        // Test widget size
        $size = $config->getWidgetSize('system-overview');
        $this->assertEquals(['width' => 12, 'height' => 4], $size);

        // Test default values
        $defaultPosition = $config->getWidgetPosition('non-existent');
        $this->assertEquals(['row' => 0, 'col' => 0], $defaultPosition);

        $defaultSize = $config->getWidgetSize('non-existent');
        $this->assertEquals(['width' => 12, 'height' => 4], $defaultSize);
    }

    public function test_user_dashboard_config_setter_methods()
    {
        $user = User::factory()->create();

        $config = UserDashboardConfig::create([
            'user_id' => $user->id,
            'dashboard_type' => 'global',
            'widget_config' => ['visibility' => []],
            'layout_config' => ['positions' => [], 'sizes' => []],
        ]);

        // Test setting widget visibility
        $config->setWidgetVisibility('test-widget', false);
        $this->assertFalse($config->getWidgetVisibility('test-widget'));

        // Test setting widget position
        $config->setWidgetPosition('test-widget', ['row' => 1, 'col' => 2]);
        $this->assertEquals(['row' => 1, 'col' => 2], $config->getWidgetPosition('test-widget'));

        // Test setting widget size
        $config->setWidgetSize('test-widget', ['width' => 6, 'height' => 8]);
        $this->assertEquals(['width' => 6, 'height' => 8], $config->getWidgetSize('test-widget'));

        // Test updating widget layout
        $config->updateWidgetLayout('another-widget', ['row' => 2, 'col' => 1], ['width' => 4, 'height' => 6]);
        $this->assertEquals(['row' => 2, 'col' => 1], $config->getWidgetPosition('another-widget'));
        $this->assertEquals(['width' => 4, 'height' => 6], $config->getWidgetSize('another-widget'));
    }

    public function test_user_dashboard_config_visible_hidden_widgets()
    {
        $user = User::factory()->create();

        $config = UserDashboardConfig::create([
            'user_id' => $user->id,
            'dashboard_type' => 'global',
            'widget_config' => [
                'visibility' => [
                    'widget1' => true,
                    'widget2' => false,
                    'widget3' => true,
                ]
            ],
            'layout_config' => [],
        ]);

        $visibleWidgets = $config->getVisibleWidgets();
        $hiddenWidgets = $config->getHiddenWidgets();

        $this->assertContains('widget1', $visibleWidgets);
        $this->assertContains('widget3', $visibleWidgets);
        $this->assertNotContains('widget2', $visibleWidgets);

        $this->assertContains('widget2', $hiddenWidgets);
        $this->assertNotContains('widget1', $hiddenWidgets);
        $this->assertNotContains('widget3', $hiddenWidgets);
    }

    public function test_dashboard_access_log_model()
    {
        $user = User::factory()->create();
        $gateway = Gateway::factory()->create();

        $log = DashboardAccessLog::create([
            'user_id' => $user->id,
            'dashboard_type' => 'global',
            'gateway_id' => $gateway->id,
            'widget_accessed' => 'system-overview',
            'access_granted' => true,
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0',
            'accessed_at' => now(),
        ]);

        $this->assertInstanceOf(DashboardAccessLog::class, $log);
        $this->assertEquals($user->id, $log->user_id);
        $this->assertEquals('global', $log->dashboard_type);
        $this->assertEquals($gateway->id, $log->gateway_id);
        $this->assertTrue($log->access_granted);
    }

    public function test_dashboard_access_log_relationships()
    {
        $user = User::factory()->create();
        $gateway = Gateway::factory()->create();

        $log = DashboardAccessLog::create([
            'user_id' => $user->id,
            'dashboard_type' => 'global',
            'gateway_id' => $gateway->id,
            'widget_accessed' => 'system-overview',
            'access_granted' => true,
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0',
            'accessed_at' => now(),
        ]);

        $this->assertEquals($user->id, $log->user->id);
        $this->assertEquals($gateway->id, $log->gateway->id);
    }

    public function test_dashboard_access_log_static_methods()
    {
        $user = User::factory()->create();
        $gateway = Gateway::factory()->create();

        // Test logAccess method
        $log = DashboardAccessLog::logAccess(
            $user->id,
            'global',
            true,
            '192.168.1.1',
            'Mozilla/5.0',
            $gateway->id,
            'system-overview'
        );

        $this->assertInstanceOf(DashboardAccessLog::class, $log);
        $this->assertEquals($user->id, $log->user_id);
        $this->assertTrue($log->access_granted);

        // Create some failed attempts
        DashboardAccessLog::logAccess($user->id, 'global', false, '192.168.1.1');
        DashboardAccessLog::logAccess($user->id, 'global', false, '192.168.1.1');

        // Test getFailedAttempts method
        $failedAttempts = DashboardAccessLog::getFailedAttempts($user->id);
        $this->assertEquals(2, $failedAttempts);

        // Test getUserAccessLogs method
        $userLogs = DashboardAccessLog::getUserAccessLogs($user->id);
        $this->assertCount(3, $userLogs); // 1 success + 2 failures

        // Test getGatewayAccessLogs method
        $gatewayLogs = DashboardAccessLog::getGatewayAccessLogs($gateway->id);
        $this->assertCount(1, $gatewayLogs); // Only the successful one had gateway_id
    }

    public function test_user_model_new_relationships()
    {
        $user = User::factory()->create();
        $gateway = Gateway::factory()->create();

        // Test dashboard configs relationship
        $config = UserDashboardConfig::create([
            'user_id' => $user->id,
            'dashboard_type' => 'global',
            'widget_config' => [],
            'layout_config' => [],
        ]);

        $this->assertCount(1, $user->dashboardConfigs);
        $this->assertEquals($config->id, $user->dashboardConfigs->first()->id);

        // Test access logs relationship
        $log = DashboardAccessLog::logAccess($user->id, 'global', true, '192.168.1.1');

        $this->assertCount(1, $user->accessLogs);
        $this->assertEquals($log->id, $user->accessLogs->first()->id);

        // Test gateway assignments relationship
        $gatewayAssignment = UserGatewayAssignment::create([
            'user_id' => $user->id,
            'gateway_id' => $gateway->id,
            'assigned_at' => now(),
        ]);

        $this->assertCount(1, $user->gatewayAssignments);
        $this->assertEquals($gatewayAssignment->id, $user->gatewayAssignments->first()->id);
    }
}