<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Models\User;
use App\Models\Device;
use App\Models\Gateway;
use App\Models\UserDeviceAssignment;
use App\Models\UserGatewayAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesApplication;

class UserPermissionTest extends TestCase
{
    use CreatesApplication, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createApplication();
    }

    public function test_admin_user_can_access_all_devices()
    {
        $adminUser = User::factory()->create(['role' => 'admin']);
        $device1 = Device::factory()->create();
        $device2 = Device::factory()->create();

        $deviceIds = $adminUser->getAssignedDeviceIds();

        $this->assertContains($device1->id, $deviceIds);
        $this->assertContains($device2->id, $deviceIds);
    }

    public function test_regular_user_gets_only_assigned_device_ids()
    {
        $user = User::factory()->create(['role' => 'operator']);
        $device1 = Device::factory()->create();
        $device2 = Device::factory()->create();
        $device3 = Device::factory()->create();

        // Assign only device1 and device2 to user
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

        $deviceIds = $user->getAssignedDeviceIds();

        $this->assertContains($device1->id, $deviceIds);
        $this->assertContains($device2->id, $deviceIds);
        $this->assertNotContains($device3->id, $deviceIds);
        $this->assertCount(2, $deviceIds);
    }

    public function test_admin_user_can_access_all_gateways()
    {
        $adminUser = User::factory()->create(['role' => 'admin']);
        $gateway1 = Gateway::factory()->create();
        $gateway2 = Gateway::factory()->create();

        $gatewayIds = $adminUser->getAssignedGatewayIds();

        $this->assertContains($gateway1->id, $gatewayIds);
        $this->assertContains($gateway2->id, $gatewayIds);
    }

    public function test_regular_user_gets_only_assigned_gateway_ids()
    {
        $user = User::factory()->create(['role' => 'operator']);
        $gateway1 = Gateway::factory()->create();
        $gateway2 = Gateway::factory()->create();
        $gateway3 = Gateway::factory()->create();

        // Assign only gateway1 and gateway2 to user
        UserGatewayAssignment::create([
            'user_id' => $user->id,
            'gateway_id' => $gateway1->id,
            'assigned_at' => now(),
        ]);
        UserGatewayAssignment::create([
            'user_id' => $user->id,
            'gateway_id' => $gateway2->id,
            'assigned_at' => now(),
        ]);

        $gatewayIds = $user->getAssignedGatewayIds();

        $this->assertContains($gateway1->id, $gatewayIds);
        $this->assertContains($gateway2->id, $gatewayIds);
        $this->assertNotContains($gateway3->id, $gatewayIds);
        $this->assertCount(2, $gatewayIds);
    }

    public function test_can_access_device_method()
    {
        $user = User::factory()->create(['role' => 'operator']);
        $device1 = Device::factory()->create();
        $device2 = Device::factory()->create();

        UserDeviceAssignment::create([
            'user_id' => $user->id,
            'device_id' => $device1->id,
            'assigned_at' => now(),
        ]);

        $this->assertTrue($user->canAccessDevice($device1->id));
        $this->assertFalse($user->canAccessDevice($device2->id));
    }

    public function test_admin_can_access_any_device()
    {
        $adminUser = User::factory()->create(['role' => 'admin']);
        $device = Device::factory()->create();

        $this->assertTrue($adminUser->canAccessDevice($device->id));
    }

    public function test_can_access_gateway_method()
    {
        $user = User::factory()->create(['role' => 'operator']);
        $gateway1 = Gateway::factory()->create();
        $gateway2 = Gateway::factory()->create();

        UserGatewayAssignment::create([
            'user_id' => $user->id,
            'gateway_id' => $gateway1->id,
            'assigned_at' => now(),
        ]);

        $this->assertTrue($user->canAccessGateway($gateway1->id));
        $this->assertFalse($user->canAccessGateway($gateway2->id));
    }

    public function test_admin_can_access_any_gateway()
    {
        $adminUser = User::factory()->create(['role' => 'admin']);
        $gateway = Gateway::factory()->create();

        $this->assertTrue($adminUser->canAccessGateway($gateway->id));
    }

    public function test_is_admin_method()
    {
        $adminUser = User::factory()->create(['role' => 'admin']);
        $regularUser = User::factory()->create(['role' => 'operator']);

        $this->assertTrue($adminUser->isAdmin());
        $this->assertFalse($regularUser->isAdmin());
    }

    public function test_device_assignments_relationship()
    {
        $user = User::factory()->create();
        $device = Device::factory()->create();

        $assignment = UserDeviceAssignment::create([
            'user_id' => $user->id,
            'device_id' => $device->id,
            'assigned_at' => now(),
        ]);

        $this->assertCount(1, $user->deviceAssignments);
        $this->assertEquals($assignment->id, $user->deviceAssignments->first()->id);
    }

    public function test_gateway_assignments_relationship()
    {
        $user = User::factory()->create();
        $gateway = Gateway::factory()->create();

        $assignment = UserGatewayAssignment::create([
            'user_id' => $user->id,
            'gateway_id' => $gateway->id,
            'assigned_at' => now(),
        ]);

        $this->assertCount(1, $user->gatewayAssignments);
        $this->assertEquals($assignment->id, $user->gatewayAssignments->first()->id);
    }
}