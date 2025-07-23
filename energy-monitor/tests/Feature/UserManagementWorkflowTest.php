<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Gateway;
use App\Models\User;
use App\Models\UserDeviceAssignment;
use App\Services\SecurityLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementWorkflowTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    /**
     * Test the complete user creation and role assignment workflow.
     */
    public function test_user_creation_and_role_assignment(): void
    {
        // Create an admin user
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        // Login as admin
        $this->actingAs($admin);

        // Create a new user
        $userData = [
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'operator',
            'phone' => $this->faker->phoneNumber,
            'email_notifications' => true,
            'sms_notifications' => false,
            'notification_critical_only' => false,
        ];

        // Create the user
        $response = $this->post('/users', $userData);
        $response->assertStatus(302); // Redirect after successful creation

        // Verify the user was created
        $this->assertDatabaseHas('users', [
            'email' => $userData['email'],
            'role' => 'operator',
        ]);

        // Get the created user
        $user = User::where('email', $userData['email'])->first();
        $this->assertNotNull($user);

        // Update user role to admin
        $updateData = [
            'name' => $user->name,
            'email' => $user->email,
            'role' => 'admin',
            'phone' => $user->phone,
            'email_notifications' => $user->email_notifications,
            'sms_notifications' => $user->sms_notifications,
            'notification_critical_only' => $user->notification_critical_only,
        ];

        $response = $this->put('/users/' . $user->id, $updateData);
        $response->assertStatus(302); // Redirect after successful update

        // Verify the role was updated
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role' => 'admin',
        ]);
    }

    /**
     * Test the device assignment workflow.
     */
    public function test_device_assignment_workflow(): void
    {
        // Create admin and operator users
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $operator = User::factory()->create([
            'role' => 'operator',
        ]);

        // Create gateway and devices
        $gateway = Gateway::factory()->create();
        $device1 = Device::factory()->create(['gateway_id' => $gateway->id]);
        $device2 = Device::factory()->create(['gateway_id' => $gateway->id]);

        // Login as admin
        $this->actingAs($admin);

        // Assign device1 to operator
        $assignmentData = [
            'user_id' => $operator->id,
            'device_id' => $device1->id,
            'assigned_by' => $admin->id,
        ];

        $response = $this->post('/device-assignments', $assignmentData);
        $response->assertStatus(302); // Redirect after successful creation

        // Verify the assignment was created
        $this->assertDatabaseHas('user_device_assignments', [
            'user_id' => $operator->id,
            'device_id' => $device1->id,
        ]);

        // Login as operator
        $this->actingAs($operator);

        // Operator should be able to view assigned device
        $response = $this->get('/devices/' . $device1->id);
        $response->assertStatus(200);

        // Operator should not be able to view unassigned device
        $response = $this->get('/devices/' . $device2->id);
        $response->assertStatus(403);

        // Login as admin again
        $this->actingAs($admin);

        // Admin should be able to view all devices
        $response = $this->get('/devices/' . $device1->id);
        $response->assertStatus(200);

        $response = $this->get('/devices/' . $device2->id);
        $response->assertStatus(200);
    }

    /**
     * Test notification preference management workflow.
     */
    public function test_notification_preference_management(): void
    {
        // Create a user
        $user = User::factory()->create([
            'email_notifications' => true,
            'sms_notifications' => false,
            'notification_critical_only' => false,
        ]);

        // Login as the user
        $this->actingAs($user);

        // Update notification preferences
        $updateData = [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'email_notifications' => true,
            'sms_notifications' => true,
            'notification_critical_only' => true,
        ];

        $response = $this->put('/profile', $updateData);
        $response->assertStatus(302); // Redirect after successful update

        // Verify the preferences were updated
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email_notifications' => true,
            'sms_notifications' => true,
            'notification_critical_only' => true,
        ]);

        // Refresh user from database
        $user->refresh();

        // Verify notification channels
        $channels = $user->getNotificationChannels();
        $this->assertContains('mail', $channels);
        $this->assertContains('vonage', $channels);

        // Verify critical-only preference
        $this->assertTrue($user->shouldReceiveNotification('critical'));
        $this->assertFalse($user->shouldReceiveNotification('warning'));
    }

    /**
     * Test the complete end-to-end workflow.
     */
    public function test_complete_user_management_workflow(): void
    {
        // 1. Create admin user
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'email_notifications' => true,
        ]);

        // 2. Login as admin
        $this->actingAs($admin);

        // 3. Create operator user
        $operatorData = [
            'name' => 'Operator User',
            'email' => 'operator@test.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'operator',
            'phone' => '+1234567890',
            'email_notifications' => true,
            'sms_notifications' => false,
            'notification_critical_only' => false,
        ];

        $response = $this->post('/users', $operatorData);
        $response->assertStatus(302);

        $operator = User::where('email', 'operator@test.com')->first();
        $this->assertNotNull($operator);

        // 4. Create gateway and devices
        $gateway = Gateway::factory()->create([
            'name' => 'Test Gateway',
        ]);

        $device = Device::factory()->create([
            'name' => 'Test Device',
            'gateway_id' => $gateway->id,
        ]);

        // 5. Assign device to operator
        $assignmentData = [
            'user_id' => $operator->id,
            'device_id' => $device->id,
            'assigned_by' => $admin->id,
        ];

        $response = $this->post('/device-assignments', $assignmentData);
        $response->assertStatus(302);

        // 6. Verify operator can access assigned device
        $this->actingAs($operator);
        $response = $this->get('/devices/' . $device->id);
        $response->assertStatus(200);

        // 7. Update operator notification preferences
        $updateData = [
            'name' => $operator->name,
            'email' => $operator->email,
            'phone' => $operator->phone,
            'email_notifications' => true,
            'sms_notifications' => true,
            'notification_critical_only' => true,
        ];

        $response = $this->put('/profile', $updateData);
        $response->assertStatus(302);

        // 8. Verify preferences were updated
        $operator->refresh();
        $this->assertTrue($operator->notification_critical_only);
        $this->assertTrue($operator->sms_notifications);

        // 9. Verify operator cannot access admin resources
        $response = $this->get('/admin/users');
        $response->assertStatus(403);

        // 10. Login as admin again
        $this->actingAs($admin);

        // 11. Verify admin can access all resources
        $response = $this->get('/admin/users');
        $response->assertStatus(200);

        $response = $this->get('/devices/' . $device->id);
        $response->assertStatus(200);

        // 12. Remove device assignment
        $assignment = UserDeviceAssignment::where('user_id', $operator->id)
            ->where('device_id', $device->id)
            ->first();

        $response = $this->delete('/device-assignments/' . $assignment->id);
        $response->assertStatus(302);

        // 13. Verify assignment was removed
        $this->assertDatabaseMissing('user_device_assignments', [
            'id' => $assignment->id,
        ]);

        // 14. Verify operator can no longer access the device
        $this->actingAs($operator);
        $response = $this->get('/devices/' . $device->id);
        $response->assertStatus(403);
    }
}
