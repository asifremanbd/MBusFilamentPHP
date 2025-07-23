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

class UserRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_check_if_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $operator = User::factory()->create(['role' => 'operator']);

        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($operator->isAdmin());
    }

    public function test_user_can_check_if_operator(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $operator = User::factory()->create(['role' => 'operator']);

        $this->assertFalse($admin->isOperator());
        $this->assertTrue($operator->isOperator());
    }

    public function test_user_can_get_assigned_devices(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $operator = User::factory()->create(['role' => 'operator']);
        $gateway = Gateway::factory()->create();
        $device1 = Device::factory()->create(['gateway_id' => $gateway->id]);
        $device2 = Device::factory()->create(['gateway_id' => $gateway->id]);
        $device3 = Device::factory()->create(['gateway_id' => $gateway->id]);

        // Assign devices to operator
        UserDeviceAssignment::create([
            'user_id' => $operator->id,
            'device_id' => $device1->id,
            'assigned_by' => $admin->id,
        ]);
        UserDeviceAssignment::create([
            'user_id' => $operator->id,
            'device_id' => $device2->id,
            'assigned_by' => $admin->id,
        ]);

        $assignedDevices = $operator->getAssignedDevices()->get();
        
        $this->assertCount(2, $assignedDevices);
        $this->assertTrue($assignedDevices->contains($device1));
        $this->assertTrue($assignedDevices->contains($device2));
        $this->assertFalse($assignedDevices->contains($device3));
    }

    public function test_admin_should_receive_all_alerts(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_notifications' => true,
            'notification_critical_only' => false,
        ]);

        $gateway = Gateway::factory()->create();
        $device = Device::factory()->create(['gateway_id' => $gateway->id]);
        
        $criticalAlert = Alert::factory()->create(['device_id' => $device->id, 'severity' => 'critical']);
        $warningAlert = Alert::factory()->create(['device_id' => $device->id, 'severity' => 'warning']);
        $infoAlert = Alert::factory()->create(['device_id' => $device->id, 'severity' => 'info']);

        $this->assertTrue($admin->shouldReceiveAlert($criticalAlert));
        $this->assertTrue($admin->shouldReceiveAlert($warningAlert));
        $this->assertTrue($admin->shouldReceiveAlert($infoAlert));
    }

    public function test_admin_with_critical_only_preference(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_notifications' => true,
            'notification_critical_only' => true,
        ]);

        $gateway = Gateway::factory()->create();
        $device = Device::factory()->create(['gateway_id' => $gateway->id]);
        
        $criticalAlert = Alert::factory()->create(['device_id' => $device->id, 'severity' => 'critical']);
        $warningAlert = Alert::factory()->create(['device_id' => $device->id, 'severity' => 'warning']);

        $this->assertTrue($admin->shouldReceiveAlert($criticalAlert));
        $this->assertFalse($admin->shouldReceiveAlert($warningAlert));
    }

    public function test_admin_with_email_disabled_receives_critical_only(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_notifications' => false,
            'notification_critical_only' => false,
        ]);

        $gateway = Gateway::factory()->create();
        $device = Device::factory()->create(['gateway_id' => $gateway->id]);
        
        $criticalAlert = Alert::factory()->create(['device_id' => $device->id, 'severity' => 'critical']);
        $warningAlert = Alert::factory()->create(['device_id' => $device->id, 'severity' => 'warning']);

        $this->assertTrue($admin->shouldReceiveAlert($criticalAlert));
        $this->assertFalse($admin->shouldReceiveAlert($warningAlert));
    }

    public function test_operator_should_not_receive_any_alerts(): void
    {
        $operator = User::factory()->create([
            'role' => 'operator',
            'email_notifications' => true,
            'notification_critical_only' => false,
        ]);

        $gateway = Gateway::factory()->create();
        $device = Device::factory()->create(['gateway_id' => $gateway->id]);
        
        $criticalAlert = Alert::factory()->create(['device_id' => $device->id, 'severity' => 'critical']);
        $warningAlert = Alert::factory()->create(['device_id' => $device->id, 'severity' => 'warning']);

        $this->assertFalse($operator->shouldReceiveAlert($criticalAlert));
        $this->assertFalse($operator->shouldReceiveAlert($warningAlert));
    }
}
