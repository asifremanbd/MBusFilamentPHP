<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Gateway;
use App\Models\Device;
use App\Models\UserGatewayAssignment;
use App\Models\UserDeviceAssignment;
use App\Models\UserDashboardConfig;
use App\Services\UserPermissionService;
use App\Services\DashboardConfigService;
use App\Services\WidgetFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Cache;

class ComprehensivePermissionTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $adminUser;
    protected User $operatorUser;
    protected User $restrictedUser;
    protected Gateway $gateway1;
    protected Gateway $gateway2;
    protected Device $device1;
    protected Device $device2;
    protected Device $device3;

    protected function setUp(): void
    {
        parent::setUp();

        // Create users with different roles
        $this->adminUser = User::factory()->create(['role' => 'admin']);
        $this->operatorUser = User::factory()->create(['role' => 'operator']);
        $this->restrictedUser = User::factory()->create(['role' => 'operator']);

        // Create gateways
        $this->gateway1 = Gateway::factory()->create(['name' => 'Gateway 1']);
        $this->gateway2 = Gateway::factory()->create(['name' => 'Gateway 2']);

        // Create devices
        $this->device1 = Device::factory()->create(['gateway_id' => $this->gateway1->id]);
        $this->device2 = Device::factory()->create(['gateway_id' => $this->gateway1->id]);
        $this->device3 = Device::factory()->create(['gateway_id' => $this->gateway2->id]);

        // Set up permissions
        $this->setupPermissions();
    }

    protected function setupPermissions(): void
    {
        // Admin has access to all gateways and devices (implicit)
        
        // Operator has access to both gateways and all devices
        UserGatewayAssignment::create([
            'user_id' => $this->operatorUser->id,
            'gateway_id' => $this->gateway1->id
        ]);
        UserGatewayAssignment::create([
            'user_id' => $this->operatorUser->id,
            'gateway_id' => $this->gateway2->id
        ]);

        UserDeviceAssignment::create([
            'user_id' => $this->operatorUser->id,
            'device_id' => $this->device1->id
        ]);
        UserDeviceAssignment::create([
            'user_id' => $this->operatorUser->id,
            'device_id' => $this->device2->id
        ]);
        UserDeviceAssignment::create([
            'user_id' => $this->operatorUser->id,
            'device_id' => $this->device3->id
        ]);

        // Restricted user has access to only gateway1 and device1
        UserGatewayAssignment::create([
            'user_id' => $this->restrictedUser->id,
            'gateway_id' => $this->gateway1->id
        ]);
        UserDeviceAssignment::create([
            'user_id' => $this->restrictedUser->id,
            'device_id' => $this->device1->id
        ]);
    }

    /** @test */
    public function admin_user_has_access_to_all_resources()
    {
        $permissionService = app(UserPermissionService::class);

        // Admin should have access to all gateways
        $authorizedGateways = $permissionService->getAuthorizedGateways($this->adminUser->id);
        $this->assertCount(2, $authorizedGateways);

        // Admin should have access to all devices
        $authorizedDevices = $permissionService->getAuthorizedDevices($this->adminUser->id);
        $this->assertCount(3, $authorizedDevices);

        // Admin should be able to access all widgets
        $this->assertTrue($permissionService->canAccessWidget($this->adminUser, 'system-overview', ['dashboard_type' => 'global']));
        $this->assertTrue($permissionService->canAccessWidget($this->adminUser, 'real-time-readings', ['dashboard_type' => 'gateway', 'gateway_id' => $this->gateway1->id]));
    }

    /** @test */
    public function operator_user_has_correct_permissions()
    {
        $permissionService = app(UserPermissionService::class);

        // Operator should have access to assigned gateways
        $authorizedGateways = $permissionService->getAuthorizedGateways($this->operatorUser->id);
        $this->assertCount(2, $authorizedGateways);
        $this->assertTrue($authorizedGateways->contains('id', $this->gateway1->id));
        $this->assertTrue($authorizedGateways->contains('id', $this->gateway2->id));

        // Operator should have access to assigned devices
        $authorizedDevices = $permissionService->getAuthorizedDevices($this->operatorUser->id);
        $this->assertCount(3, $authorizedDevices);
    }

    /** @test */
    public function restricted_user_has_limited_permissions()
    {
        $permissionService = app(UserPermissionService::class);

        // Restricted user should have access to only gateway1
        $authorizedGateways = $permissionService->getAuthorizedGateways($this->restrictedUser->id);
        $this->assertCount(1, $authorizedGateways);
        $this->assertTrue($authorizedGateways->contains('id', $this->gateway1->id));
        $this->assertFalse($authorizedGateways->contains('id', $this->gateway2->id));

        // Restricted user should have access to only device1
        $authorizedDevices = $permissionService->getAuthorizedDevices($this->restrictedUser->id);
        $this->assertCount(1, $authorizedDevices);
        $this->assertTrue($authorizedDevices->contains('id', $this->device1->id));
    }

    /** @test */
    public function dashboard_config_respects_user_permissions()
    {
        $configService = app(DashboardConfigService::class);

        // Test global dashboard config for restricted user
        $config = $configService->getUserDashboardConfig($this->restrictedUser, 'global');
        $this->assertNotNull($config);
        $this->assertEquals('global', $config->dashboard_type);

        // Test gateway dashboard config for authorized gateway
        $config = $configService->getUserDashboardConfig($this->restrictedUser, 'gateway', $this->gateway1->id);
        $this->assertNotNull($config);
        $this->assertEquals('gateway', $config->dashboard_type);
    }

    /** @test */
    public function widget_factory_filters_by_permissions()
    {
        $widgetFactory = app(WidgetFactory::class);

        // Admin should get all widgets
        $adminWidgets = $widgetFactory->getAuthorizedWidgets($this->adminUser, 'global');
        $this->assertNotEmpty($adminWidgets);

        // Restricted user should get filtered widgets
        $restrictedWidgets = $widgetFactory->getAuthorizedWidgets($this->restrictedUser, 'global');
        $this->assertNotEmpty($restrictedWidgets);

        // Gateway-specific widgets should respect gateway permissions
        $gatewayWidgets = $widgetFactory->getAuthorizedWidgets($this->restrictedUser, 'gateway', $this->gateway1->id);
        $this->assertNotEmpty($gatewayWidgets);
    }

    /** @test */
    public function permission_caching_works_correctly()
    {
        $permissionService = app(UserPermissionService::class);

        // Clear cache
        Cache::flush();

        // First call should hit database
        $start = microtime(true);
        $gateways1 = $permissionService->getAuthorizedGateways($this->operatorUser->id);
        $time1 = microtime(true) - $start;

        // Second call should use cache
        $start = microtime(true);
        $gateways2 = $permissionService->getAuthorizedGateways($this->operatorUser->id);
        $time2 = microtime(true) - $start;

        // Cache should be faster
        $this->assertLessThan($time1, $time2);
        $this->assertEquals($gateways1->count(), $gateways2->count());
    }

    /** @test */
    public function permission_cache_invalidates_on_assignment_changes()
    {
        $permissionService = app(UserPermissionService::class);

        // Get initial permissions
        $initialGateways = $permissionService->getAuthorizedGateways($this->restrictedUser->id);
        $this->assertCount(1, $initialGateways);

        // Add new gateway assignment
        UserGatewayAssignment::create([
            'user_id' => $this->restrictedUser->id,
            'gateway_id' => $this->gateway2->id
        ]);

        // Permissions should be updated
        $updatedGateways = $permissionService->getAuthorizedGateways($this->restrictedUser->id);
        $this->assertCount(2, $updatedGateways);
    }

    /** @test */
    public function dashboard_api_endpoints_respect_permissions()
    {
        // Test global dashboard access
        $response = $this->actingAs($this->restrictedUser)
            ->get(route('dashboard.global'));
        $response->assertStatus(200);

        // Test authorized gateway access
        $response = $this->actingAs($this->restrictedUser)
            ->get(route('dashboard.gateway', ['gateway' => $this->gateway1->id]));
        $response->assertStatus(200);

        // Test unauthorized gateway access
        $response = $this->actingAs($this->restrictedUser)
            ->get(route('dashboard.gateway', ['gateway' => $this->gateway2->id]));
        $response->assertStatus(403);
    }

    /** @test */
    public function api_endpoints_return_filtered_data()
    {
        // Test gateways API
        $response = $this->actingAs($this->restrictedUser)
            ->getJson('/api/dashboard/gateways');

        $response->assertStatus(200);
        $gateways = $response->json('gateways');
        $this->assertCount(1, $gateways);
        $this->assertEquals($this->gateway1->id, $gateways[0]['id']);

        // Test with operator user
        $response = $this->actingAs($this->operatorUser)
            ->getJson('/api/dashboard/gateways');

        $response->assertStatus(200);
        $gateways = $response->json('gateways');
        $this->assertCount(2, $gateways);
    }

    /** @test */
    public function widget_configuration_respects_permissions()
    {
        // Create dashboard config
        $config = UserDashboardConfig::create([
            'user_id' => $this->restrictedUser->id,
            'dashboard_type' => 'global',
            'widget_config' => ['system-overview' => ['visible' => true]],
            'layout_config' => ['system-overview' => ['position' => ['row' => 0, 'col' => 0]]]
        ]);

        // Test widget visibility update
        $response = $this->actingAs($this->restrictedUser)
            ->postJson('/api/dashboard/config/widget/visibility', [
                'dashboard_type' => 'global',
                'widget_id' => 'system-overview',
                'visible' => false
            ]);

        $response->assertStatus(200);

        // Verify config was updated
        $config->refresh();
        $this->assertFalse($config->widget_config['system-overview']['visible']);
    }

    /** @test */
    public function bulk_permission_operations_work_correctly()
    {
        // Test bulk gateway assignment
        $newGateways = Gateway::factory()->count(3)->create();
        
        foreach ($newGateways as $gateway) {
            UserGatewayAssignment::create([
                'user_id' => $this->restrictedUser->id,
                'gateway_id' => $gateway->id
            ]);
        }

        $permissionService = app(UserPermissionService::class);
        $authorizedGateways = $permissionService->getAuthorizedGateways($this->restrictedUser->id);
        
        // Should now have 4 gateways (1 original + 3 new)
        $this->assertCount(4, $authorizedGateways);
    }

    /** @test */
    public function error_handling_works_for_invalid_permissions()
    {
        // Test accessing non-existent gateway
        $response = $this->actingAs($this->restrictedUser)
            ->get(route('dashboard.gateway', ['gateway' => 99999]));
        $response->assertStatus(404);

        // Test API with invalid widget
        $response = $this->actingAs($this->restrictedUser)
            ->postJson('/api/dashboard/config/widget/visibility', [
                'dashboard_type' => 'global',
                'widget_id' => 'non-existent-widget',
                'visible' => false
            ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function session_permission_validation_works()
    {
        // Login as restricted user
        $this->actingAs($this->restrictedUser);

        // Access authorized resource
        $response = $this->get(route('dashboard.gateway', ['gateway' => $this->gateway1->id]));
        $response->assertStatus(200);

        // Remove permission while session is active
        UserGatewayAssignment::where('user_id', $this->restrictedUser->id)
            ->where('gateway_id', $this->gateway1->id)
            ->delete();

        // Should now be denied access
        $response = $this->get(route('dashboard.gateway', ['gateway' => $this->gateway1->id]));
        $response->assertStatus(403);
    }

    /** @test */
    public function concurrent_user_permissions_dont_interfere()
    {
        $permissionService = app(UserPermissionService::class);

        // Get permissions for both users simultaneously
        $restrictedGateways = $permissionService->getAuthorizedGateways($this->restrictedUser->id);
        $operatorGateways = $permissionService->getAuthorizedGateways($this->operatorUser->id);

        // Verify they have different permissions
        $this->assertCount(1, $restrictedGateways);
        $this->assertCount(2, $operatorGateways);

        // Verify cache keys are separate
        $this->assertNotEquals(
            $restrictedGateways->pluck('id')->toArray(),
            $operatorGateways->pluck('id')->toArray()
        );
    }
}
"