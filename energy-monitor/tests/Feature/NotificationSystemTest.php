<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Device;
use App\Models\Gateway;
use App\Models\User;
use App\Notifications\CriticalAlert as CriticalAlertNotification;
use App\Notifications\OutOfRangeAlert as OutOfRangeAlertNotification;
use App\Services\AlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationSystemTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that admin users receive notifications.
     */
    public function test_admin_users_receive_notifications(): void
    {
        Notification::fake();

        // Create admin user with notifications enabled
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_notifications' => true,
            'notification_critical_only' => false,
        ]);

        // Create gateway and device
        $gateway = Gateway::factory()->create();
        $device = Device::factory()->create(['gateway_id' => $gateway->id]);

        // Create alert
        $alert = Alert::factory()->create([
            'device_id' => $device->id,
            'severity' => 'warning',
        ]);

        // Send notification
        $admin->notify(new OutOfRangeAlertNotification($alert));

        // Assert notification was sent
        Notification::assertSentTo(
            $admin,
            OutOfRangeAlertNotification::class,
            function ($notification, $channels) use ($alert) {
                return $notification->alert->id === $alert->id;
            }
        );
    }

    /**
     * Test that operator users do not receive notifications.
     */
    public function test_operator_users_do_not_receive_notifications(): void
    {
        Notification::fake();

        // Create operator user with notifications enabled
        $operator = User::factory()->create([
            'role' => 'operator',
            'email_notifications' => true,
            'notification_critical_only' => false,
        ]);

        // Create gateway and device
        $gateway = Gateway::factory()->create();
        $device = Device::factory()->create(['gateway_id' => $gateway->id]);

        // Create alert
        $alert = Alert::factory()->create([
            'device_id' => $device->id,
            'severity' => 'warning',
        ]);

        // Check if operator should receive alert
        $this->assertFalse($operator->shouldReceiveAlert($alert));
    }

    /**
     * Test that critical-only preference filters non-critical alerts.
     */
    public function test_critical_only_preference_filters_alerts(): void
    {
        Notification::fake();

        // Create admin user with critical-only preference
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_notifications' => true,
            'notification_critical_only' => true,
        ]);

        // Create gateway and device
        $gateway = Gateway::factory()->create();
        $device = Device::factory()->create(['gateway_id' => $gateway->id]);

        // Create warning alert
        $warningAlert = Alert::factory()->create([
            'device_id' => $device->id,
            'severity' => 'warning',
        ]);

        // Create critical alert
        $criticalAlert = Alert::factory()->create([
            'device_id' => $device->id,
            'severity' => 'critical',
        ]);

        // Check if admin should receive alerts
        $this->assertFalse($admin->shouldReceiveAlert($warningAlert));
        $this->assertTrue($admin->shouldReceiveAlert($criticalAlert));
    }

    /**
     * Test that critical alerts bypass email_notifications preference.
     */
    public function test_critical_alerts_bypass_email_notifications_preference(): void
    {
        Notification::fake();

        // Create admin user with email notifications disabled
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_notifications' => false,
            'notification_critical_only' => false,
        ]);

        // Create gateway and device
        $gateway = Gateway::factory()->create();
        $device = Device::factory()->create(['gateway_id' => $gateway->id]);

        // Create warning alert
        $warningAlert = Alert::factory()->create([
            'device_id' => $device->id,
            'severity' => 'warning',
        ]);

        // Create critical alert
        $criticalAlert = Alert::factory()->create([
            'device_id' => $device->id,
            'severity' => 'critical',
        ]);

        // Check if admin should receive alerts
        $this->assertFalse($admin->shouldReceiveAlert($warningAlert));
        $this->assertTrue($admin->shouldReceiveAlert($criticalAlert));
    }

    /**
     * Test notification channels based on user preferences.
     */
    public function test_notification_channels_based_on_preferences(): void
    {
        // Create user with email notifications enabled
        $userWithEmail = User::factory()->create([
            'email_notifications' => true,
            'sms_notifications' => false,
        ]);

        // Create user with SMS notifications enabled
        $userWithSms = User::factory()->create([
            'email_notifications' => false,
            'sms_notifications' => true,
            'phone' => '+1234567890',
        ]);

        // Create user with both notifications enabled
        $userWithBoth = User::factory()->create([
            'email_notifications' => true,
            'sms_notifications' => true,
            'phone' => '+1234567890',
        ]);

        // Check notification channels
        $this->assertEquals(['mail'], $userWithEmail->getNotificationChannels());
        $this->assertEquals(['vonage'], $userWithSms->getNotificationChannels());
        $this->assertEquals(['mail', 'vonage'], $userWithBoth->getNotificationChannels());
    }

    /**
     * Test AlertService notification filtering.
     */
    public function test_alert_service_notification_filtering(): void
    {
        Notification::fake();

        // Create users with different preferences
        $adminAll = User::factory()->create([
            'role' => 'admin',
            'email_notifications' => true,
            'notification_critical_only' => false,
        ]);

        $adminCriticalOnly = User::factory()->create([
            'role' => 'admin',
            'email_notifications' => true,
            'notification_critical_only' => true,
        ]);

        $operator = User::factory()->create([
            'role' => 'operator',
            'email_notifications' => true,
            'notification_critical_only' => false,
        ]);

        // Create gateway and device
        $gateway = Gateway::factory()->create();
        $device = Device::factory()->create(['gateway_id' => $gateway->id]);

        // Create alerts
        $warningAlert = Alert::factory()->create([
            'device_id' => $device->id,
            'severity' => 'warning',
        ]);

        $criticalAlert = Alert::factory()->create([
            'device_id' => $device->id,
            'severity' => 'critical',
        ]);

        // Use reflection to access private method
        $alertService = new AlertService();
        $reflection = new \ReflectionClass($alertService);
        $method = $reflection->getMethod('sendNotifications');
        $method->setAccessible(true);

        // Send warning notification
        $method->invoke($alertService, $warningAlert, OutOfRangeAlertNotification::class);

        // Send critical notification
        $method->invoke($alertService, $criticalAlert, CriticalAlertNotification::class);

        // Assert notifications were sent correctly
        Notification::assertSentTo(
            $adminAll,
            OutOfRangeAlertNotification::class
        );

        Notification::assertNotSentTo(
            $adminCriticalOnly,
            OutOfRangeAlertNotification::class
        );

        Notification::assertSentTo(
            $adminAll,
            CriticalAlertNotification::class
        );

        Notification::assertSentTo(
            $adminCriticalOnly,
            CriticalAlertNotification::class
        );

        Notification::assertNotSentTo(
            $operator,
            [OutOfRangeAlertNotification::class, CriticalAlertNotification::class]
        );
    }
}
