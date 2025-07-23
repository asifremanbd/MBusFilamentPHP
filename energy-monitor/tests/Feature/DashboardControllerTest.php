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
use App\Models\DashboardAccessLog;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test users
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->operator = User::factory()->create(['role' => 'operator']);
        
        // Create test gateway and devices
        $this->gateway = Gateway::factory()->create();
        $this->device1 = Device::factory()->create(['gateway_id' => $this->gateway->id]);
        $this->device2 = Device::factory()->create(['gateway_id' => $this->gateway->id]);
        
        // Assign device1 to operator
        UserDeviceAssignment::create([
            'user_id' => $this->operator->id,
            'device_id' => $this->device1->id,
            'assigned_at' => now(),
        ]);
    }

    public function test_admin_can_access_global_dashboard()
    {
        $response = $this->actingAs($this->admin)
            ->get('/dashboard/global');

        $response->assertStatus(200);
        $response->assertViewIs('dashboard.global');
        $response->assertViewHas(['gateways', 'widgets', 'config', 'user', 'dashboard_type']);
        
        // Check that access is logged
        $this->assertDatabaseHas('dashboard_access_logs', [
            'user_id' => $this->admin->id,
            'dashboard_type' => 'global',
            'access_granted' => true,
        ]);
    }

    public function test_operator_can_access_global_dashboard_with_limited_data()
    {
        $response = $this->actingAs($this->operator)
            ->get('/dashboard/global');

        $response->assertStatus(200);
        $response->assertViewIs('dashboard.global');
        
        // Operator should only see gateways they have device access to
        $gateways = $response->viewData('gateways');
        $this->assertCount(1, $gateways);
        $this->assertEquals($this->gateway->id, $gateways->first()->id);
    }

    public function test_admin_can_access_gateway_dashboard()
    {
        $response = $this->actingAs($this->admin)
            ->get("/dashboard/gateway/{$this->gateway->id}");

        $response->assertStatus(200);
        $response->assertViewIs('dashboard.gateway');
        $response->assertViewHas(['gateway', 'devices', 'widgets', 'config', 'user', 'dashboard_type', 'gateway_id']);
        
        // Check that access is logged
        $this->assertDatabaseHas('dashboard_access_logs', [
            'user_id' => $this->admin->id,
            'dashboard_type' => 'gateway',
            'gateway_id' => $this->gateway->id,
            'access_granted' => true,
        ]);
    }

    public function test_operator_can_access_authorized_gateway_dashboard()
    {
        $response = $this->actingAs($this->operator)
            ->get("/dashboard/gateway/{$this->gateway->id}");

        $response->assertStatus(200);
        $response->assertViewIs('dashboard.gateway');
        
        // Operator should only see devices they have access to
        $devices = $response->viewData('devices');
        $this->assertCount(1, $devices);
        $this->assertEquals($this->device1->id, $devices->first()->id);
    }

    public function test_operator_cannot_access_unauthorized_gateway_dashboard()
    {
        $unauthorizedGateway = Gateway::factory()->create();
        
        $response = $this->actingAs($this->operator)
            ->get("/dashboard/gateway/{$unauthorizedGateway->id}");

        $response->assertStatus(403);
        $response->assertViewIs('dashboard.unauthorized');
        
        // Check that unauthorized access is logged
        $this->assertDatabaseHas('dashboard_access_logs', [
            'user_id' => $this->operator->id,
            'dashboard_type' => 'gateway',
            'gateway_id' => $unauthorizedGateway->id,
            'access_granted' => false,
        ]);
    }

    public function test_gateway_dashboard_returns_404_for_nonexistent_gateway()
    {
        $response = $this->actingAs($this->admin)
            ->get('/dashboard/gateway/99999');

        $response->assertStatus(404);
        $response->assertViewIs('dashboard.not-found');
    }

    public function test_get_dashboard_data_returns_json_for_global_dashboard()
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/dashboard/data', [
                'dashboard_type' => 'global'
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'dashboard_type',
            'widgets',
            'timestamp'
        ]);
        
        $this->assertTrue($response->json('success'));
        $this->assertEquals('global', $response->json('dashboard_type'));
    }

    public function test_get_dashboard_data_returns_json_for_gateway_dashboard()
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/dashboard/data', [
                'dashboard_type' => 'gateway',
                'gateway_id' => $this->gateway->id
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'dashboard_type',
            'gateway_id',
            'widgets',
            'timestamp'
        ]);
        
        $this->assertTrue($response->json('success'));
        $this->assertEquals('gateway', $response->json('dashboard_type'));
        $this->assertEquals($this->gateway->id, $response->json('gateway_id'));
    }

    public function test_get_dashboard_data_requires_gateway_id_for_gateway_dashboard()
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/dashboard/data', [
                'dashboard_type' => 'gateway'
            ]);

        $response->assertStatus(400);
        $response->assertJson([
            'error' => 'Gateway ID is required for gateway dashboard'
        ]);
    }

    public function test_get_dashboard_data_validates_dashboard_type()
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/dashboard/data', [
                'dashboard_type' => 'invalid'
            ]);

        $response->assertStatus(400);
        $response->assertJson([
            'error' => 'Invalid dashboard type'
        ]);
    }

    public function test_get_available_gateways_returns_authorized_gateways()
    {
        $response = $this->actingAs($this->operator)
            ->getJson('/api/dashboard/gateways');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'gateways' => [
                '*' => [
                    'id',
                    'name',
                    'location',
                    'device_count',
                    'status'
                ]
            ],
            'total_count'
        ]);
        
        $this->assertTrue($response->json('success'));
        $this->assertEquals(1, $response->json('total_count'));
        $this->assertEquals($this->gateway->id, $response->json('gateways.0.id'));
    }

    public function test_admin_sees_all_gateways()
    {
        $gateway2 = Gateway::factory()->create();
        
        $response = $this->actingAs($this->admin)
            ->getJson('/api/dashboard/gateways');

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('total_count'));
    }

    public function test_unauthenticated_user_cannot_access_dashboard()
    {
        $response = $this->get('/dashboard/global');
        $response->assertRedirect('/login');
        
        $response = $this->get("/dashboard/gateway/{$this->gateway->id}");
        $response->assertRedirect('/login');
    }

    public function test_dashboard_handles_exceptions_gracefully()
    {
        // Mock a service to throw an exception
        $this->mock(\App\Services\WidgetFactory::class, function ($mock) {
            $mock->shouldReceive('getAuthorizedWidgets')
                ->andThrow(new \Exception('Test exception'));
        });

        $response = $this->actingAs($this->admin)
            ->get('/dashboard/global');

        $response->assertStatus(500);
        $response->assertViewIs('dashboard.error');
    }

    public function test_dashboard_creates_default_config_for_new_user()
    {
        $newUser = User::factory()->create(['role' => 'admin']);
        
        $this->assertDatabaseMissing('user_dashboard_configs', [
            'user_id' => $newUser->id,
            'dashboard_type' => 'global'
        ]);

        $response = $this->actingAs($newUser)
            ->get('/dashboard/global');

        $response->assertStatus(200);
        
        // Config should be created automatically
        $this->assertDatabaseHas('user_dashboard_configs', [
            'user_id' => $newUser->id,
            'dashboard_type' => 'global'
        ]);
    }

    public function test_dashboard_respects_user_widget_visibility_settings()
    {
        // Create custom dashboard config with some widgets hidden
        UserDashboardConfig::create([
            'user_id' => $this->admin->id,
            'dashboard_type' => 'global',
            'widget_config' => [
                'visibility' => [
                    'system-overview' => true,
                    'cross-gateway-alerts' => false,
                    'top-consuming-gateways' => true,
                    'system-health' => false,
                ]
            ],
            'layout_config' => [
                'positions' => [],
                'sizes' => []
            ]
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/dashboard/global');

        $response->assertStatus(200);
        
        $config = $response->viewData('config');
        $this->assertTrue($config->getWidgetVisibility('system-overview'));
        $this->assertFalse($config->getWidgetVisibility('cross-gateway-alerts'));
        $this->assertTrue($config->getWidgetVisibility('top-consuming-gateways'));
        $this->assertFalse($config->getWidgetVisibility('system-health'));
    }

    public function test_dashboard_logs_access_with_correct_metadata()
    {
        $response = $this->actingAs($this->admin)
            ->get('/dashboard/global');

        $response->assertStatus(200);
        
        $log = DashboardAccessLog::where('user_id', $this->admin->id)->first();
        $this->assertNotNull($log);
        $this->assertEquals('global', $log->dashboard_type);
        $this->assertTrue($log->access_granted);
        $this->assertNotNull($log->ip_address);
        $this->assertNotNull($log->user_agent);
        $this->assertNotNull($log->accessed_at);
    }

    public function test_gateway_dashboard_authorization_uses_policy()
    {
        // This test would require setting up a proper Gateway policy
        // For now, we'll test the basic authorization flow
        
        $response = $this->actingAs($this->operator)
            ->get("/dashboard/gateway/{$this->gateway->id}");

        // Operator should be able to access gateway with assigned devices
        $response->assertStatus(200);
        
        $unauthorizedGateway = Gateway::factory()->create();
        
        $response = $this->actingAs($this->operator)
            ->get("/dashboard/gateway/{$unauthorizedGateway->id}");

        // Operator should not be able to access gateway without assigned devices
        $response->assertStatus(403);
    }

    public function test_dashboard_provides_suggested_actions_for_unauthorized_access()
    {
        $unauthorizedGateway = Gateway::factory()->create();
        
        $response = $this->actingAs($this->operator)
            ->get("/dashboard/gateway/{$unauthorizedGateway->id}");

        $response->assertStatus(403);
        $response->assertViewIs('dashboard.unauthorized');
        $response->assertViewHas('suggested_action');
        
        $suggestedAction = $response->viewData('suggested_action');
        $this->assertIsArray($suggestedAction);
        $this->assertArrayHasKey('action', $suggestedAction);
        $this->assertArrayHasKey('message', $suggestedAction);
    }

    public function test_dashboard_data_api_respects_permissions()
    {
        $response = $this->actingAs($this->operator)
            ->getJson('/api/dashboard/data', [
                'dashboard_type' => 'gateway',
                'gateway_id' => $this->gateway->id
            ]);

        $response->assertStatus(200);
        
        $unauthorizedGateway = Gateway::factory()->create();
        
        $response = $this->actingAs($this->operator)
            ->getJson('/api/dashboard/data', [
                'dashboard_type' => 'gateway',
                'gateway_id' => $unauthorizedGateway->id
            ]);

        $response->assertStatus(403);
    }
}