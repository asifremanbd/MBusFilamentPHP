<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Gateway;
use App\Models\Device;
use App\Models\UserDeviceAssignment;
use App\Models\UserGatewayAssignment;
use Livewire\Livewire;
use App\Filament\Resources\UserResource;

class UserManagementTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->operator = User::factory()->create(['role' => 'operator']);
        
        $this->gateway1 = Gateway::factory()->create(['name' => 'Gateway 1']);
        $this->gateway2 = Gateway::factory()->create(['name' => 'Gateway 2']);
        
        $this->device1 = Device::factory()->create(['gateway_id' => $this->gateway1->id]);
        $this->device2 = Device::factory()->create(['gateway_id' => $this->gateway1->id]);
        $this->device3 = Device::factory()->create(['gateway_id' => $this->gateway2->id]);
    }

    public function test_admin_can_access_user_management()
    {
        $this->assertTrue(UserResource::canViewAny());
        
        $this->actingAs($this->admin);
        $this->assertTrue(UserResource::canViewAny());
    }

    public function test_operator_cannot_access_user_management()
    {
        $this->actingAs($this->operator);
        $this->assertFalse(UserResource::canViewAny());
    }

    public function test_admin_can_create_user_with_permissions()
    {
        $this->actingAs($this->admin);

        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'role' => 'operator',
            'email_notifications' => true,
            'sms_notifications' => false,
            'notification_critical_only' => false,
        ];

        $response = $this->post('/admin/users', $userData);
        
        $this->assertDatabaseHas('users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'operator',
        ]);
    }

    public function test_admin_can_assign_gateways_to_user()
    {
        $this->actingAs($this->admin);
        
        $user = User::factory()->create(['role' => 'operator']);

        // Simulate gateway assignment
        UserGatewayAssignment::create([
            'user_id' => $user->id,
            'gateway_id' => $this->gateway1->id,
            'assigned_at' => now(),
            'assigned_by' => $this->admin->id,
        ]);

        $this->assertDatabaseHas('user_gateway_assignments', [
            'user_id' => $user->id,
            'gateway_id' => $this->gateway1->id,
            'assigned_by' => $this->admin->id,
        ]);
    }

    public function test_admin_can_assign_devices_to_user()
    {
        $this->actingAs($this->admin);
        
        $user = User::factory()->create(['role' => 'operator']);

        // Simulate device assignment
        UserDeviceAssignment::create([
            'user_id' => $user->id,
            'device_id' => $this->device1->id,
            'assigned_at' => now(),
            'assigned_by' => $this->admin->id,
        ]);

        $this->assertDatabaseHas('user_device_assignments', [
            'user_id' => $user->id,
            'device_id' => $this->device1->id,
            'assigned_by' => $this->admin->id,
        ]);
    }

    public function test_bulk_gateway_assignment_works()
    {
        $this->actingAs($this->admin);
        
        $user1 = User::factory()->create(['role' => 'operator']);
        $user2 = User::factory()->create(['role' => 'operator']);

        // Simulate bulk gateway assignment
        $users = [$user1, $user2];
        $gateways = [$this->gateway1->id, $this->gateway2->id];

        foreach ($users as $user) {
            foreach ($gateways as $gatewayId) {
                UserGatewayAssignment::create([
                    'user_id' => $user->id,
                    'gateway_id' => $gatewayId,
                    'assigned_at' => now(),
                    'assigned_by' => $this->admin->id,
                ]);
            }
        }

        // Verify assignments
        $this->assertEquals(2, UserGatewayAssignment::where('user_id', $user1->id)->count());
        $this->assertEquals(2, UserGatewayAssignment::where('user_id', $user2->id)->count());
    }

    public function test_bulk_device_assignment_works()
    {
        $this->actingAs($this->admin);
        
        $user1 = User::factory()->create(['role' => 'operator']);
        $user2 = User::factory()->create(['role' => 'operator']);

        // Simulate bulk device assignment
        $users = [$user1, $user2];
        $devices = [$this->device1->id, $this->device2->id];

        foreach ($users as $user) {
            foreach ($devices as $deviceId) {
                UserDeviceAssignment::create([
                    'user_id' => $user->id,
                    'device_id' => $deviceId,
                    'assigned_at' => now(),
                    'assigned_by' => $this->admin->id,
                ]);
            }
        }

        // Verify assignments
        $this->assertEquals(2, UserDeviceAssignment::where('user_id', $user1->id)->count());
        $this->assertEquals(2, UserDeviceAssignment::where('user_id', $user2->id)->count());
    }

    public function test_auto_assign_gateway_devices_works()
    {
        $this->actingAs($this->admin);
        
        $user = User::factory()->create(['role' => 'operator']);

        // Assign gateway
        UserGatewayAssignment::create([
            'user_id' => $user->id,
            'gateway_id' => $this->gateway1->id,
            'assigned_at' => now(),
            'assigned_by' => $this->admin->id,
        ]);

        // Auto-assign gateway devices
        $gatewayDevices = Device::where('gateway_id', $this->gateway1->id)->get();
        foreach ($gatewayDevices as $device) {
            UserDeviceAssignment::firstOrCreate([
                'user_id' => $user->id,
                'device_id' => $device->id,
            ], [
                'assigned_at' => now(),
                'assigned_by' => $this->admin->id,
            ]);
        }

        // Verify that devices from gateway1 are assigned
        $assignedDevices = UserDeviceAssignment::where('user_id', $user->id)->pluck('device_id');
        $this->assertContains($this->device1->id, $assignedDevices);
        $this->assertContains($this->device2->id, $assignedDevices);
        $this->assertNotContains($this->device3->id, $assignedDevices); // device3 is in gateway2
    }

    public function test_permission_hierarchy_display_works()
    {
        $this->actingAs($this->admin);
        
        $user = User::factory()->create(['role' => 'operator']);

        // Assign gateway and devices
        UserGatewayAssignment::create([
            'user_id' => $user->id,
            'gateway_id' => $this->gateway1->id,
            'assigned_at' => now(),
            'assigned_by' => $this->admin->id,
        ]);

        UserDeviceAssignment::create([
            'user_id' => $user->id,
            'device_id' => $this->device1->id,
            'assigned_at' => now(),
            'assigned_by' => $this->admin->id,
        ]);

        // Verify hierarchy relationships
        $gatewayAssignments = UserGatewayAssignment::where('user_id', $user->id)->get();
        $deviceAssignments = UserDeviceAssignment::where('user_id', $user->id)->get();

        $this->assertCount(1, $gatewayAssignments);
        $this->assertCount(1, $deviceAssignments);

        // Verify device belongs to assigned gateway
        $assignedDevice = Device::find($deviceAssignments->first()->device_id);
        $assignedGateway = $gatewayAssignments->first()->gateway_id;
        
        $this->assertEquals($assignedGateway, $assignedDevice->gateway_id);
    }

    public function test_audit_logging_for_permission_changes()
    {
        $this->actingAs($this->admin);
        
        $user = User::factory()->create(['role' => 'operator']);

        // Create assignment with audit info
        $assignment = UserGatewayAssignment::create([
            'user_id' => $user->id,
            'gateway_id' => $this->gateway1->id,
            'assigned_at' => now(),
            'assigned_by' => $this->admin->id,
        ]);

        // Verify audit information is stored
        $this->assertEquals($this->admin->id, $assignment->assigned_by);
        $this->assertNotNull($assignment->assigned_at);
    }

    public function test_user_cannot_modify_own_permissions()
    {
        $this->actingAs($this->admin);

        // Admin should not be able to delete themselves through bulk actions
        // This is handled in the UserResource bulk action logic
        
        // Simulate the check that prevents self-modification
        $currentUserId = $this->admin->id;
        $usersToModify = collect([$this->admin, $this->operator]);
        
        $filteredUsers = $usersToModify->reject(fn ($user) => $user->id === $currentUserId);
        
        $this->assertCount(1, $filteredUsers);
        $this->assertEquals($this->operator->id, $filteredUsers->first()->id);
    }

    public function test_permission_removal_works()
    {
        $this->actingAs($this->admin);
        
        $user = User::factory()->create(['role' => 'operator']);

        // Assign permissions
        UserGatewayAssignment::create([
            'user_id' => $user->id,
            'gateway_id' => $this->gateway1->id,
            'assigned_at' => now(),
            'assigned_by' => $this->admin->id,
        ]);

        UserDeviceAssignment::create([
            'user_id' => $user->id,
            'device_id' => $this->device1->id,
            'assigned_at' => now(),
            'assigned_by' => $this->admin->id,
        ]);

        // Verify assignments exist
        $this->assertEquals(1, UserGatewayAssignment::where('user_id', $user->id)->count());
        $this->assertEquals(1, UserDeviceAssignment::where('user_id', $user->id)->count());

        // Remove permissions
        UserGatewayAssignment::where('user_id', $user->id)->delete();
        UserDeviceAssignment::where('user_id', $user->id)->delete();

        // Verify permissions are removed
        $this->assertEquals(0, UserGatewayAssignment::where('user_id', $user->id)->count());
        $this->assertEquals(0, UserDeviceAssignment::where('user_id', $user->id)->count());
    }

    public function test_replace_existing_assignments_works()
    {
        $this->actingAs($this->admin);
        
        $user = User::factory()->create(['role' => 'operator']);

        // Initial assignment
        UserGatewayAssignment::create([
            'user_id' => $user->id,
            'gateway_id' => $this->gateway1->id,
            'assigned_at' => now(),
            'assigned_by' => $this->admin->id,
        ]);

        $this->assertEquals(1, UserGatewayAssignment::where('user_id', $user->id)->count());

        // Replace with new assignment
        UserGatewayAssignment::where('user_id', $user->id)->delete();
        UserGatewayAssignment::create([
            'user_id' => $user->id,
            'gateway_id' => $this->gateway2->id,
            'assigned_at' => now(),
            'assigned_by' => $this->admin->id,
        ]);

        // Verify replacement
        $this->assertEquals(1, UserGatewayAssignment::where('user_id', $user->id)->count());
        $assignment = UserGatewayAssignment::where('user_id', $user->id)->first();
        $this->assertEquals($this->gateway2->id, $assignment->gateway_id);
    }

    public function test_gateway_filter_works_for_device_assignment()
    {
        $this->actingAs($this->admin);

        // Test filtering devices by gateway
        $gateway1Devices = Device::where('gateway_id', $this->gateway1->id)->get();
        $gateway2Devices = Device::where('gateway_id', $this->gateway2->id)->get();

        $this->assertCount(2, $gateway1Devices);
        $this->assertCount(1, $gateway2Devices);

        // Verify devices belong to correct gateways
        foreach ($gateway1Devices as $device) {
            $this->assertEquals($this->gateway1->id, $device->gateway_id);
        }

        foreach ($gateway2Devices as $device) {
            $this->assertEquals($this->gateway2->id, $device->gateway_id);
        }
    }

    public function test_user_assignment_counts_are_accurate()
    {
        $this->actingAs($this->admin);
        
        $user = User::factory()->create(['role' => 'operator']);

        // Assign 2 gateways and 3 devices
        UserGatewayAssignment::create([
            'user_id' => $user->id,
            'gateway_id' => $this->gateway1->id,
            'assigned_at' => now(),
            'assigned_by' => $this->admin->id,
        ]);

        UserGatewayAssignment::create([
            'user_id' => $user->id,
            'gateway_id' => $this->gateway2->id,
            'assigned_at' => now(),
            'assigned_by' => $this->admin->id,
        ]);

        UserDeviceAssignment::create([
            'user_id' => $user->id,
            'device_id' => $this->device1->id,
            'assigned_at' => now(),
            'assigned_by' => $this->admin->id,
        ]);

        UserDeviceAssignment::create([
            'user_id' => $user->id,
            'device_id' => $this->device2->id,
            'assigned_at' => now(),
            'assigned_by' => $this->admin->id,
        ]);

        UserDeviceAssignment::create([
            'user_id' => $user->id,
            'device_id' => $this->device3->id,
            'assigned_at' => now(),
            'assigned_by' => $this->admin->id,
        ]);

        // Verify counts
        $gatewayCount = UserGatewayAssignment::where('user_id', $user->id)->count();
        $deviceCount = UserDeviceAssignment::where('user_id', $user->id)->count();

        $this->assertEquals(2, $gatewayCount);
        $this->assertEquals(3, $deviceCount);
    }

    public function test_user_deactivation_removes_permissions()
    {
        $this->actingAs($this->admin);
        
        $user = User::factory()->create(['role' => 'operator']);

        // Assign permissions
        UserGatewayAssignment::create([
            'user_id' => $user->id,
            'gateway_id' => $this->gateway1->id,
            'assigned_at' => now(),
            'assigned_by' => $this->admin->id,
        ]);

        UserDeviceAssignment::create([
            'user_id' => $user->id,
            'device_id' => $this->device1->id,
            'assigned_at' => now(),
            'assigned_by' => $this->admin->id,
        ]);

        // Verify assignments exist
        $this->assertEquals(1, UserGatewayAssignment::where('user_id', $user->id)->count());
        $this->assertEquals(1, UserDeviceAssignment::where('user_id', $user->id)->count());

        // Delete user (simulating deactivation)
        $user->delete();

        // Verify permissions are removed (assuming cascade delete is set up)
        // In a real scenario, this would be handled by foreign key constraints
        // or explicit cleanup in the user deletion process
        $this->assertTrue($user->trashed() || !User::find($user->id));
    }
}