<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Gateway;
use App\Models\Device;
use App\Models\UserDeviceAssignment;
use App\Models\UserDashboardConfig;

class DashboardConfigControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->operator = User::factory()->create(['role' => 'operator']);
        
        $this->gateway = Gateway::factory()->create();
        $this->device = Device::factory()->create(['gateway_id' => $this->gateway->id]);
        
        UserDeviceAssignment::create([
            'user_id' => $this->operator->id,
            'device_id' => $this->device->id,
            'assigned_at' => now(),
        ]);
    }

    public function test_get_config_returns_user_dashboard_configuration()
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/dashboard/config', [
                'dashboard_type' => 'global'
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'config' => [
                'dashboard_type',
                'widget_config',
                'layout_config',
                'updated_at'
            ]
        ]);
        
        $this->assertTrue($response->json('success'));
        $this->assertEquals('global', $response->json('config.dashboard_type'));
    }

    public function test_get_config_validates_dashboard_type()
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/dashboard/config', [
                'dashboard_type' => 'invalid'
            ]);

        $response->assertStatus(400);
        $response->assertJsonValidationErrors(['dashboard_type']);
    }

    public function test_update_widget_visibility_works_correctly()
    {
        $response = $this->actingAs($this->admin)
            ->putJson('/api/dashboard/widget/visibility', [
                'dashboard_type' => 'global',
                'widget_id' => 'system-overview',
                'visible' => false
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'widget_id' => 'system-overview',
            'visible' => false
        ]);

        // Verify the change was persisted
        $config = UserDashboardConfig::where('user_id', $this->admin->id)
            ->where('dashboard_type', 'global')
            ->first();
        
        $this->assertFalse($config->getWidgetVisibility('system-overview'));
    }

    public function test_update_widget_visibility_validates_input()
    {
        $response = $this->actingAs($this->admin)
            ->putJson('/api/dashboard/widget/visibility', [
                'dashboard_type' => 'invalid',
                'widget_id' => '',
                'visible' => 'not_boolean'
            ]);

        $response->assertStatus(400);
        $response->assertJsonValidationErrors(['dashboard_type', 'widget_id', 'visible']);
    }

    public function test_update_widget_visibility_checks_widget_access()
    {
        $response = $this->actingAs($this->operator)
            ->putJson('/api/dashboard/widget/visibility', [
                'dashboard_type' => 'global',
                'widget_id' => 'nonexistent-widget',
                'visible' => true
            ]);

        $response->assertStatus(403);
        $response->assertJson([
            'error' => 'Access denied'
        ]);
    }

    public function test_update_widget_layout_works_correctly()
    {
        $layoutUpdates = [
            [
                'widget_id' => 'system-overview',
                'position' => ['row' => 1, 'col' => 2],
                'size' => ['width' => 8, 'height' => 6]
            ],
            [
                'widget_id' => 'cross-gateway-alerts',
                'position' => ['row' => 0, 'col' => 0],
                'size' => ['width' => 4, 'height' => 4]
            ]
        ];

        $response = $this->actingAs($this->admin)
            ->putJson('/api/dashboard/widget/layout', [
                'dashboard_type' => 'global',
                'layout_updates' => $layoutUpdates
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'updated_widgets' => ['system-overview', 'cross-gateway-alerts']
        ]);

        // Verify the changes were persisted
        $config = UserDashboardConfig::where('user_id', $this->admin->id)
            ->where('dashboard_type', 'global')
            ->first();
        
        $this->assertEquals(['row' => 1, 'col' => 2], $config->getWidgetPosition('system-overview'));
        $this->assertEquals(['width' => 8, 'height' => 6], $config->getWidgetSize('system-overview'));
    }

    public function test_update_widget_layout_validates_input()
    {
        $response = $this->actingAs($this->admin)
            ->putJson('/api/dashboard/widget/layout', [
                'dashboard_type' => 'global',
                'layout_updates' => [
                    [
                        'widget_id' => '',
                        'position' => ['row' => -1, 'col' => 15],
                        'size' => ['width' => 0, 'height' => 25]
                    ]
                ]
            ]);

        $response->assertStatus(400);
        $response->assertJsonValidationErrors([
            'layout_updates.0.widget_id',
            'layout_updates.0.position.row',
            'layout_updates.0.position.col',
            'layout_updates.0.size.width',
            'layout_updates.0.size.height'
        ]);
    }

    public function test_reset_config_restores_defaults()
    {
        // First, modify the config
        $this->actingAs($this->admin)
            ->putJson('/api/dashboard/widget/visibility', [
                'dashboard_type' => 'global',
                'widget_id' => 'system-overview',
                'visible' => false
            ]);

        // Verify it was changed
        $config = UserDashboardConfig::where('user_id', $this->admin->id)
            ->where('dashboard_type', 'global')
            ->first();
        $this->assertFalse($config->getWidgetVisibility('system-overview'));

        // Reset to defaults
        $response = $this->actingAs($this->admin)
            ->postJson('/api/dashboard/config/reset', [
                'dashboard_type' => 'global'
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Dashboard configuration reset to defaults'
        ]);

        // Verify it was reset
        $config->refresh();
        $this->assertTrue($config->getWidgetVisibility('system-overview'));
    }

    public function test_get_available_widgets_returns_authorized_widgets()
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/dashboard/widgets', [
                'dashboard_type' => 'global'
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'dashboard_type',
            'widgets'
        ]);
        
        $this->assertTrue($response->json('success'));
        $this->assertEquals('global', $response->json('dashboard_type'));
        $this->assertIsArray($response->json('widgets'));
    }

    public function test_get_available_widgets_for_gateway_dashboard()
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/dashboard/widgets', [
                'dashboard_type' => 'gateway',
                'gateway_id' => $this->gateway->id
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'dashboard_type',
            'gateway_id',
            'widgets'
        ]);
        
        $this->assertEquals('gateway', $response->json('dashboard_type'));
        $this->assertEquals($this->gateway->id, $response->json('gateway_id'));
    }

    public function test_get_available_widgets_validates_gateway_access()
    {
        $unauthorizedGateway = Gateway::factory()->create();
        
        $response = $this->actingAs($this->operator)
            ->getJson('/api/dashboard/widgets', [
                'dashboard_type' => 'gateway',
                'gateway_id' => $unauthorizedGateway->id
            ]);

        $response->assertStatus(403);
    }

    public function test_update_widget_config_works_correctly()
    {
        $widgetConfig = [
            'time_range' => '24h',
            'limit' => 10,
            'show_details' => true
        ];

        $response = $this->actingAs($this->admin)
            ->putJson('/api/dashboard/widget/config', [
                'dashboard_type' => 'global',
                'widget_id' => 'system-overview',
                'config' => $widgetConfig
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'widget_id' => 'system-overview'
        ]);

        // Verify the config was saved
        $config = UserDashboardConfig::where('user_id', $this->admin->id)
            ->where('dashboard_type', 'global')
            ->first();
        
        $this->assertEquals($widgetConfig, $config->widget_config['configs']['system-overview']);
    }

    public function test_get_widget_performance_returns_metrics()
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/dashboard/widget/performance', [
                'dashboard_type' => 'global',
                'widget_id' => 'system-overview'
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'widget_id',
            'performance' => [
                'widget_type',
                'execution_time',
                'memory_usage',
                'status',
                'timestamp'
            ]
        ]);
        
        $this->assertTrue($response->json('success'));
        $this->assertEquals('system-overview', $response->json('widget_id'));
    }

    public function test_clear_widget_cache_works_for_specific_widget()
    {
        $response = $this->actingAs($this->admin)
            ->deleteJson('/api/dashboard/widget/cache', [
                'dashboard_type' => 'global',
                'widget_id' => 'system-overview'
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'widget_id' => 'system-overview'
        ]);
    }

    public function test_clear_widget_cache_works_for_all_widgets()
    {
        $response = $this->actingAs($this->admin)
            ->deleteJson('/api/dashboard/widget/cache', [
                'dashboard_type' => 'global'
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true
        ]);
        $response->assertJsonStructure([
            'success',
            'message',
            'cleared_count'
        ]);
    }

    public function test_api_endpoints_require_authentication()
    {
        $endpoints = [
            ['GET', '/api/dashboard/config'],
            ['PUT', '/api/dashboard/widget/visibility'],
            ['PUT', '/api/dashboard/widget/layout'],
            ['POST', '/api/dashboard/config/reset'],
            ['GET', '/api/dashboard/widgets'],
            ['PUT', '/api/dashboard/widget/config'],
            ['GET', '/api/dashboard/widget/performance'],
            ['DELETE', '/api/dashboard/widget/cache'],
        ];

        foreach ($endpoints as [$method, $url]) {
            $response = $this->json($method, $url);
            $response->assertStatus(401);
        }
    }

    public function test_api_endpoints_handle_exceptions_gracefully()
    {
        // Mock a service to throw an exception
        $this->mock(\App\Services\DashboardConfigService::class, function ($mock) {
            $mock->shouldReceive('getUserDashboardConfig')
                ->andThrow(new \Exception('Test exception'));
        });

        $response = $this->actingAs($this->admin)
            ->getJson('/api/dashboard/config', [
                'dashboard_type' => 'global'
            ]);

        $response->assertStatus(500);
        $response->assertJsonStructure([
            'error',
            'message'
        ]);
    }

    public function test_widget_access_validation_works_correctly()
    {
        // Test with a widget that doesn't exist for the dashboard type
        $response = $this->actingAs($this->admin)
            ->putJson('/api/dashboard/widget/visibility', [
                'dashboard_type' => 'global',
                'widget_id' => 'gateway-device-list', // This is a gateway widget, not global
                'visible' => true
            ]);

        $response->assertStatus(403);
        $response->assertJson([
            'error' => 'Access denied'
        ]);
    }

    public function test_operator_can_configure_authorized_widgets()
    {
        $response = $this->actingAs($this->operator)
            ->putJson('/api/dashboard/widget/visibility', [
                'dashboard_type' => 'global',
                'widget_id' => 'system-overview',
                'visible' => false
            ]);

        // Operator should be able to configure widgets they have access to
        $response->assertStatus(200);
    }

    public function test_dashboard_config_persists_across_requests()
    {
        // Update visibility
        $this->actingAs($this->admin)
            ->putJson('/api/dashboard/widget/visibility', [
                'dashboard_type' => 'global',
                'widget_id' => 'system-overview',
                'visible' => false
            ]);

        // Update layout
        $this->actingAs($this->admin)
            ->putJson('/api/dashboard/widget/layout', [
                'dashboard_type' => 'global',
                'layout_updates' => [
                    [
                        'widget_id' => 'system-overview',
                        'position' => ['row' => 2, 'col' => 3],
                        'size' => ['width' => 10, 'height' => 8]
                    ]
                ]
            ]);

        // Get config and verify both changes persisted
        $response = $this->actingAs($this->admin)
            ->getJson('/api/dashboard/config', [
                'dashboard_type' => 'global'
            ]);

        $response->assertStatus(200);
        
        $config = $response->json('config');
        $this->assertFalse($config['widget_config']['visibility']['system-overview']);
        $this->assertEquals(['row' => 2, 'col' => 3], $config['layout_config']['positions']['system-overview']);
        $this->assertEquals(['width' => 10, 'height' => 8], $config['layout_config']['sizes']['system-overview']);
    }
}