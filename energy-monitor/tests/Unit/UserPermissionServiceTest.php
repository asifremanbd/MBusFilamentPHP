<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Models\User;
use App\Models\Device;
use App\Models\Gateway;
use App\Models\UserDeviceAssignment;
use App\Models\UserGatewayAssignment;
use App\Services\UserPermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesApplication;

class UserPermissionServiceTest extends TestCase
{
    use CreatesApplication, RefreshDatabase;

    protected UserPermissionService $permissionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createApplication();
        $this->permissionService = new UserPermissionService();
    }

    public function test_admin_gets_all_gateways()
    {
        $adminUser = User::factory()->create(['role' => 'admin']);
        $gateway1 = Gateway::factory()->create();
        $gateway2 = Gateway::factory()->create();

        $authorizedGateways = $this->permissionService->getAuthorizedGateways($adminUser);

        $this->assertCount(2, $authorizedGateways);
        $this->assertTrue($authorizedGateways->contains($gateway1));
        $this->assertTrue($authorizedGateways->contains($gateway2));
    }

    public function test_regular_user_gets_only_gateways_with_assigned_devices()
    {
        $user = User::factory()->create(['role' => 'operator']);
        $gateway1 = Gateway::factory()->create();
        $gateway2 = Gateway::factory()->create();
        $gateway3 = Gateway::factory()->create();
        
        $device1 = Device::factory()->create(['gateway_id' => $gateway1->id]);
        $device2 = Device::factory()->create(['gateway_id' => $gateway2->id]);
        $device3 = Device::factory()->create(['gateway_id' => $gateway3->id]);

        // Assign devices from gateway1 and gateway2 to user
        UserDeviceAssignment::create([
            'user_id' => $user->id,
            'device_id' => $device1->id,
            'assigned_at' => now(),
        ]);
        UserDeviceAssignment::create([
            'user_id' => $user->id,
            'device_id' => $device2->id,
            'assigned_at' => now(),
        ]);

        $authorizedGateways = $this->permissionService->getAuthorizedGateways($user);

        $this->assertCount(2, $authorizedGateways);
        $this->assertTrue($authorizedGateways->contains($gateway1));
        $this->assertTrue($authorizedGateways->contains($gateway2));
        $this->assertFalse($authorizedGateways->contains($gateway3));
    }

    public function test_get_authorized_devices_for_admin()
    {
        $adminUser = User::factory()->create(['role' => 'admin']);
        $device1 = Device::factory()->create();
        $device2 = Device::factory()->create();

        $authorizedDevices = $this->permissionService->getAuthorizedDevices($adminUser);

        $this->assertCount(2, $authorizedDevices);
        $this->assertTrue($authorizedDevices->contains($device1));
        $this->assertTrue($authorizedDevices->contains($device2));
    }

    public function test_get_authorized_devices_for_regular_user()
    {
        $user = User::factory()->create(['role' => 'operator']);
        $device1 = Device::factory()->create();
        $device2 = Device::factory()->create();
        $device3 = Device::factory()->create();

        UserDeviceAssignment::create([
            'user_id' => $user->id,
            'device_id' => $device1->id,
            'assigned_at' => now(),
        ]);
        UserDeviceAssignment::create([
            'user_id' => $user->id,
            'device_id' => $device2->id,
            'assigned_at' => now(),
        ]);

        $authorizedDevices = $this->permissionService->getAuthorizedDevices($user);

        $this->assertCount(2, $authorizedDevices);
        $this->assertTrue($authorizedDevices->contains($device1));
        $this->assertTrue($authorizedDevices->contains($device2));
        $this->assertFalse($authorizedDevices->contains($device3));
    }

    public function test_get_authorized_devices_filtered_by_gateway()
    {
        $user = User::factory()->create(['role' => 'operator']);
        $gateway1 = Gateway::factory()->create();
        $gateway2 = Gateway::factory()->create();
        
        $device1 = Device::factory()->create(['gateway_id' => $gateway1->id]);
        $device2 = Device::factory()->create(['gateway_id' => $gateway1->id]);
        $device3 = Device::factory()->create(['gateway_id' => $gateway2->id]);

        UserDeviceAssignment::create([
            'user_id' => $user->id,
            'device_id' => $device1->id,
            'assigned_at' => now(),
        ]);
        UserDeviceAssignment::create([
            'user_id' => $user->id,
            'device_id' => $device2->id,
            'assigned_at' => now(),
        ]);
        UserDeviceAssignment::create([
            'user_id' => $user->id,
            'device_id' => $device3->id,
            'assigned_at' => now(),
        ]);

        $authorizedDevices = $this->permissionService->getAuthorizedDevices($user, $gateway1->id);

        $this->assertCount(2, $authorizedDevices);
        $this->assertTrue($authorizedDevices->contains($device1));
        $this->assertTrue($authorizedDevices->contains($device2));
        $this->assertFalse($authorizedDevices->contains($device3));
    }

    public function test_can_access_widget_system_overview()
    {
        $adminUser = User::factory()->create(['role' => 'admin']);
        $regularUser = User::factory()->create(['role' => 'operator']);
        $device = Device::factory()->create();

        UserDeviceAssignment::create([
            'user_id' => $regularUser->id,
            'device_id' => $device->id,
            'assigned_at' => now(),
        ]);

        $this->assertTrue($this->permissionService->canAccessWidget($adminUser, 'system-overview'));
        $this->assertTrue($this->permissionService->canAccessWidget($regularUser, 'system-overview'));
    }

    public function test_can_access_widget_gateway_stats()
    {
        $user = User::factory()->create(['role' => 'operator']);
        $gateway1 = Gateway::factory()->create();
        $gateway2 = Gateway::factory()->create();

        UserGatewayAssignment::create([
            'user_id' => $user->id,
            'gateway_id' => $gateway1->id,
            'assigned_at' => now(),
        ]);

        $this->assertTrue($this->permissionService->canAccessWidget($user, 'gateway-stats', ['gateway_id' => $gateway1->id]));
        $this->assertFalse($this->permissionService->canAccessWidget($user, 'gateway-stats', ['gateway_id' => $gateway2->id]));
    }

    public function test_can_access_widget_device_status()
    {
        $user = User::factory()->create(['role' => 'operator']);
        $device1 = Device::factory()->create();
        $device2 = Device::factory()->create();
        $device3 = Device::factory()->create();

        UserDeviceAssignment::create([
            'user_id' => $user->id,
            'device_id' => $device1->id,
            'assigned_at' => now(),
        ]);
        UserDeviceAssignment::create([
            'user_id' => $user->id,
            'device_id' => $device2->id,
            'assigned_at' => now(),
        ]);

        $this->assertTrue($this->permissionService->canAccessWidget($user, 'device-status', ['device_ids' => [$device1->id, $device2->id]]));
        $this->assertFalse($this->permissionService->canAccessWidget($user, 'device-status', ['device_ids' => [$device1->id, $device3->id]]));
        $this->assertTrue($this->permissionService->canAccessWidget($user, 'device-status', [])); // No specific devices required
    }

    public function test_filter_authorized_devices()
    {
        $user = User::factory()->create(['role' => 'operator']);
        $device1 = Device::factory()->create();
        $device2 = Device::factory()->create();
        $device3 = Device::factory()->create();

        UserDeviceAssignment::create([
            'user_id' => $user->id,
            'device_id' => $device1->id,
            'assigned_at' => now(),
        ]);
        UserDeviceAssignment::create([
            'user_id' => $user->id,
            'device_id' => $device2->id,
            'assigned_at' => now(),
        ]);

        $allDevices = collect([$device1, $device2, $device3]);
        $filteredDevices = $this->permissionService->filterAuthorizedDevices($user, $allDevices);

        $this->assertCount(2, $filteredDevices);
        $this->assertTrue($filteredDevices->contains($device1));
        $this->assertTrue($filteredDevices->contains($device2));
        $this->assertFalse($filteredDevices->contains($device3));
    }

    public function test_has_any_device_access()
    {
        $adminUser = User::factory()->create(['role' => 'admin']);
        $userWithDevices = User::factory()->create(['role' => 'operator']);
        $userWithoutDevices = User::factory()->create(['role' => 'operator']);
        $device = Device::factory()->create();

        UserDeviceAssignment::create([
            'user_id' => $userWithDevices->id,
            'device_id' => $device->id,
            'assigned_at' => now(),
        ]);

        $this->assertTrue($this->permissionService->hasAnyDeviceAccess($adminUser));
        $this->assertTrue($this->permissionService->hasAnyDeviceAccess($userWithDevices));
        $this->assertFalse($this->permissionService->hasAnyDeviceAccess($userWithoutDevices));
    }

    public function test_has_any_gateway_access()
    {
        $adminUser = User::factory()->create(['role' => 'admin']);
        $userWithGatewayAccess = User::factory()->create(['role' => 'operator']);
        $userWithoutGatewayAccess = User::factory()->create(['role' => 'operator']);
        
        $gateway = Gateway::factory()->create();
        $device = Device::factory()->create(['gateway_id' => $gateway->id]);

        UserDeviceAssignment::create([
            'user_id' => $userWithGatewayAccess->id,
            'device_id' => $device->id,
            'assigned_at' => now(),
        ]);

        $this->assertTrue($this->permissionService->hasAnyGatewayAccess($adminUser));
        $this->assertTrue($this->permissionService->hasAnyGatewayAccess($userWithGatewayAccess));
        $this->assertFalse($this->permissionService->hasAnyGatewayAccess($userWithoutGatewayAccess));
    }
}