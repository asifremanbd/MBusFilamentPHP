<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Device;
use App\Models\Gateway;
use App\Models\Alert;
use App\Models\UserDeviceAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AuthorizationPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_all_devices(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $gateway = Gateway::factory()->create();
        $device = Device::factory()->create(['gateway_id' => $gateway->id]);

        $this->assertTrue($admin->can('viewAny', Device::class));
        $this->assertTrue($admin->can('view', $device));
        $this->assertTrue($admin->can('create', Device::class));
        $this->assertTrue($admin->can('update', $device));
        $this->assertTrue($admin->can('delete', $device));
    }

    public function test_operator_can_only_view_assigned_devices(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $operator = User::factory()->create(['role' => 'operator']);
        $gateway = Gateway::factory()->create();
        $assignedDevice = Device::factory()->create(['gateway_id' => $gateway->id]);
        $unassignedDevice = Device::factory()->create(['gateway_id' => $gateway->id]);

        // Assign one device to operator
        UserDeviceAssignment::create([
            'user_id' => $operator->id,
            'device_id' => $assignedDevice->id,
            'assigned_by' => $admin->id,
        ]);

        $this->assertTrue($operator->can('viewAny', Device::class));
        $this->assertTrue($operator->can('view', $assignedDevice));
        $this->assertFalse($operator->can('view', $unassignedDevice));
        $this->assertFalse($operator->can('create', Device::class));
        $this->assertFalse($operator->can('update', $assignedDevice));
        $this->assertFalse($operator->can('delete', $assignedDevice));
    }

    public function test_admin_can_manage_all_alerts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $gateway = Gateway::factory()->create();
        $device = Device::factory()->create(['gateway_id' => $gateway->id]);
        $alert = Alert::factory()->create(['device_id' => $device->id]);

        $this->assertTrue($admin->can('viewAny', Alert::class));
        $this->assertTrue($admin->can('view', $alert));
        $this->assertFalse($admin->can('create', Alert::class)); // Alerts are system-generated
        $this->assertTrue($admin->can('update', $alert));
        $this->assertTrue($admin->can('delete', $alert));
    }

    public function test_operator_can_only_view_alerts_for_assigned_devices(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $operator = User::factory()->create(['role' => 'operator']);
        $gateway = Gateway::factory()->create();
        $assignedDevice = Device::factory()->create(['gateway_id' => $gateway->id]);
        $unassignedDevice = Device::factory()->create(['gateway_id' => $gateway->id]);
        
        $assignedAlert = Alert::factory()->create(['device_id' => $assignedDevice->id]);
        $unassignedAlert = Alert::factory()->create(['device_id' => $unassignedDevice->id]);

        // Assign device to operator
        UserDeviceAssignment::create([
            'user_id' => $operator->id,
            'device_id' => $assignedDevice->id,
            'assigned_by' => $admin->id,
        ]);

        $this->assertTrue($operator->can('viewAny', Alert::class));
        $this->assertTrue($operator->can('view', $assignedAlert));
        $this->assertFalse($operator->can('view', $unassignedAlert));
        $this->assertFalse($operator->can('create', Alert::class));
        $this->assertTrue($operator->can('update', $assignedAlert)); // Can resolve assigned alerts
        $this->assertFalse($operator->can('update', $unassignedAlert));
        $this->assertFalse($operator->can('delete', $assignedAlert)); // Only admins can delete
    }

    public function test_admin_can_manage_users(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $otherUser = User::factory()->create(['role' => 'operator']);

        $this->assertTrue($admin->can('viewAny', User::class));
        $this->assertTrue($admin->can('view', $otherUser));
        $this->assertTrue($admin->can('create', User::class));
        $this->assertTrue($admin->can('update', $otherUser));
        $this->assertTrue($admin->can('delete', $otherUser));
        $this->assertFalse($admin->can('delete', $admin)); // Cannot delete themselves
    }

    public function test_operator_cannot_manage_users(): void
    {
        $operator = User::factory()->create(['role' => 'operator']);
        $otherUser = User::factory()->create(['role' => 'admin']);

        $this->assertFalse($operator->can('viewAny', User::class));
        $this->assertFalse($operator->can('view', $otherUser));
        $this->assertTrue($operator->can('view', $operator)); // Can view own profile
        $this->assertFalse($operator->can('create', User::class));
        $this->assertTrue($operator->can('update', $operator)); // Can update own profile
        $this->assertFalse($operator->can('update', $otherUser));
        $this->assertFalse($operator->can('delete', $otherUser));
    }

    public function test_admin_can_view_gateways_with_assigned_devices(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $gateway = Gateway::factory()->create();

        $this->assertTrue($admin->can('viewAny', Gateway::class));
        $this->assertTrue($admin->can('view', $gateway));
        $this->assertTrue($admin->can('create', Gateway::class));
        $this->assertTrue($admin->can('update', $gateway));
        $this->assertTrue($admin->can('delete', $gateway));
    }

    public function test_operator_can_only_view_gateways_with_assigned_devices(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $operator = User::factory()->create(['role' => 'operator']);
        $gatewayWithAssignedDevice = Gateway::factory()->create();
        $gatewayWithoutAssignedDevice = Gateway::factory()->create();
        
        $assignedDevice = Device::factory()->create(['gateway_id' => $gatewayWithAssignedDevice->id]);
        $unassignedDevice = Device::factory()->create(['gateway_id' => $gatewayWithoutAssignedDevice->id]);

        // Assign device to operator
        UserDeviceAssignment::create([
            'user_id' => $operator->id,
            'device_id' => $assignedDevice->id,
            'assigned_by' => $admin->id,
        ]);

        $this->assertTrue($operator->can('viewAny', Gateway::class));
        $this->assertTrue($operator->can('view', $gatewayWithAssignedDevice));
        $this->assertFalse($operator->can('view', $gatewayWithoutAssignedDevice));
        $this->assertFalse($operator->can('create', Gateway::class));
        $this->assertFalse($operator->can('update', $gatewayWithAssignedDevice));
        $this->assertFalse($operator->can('delete', $gatewayWithAssignedDevice));
    }
}
