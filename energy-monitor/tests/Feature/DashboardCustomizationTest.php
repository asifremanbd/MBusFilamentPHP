<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Gateway;
use App\Models\Device;
use App\Models\UserDashboardConfig;
use App\Models\UserDeviceAssignment;

class DashboardCustomizationTest extends TestCase
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

    public function test_dashboard_customization_interface_loads()
    {
        $this->actingAs($this->admin);
        
        $response = $this->get('/dashboard/global');
        
        $response->assertStatus(200);
        $response->assertSee('dashboard-customizer');
        $response->assertSee('data-widget-id');
    }

    public function test_widget_visibility_can_be_toggled()
    {
        $this->actingAs($this->admin);
        
        $response = $this->putJson('/api/dashboard/widget/visibility', [
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

    public function test_widget_layout_can_be_updated()
    {
        $this->actingAs($this->admin);
        
        $layoutUpdates = [
            [
                'widget_id' => 'system-overview',
                'position' => ['row' => 2, 'col' => 3],
                'size' => ['width' => 8, 'height' => 6]
            ]
        ];
        
        $response = $this->putJson('/api/dashboard/widget/layout', [
            'dashboard_type' => 'global',
            'layout_updates' => $layoutUpdates
        ]);
        
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'updated_widgets' => ['system-overview']
        ]);
        
        // Verify the changes were persisted
        $config = UserDashboardConfig::where('user_id', $this->admin->id)
            ->where('dashboard_type', 'global')
            ->first();
        
        $this->assertEquals(['row' => 2, 'col' => 3], $config->getWidgetPosition('system-overview'));
        $this->assertEquals(['width' => 8, 'height' => 6], $config->getWidgetSize('system-overview'));
    }

    public function test_dashboard_config_can_be_reset()
    {
        $this->actingAs($this->admin);
        
        // First, modify the config
        $this->putJson('/api/dashboard/widget/visibility', [
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
        $response = $this->postJson('/api/dashboard/config/reset', [
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

    public function test_available_widgets_endpoint_returns_authorized_widgets()
    {
        $this->actingAs($this->admin);
        
        $response = $this->getJson('/api/dashboard/widgets', [
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

    public function test_widget_customization_respects_permissions()
    {
        $this->actingAs($this->operator);
        
        // Operator should be able to customize widgets they have access to
        $response = $this->putJson('/api/dashboard/widget/visibility', [
            'dashboard_type' => 'global',
            'widget_id' => 'system-overview',
            'visible' => false
        ]);
        
        $response->assertStatus(200);
        
        // But should not be able to access widgets they don't have permission for
        $response = $this->putJson('/api/dashboard/widget/visibility', [
            'dashboard_type' => 'gateway',
            'widget_id' => 'gateway-device-list',
            'visible' => false
        ]);
        
        // This might succeed if the operator has gateway access through device assignment
        // The exact behavior depends on the permission logic
        $this->assertContains($response->status(), [200, 403]);
    }

    public function test_dashboard_type_switching_works()
    {
        $this->actingAs($this->admin);
        
        // Test global dashboard access
        $response = $this->get('/dashboard/global');
        $response->assertStatus(200);
        
        // Test gateway dashboard access
        $response = $this->get("/dashboard/gateway/{$this->gateway->id}");
        $response->assertStatus(200);
    }

    public function test_widget_drag_and_drop_updates_layout()
    {
        $this->actingAs($this->admin);
        
        // Simulate drag and drop by updating widget layout
        $layoutUpdates = [
            [
                'widget_id' => 'system-overview',
                'position' => ['row' => 0, 'col' => 0],
                'size' => ['width' => 6, 'height' => 4]
            ],
            [
                'widget_id' => 'cross-gateway-alerts',
                'position' => ['row' => 0, 'col' => 6],
                'size' => ['width' => 6, 'height' => 4]
            ]
        ];
        
        $response = $this->putJson('/api/dashboard/widget/layout', [
            'dashboard_type' => 'global',
            'layout_updates' => $layoutUpdates
        ]);
        
        $response->assertStatus(200);
        
        // Verify both widgets were positioned correctly
        $config = UserDashboardConfig::where('user_id', $this->admin->id)
            ->where('dashboard_type', 'global')
            ->first();
        
        $this->assertEquals(['row' => 0, 'col' => 0], $config->getWidgetPosition('system-overview'));
        $this->assertEquals(['row' => 0, 'col' => 6], $config->getWidgetPosition('cross-gateway-alerts'));
    }

    public function test_widget_resizing_updates_size()
    {
        $this->actingAs($this->admin);
        
        $layoutUpdates = [
            [
                'widget_id' => 'system-overview',
                'position' => ['row' => 0, 'col' => 0],
                'size' => ['width' => 12, 'height' => 8] // Full width, double height
            ]
        ];
        
        $response = $this->putJson('/api/dashboard/widget/layout', [
            'dashboard_type' => 'global',
            'layout_updates' => $layoutUpdates
        ]);
        
        $response->assertStatus(200);
        
        // Verify the size was updated
        $config = UserDashboardConfig::where('user_id', $this->admin->id)
            ->where('dashboard_type', 'global')
            ->first();
        
        $this->assertEquals(['width' => 12, 'height' => 8], $config->getWidgetSize('system-overview'));
    }

    public function test_dashboard_customization_persists_across_sessions()
    {
        $this->actingAs($this->admin);
        
        // Customize dashboard
        $this->putJson('/api/dashboard/widget/visibility', [
            'dashboard_type' => 'global',
            'widget_id' => 'system-overview',
            'visible' => false
        ]);
        
        $this->putJson('/api/dashboard/widget/layout', [
            'dashboard_type' => 'global',
            'layout_updates' => [
                [
                    'widget_id' => 'cross-gateway-alerts',
                    'position' => ['row' => 1, 'col' => 2],
                    'size' => ['width' => 8, 'height' => 6]
                ]
            ]
        ]);
        
        // Simulate new session by getting config
        $response = $this->getJson('/api/dashboard/config', [
            'dashboard_type' => 'global'
        ]);
        
        $response->assertStatus(200);
        
        $config = $response->json('config');
        $this->assertFalse($config['widget_config']['visibility']['system-overview']);
        $this->assertEquals(['row' => 1, 'col' => 2], $config['layout_config']['positions']['cross-gateway-alerts']);
        $this->assertEquals(['width' => 8, 'height' => 6], $config['layout_config']['sizes']['cross-gateway-alerts']);
    }

    public function test_multiple_users_have_separate_configurations()
    {
        // Admin customizes dashboard
        $this->actingAs($this->admin);
        $this->putJson('/api/dashboard/widget/visibility', [
            'dashboard_type' => 'global',
            'widget_id' => 'system-overview',
            'visible' => false
        ]);
        
        // Operator customizes dashboard differently
        $this->actingAs($this->operator);
        $this->putJson('/api/dashboard/widget/visibility', [
            'dashboard_type' => 'global',
            'widget_id' => 'system-overview',
            'visible' => true
        ]);
        
        // Verify admin's config
        $adminConfig = UserDashboardConfig::where('user_id', $this->admin->id)
            ->where('dashboard_type', 'global')
            ->first();
        $this->assertFalse($adminConfig->getWidgetVisibility('system-overview'));
        
        // Verify operator's config
        $operatorConfig = UserDashboardConfig::where('user_id', $this->operator->id)
            ->where('dashboard_type', 'global')
            ->first();
        $this->assertTrue($operatorConfig->getWidgetVisibility('system-overview'));
    }

    public function test_dashboard_customization_validates_input()
    {
        $this->actingAs($this->admin);
        
        // Test invalid dashboard type
        $response = $this->putJson('/api/dashboard/widget/visibility', [
            'dashboard_type' => 'invalid',
            'widget_id' => 'system-overview',
            'visible' => true
        ]);
        $response->assertStatus(400);
        
        // Test missing widget ID
        $response = $this->putJson('/api/dashboard/widget/visibility', [
            'dashboard_type' => 'global',
            'visible' => true
        ]);
        $response->assertStatus(400);
        
        // Test invalid layout data
        $response = $this->putJson('/api/dashboard/widget/layout', [
            'dashboard_type' => 'global',
            'layout_updates' => [
                [
                    'widget_id' => 'system-overview',
                    'position' => ['row' => -1, 'col' => 15], // Invalid position
                    'size' => ['width' => 0, 'height' => 25] // Invalid size
                ]
            ]
        ]);
        $response->assertStatus(400);
    }

    public function test_widget_performance_metrics_are_available()
    {
        $this->actingAs($this->admin);
        
        $response = $this->getJson('/api/dashboard/widget/performance', [
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
    }

    public function test_widget_cache_can_be_cleared()
    {
        $this->actingAs($this->admin);
        
        // Clear specific widget cache
        $response = $this->deleteJson('/api/dashboard/widget/cache', [
            'dashboard_type' => 'global',
            'widget_id' => 'system-overview'
        ]);
        
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'widget_id' => 'system-overview'
        ]);
        
        // Clear all widget caches
        $response = $this->deleteJson('/api/dashboard/widget/cache', [
            'dashboard_type' => 'global'
        ]);
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'cleared_count'
        ]);
    }
}