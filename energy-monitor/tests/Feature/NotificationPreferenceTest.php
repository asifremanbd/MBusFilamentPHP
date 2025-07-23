<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Device;
use App\Models\Gateway;
use App\Models\Alert;
use App\Services\AlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationPreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_with_email_enabled_receives_all_alerts(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_notifications' => true,
            'notification_critical_only' => false,
        ]);

        $criticalAlert = (object) ['severity' => 'critical'];
        $warningAlert = (object) ['severity' => 'warning'];
        $infoAlert = (object) ['severity' => 'info'];

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

        $criticalAlert = (object) ['severity' => 'critical'];
        $warningAlert = (object) ['severity' => 'warning'];
        $infoAlert = (object) ['severity' => 'info'];

        $this->assertTrue($admin->shouldReceiveAlert($criticalAlert));
        $this->assertFalse($admin->shouldReceiveAlert($warningAlert));
        $this->assertFalse($admin->shouldReceiveAlert($infoAlert));
    }

    public function test_admin_with_email_disabled_receives_critical_only(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_notifications' => false,
            'notification_critical_only' => false,
        ]);

        $criticalAlert = (object) ['severity' => 'critical'];
        $warningAlert = (object) ['severity' => 'warning'];

        // Critical alerts bypass email_notifications preference
        $this->assertTrue($admin->shouldReceiveAlert($criticalAlert));
        $this->assertFalse($admin->shouldReceiveAlert($warningAlert));
    }

    public function test_operator_never_receives_email_notifications(): void
    {
        $operator = User::factory()->create([
            'role' => 'operator',
            'email_notifications' => true,
            'notification_critical_only' => false,
        ]);

        $criticalAlert = (object) ['severity' => 'critical'];
        $warningAlert = (object) ['severity' => 'warning'];

        $this->assertFalse($operator->shouldReceiveAlert($criticalAlert));
        $this->assertFalse($operator->shouldReceiveAlert($warningAlert));
    }

    public function test_user_notification_channels(): void
    {
        $userWithBoth = User::factory()->create([
            'email_notifications' => true,
            'sms_notifications' => true,
            'phone' => '+1234567890',
        ]);

        $userWithEmailOnly = User::factory()->create([
            'email_notifications' => true,
            'sms_notifications' => false,
        ]);

        $userWithSmsOnly = User::factory()->create([
            'email_notifications' => false,
            'sms_notifications' => true,
            'phone' => '+1234567890',
        ]);

        $userWithNone = User::factory()->create([
            'email_notifications' => false,
            'sms_notifications' => false,
        ]);

        $this->assertEquals(['mail', 'vonage'], $userWithBoth->getNotificationChannels());
        $this->assertEquals(['mail'], $userWithEmailOnly->getNotificationChannels());
        $this->assertEquals(['vonage'], $userWithSmsOnly->getNotificationChannels());
        $this->assertEquals([], $userWithNone->getNotificationChannels());
    }

    public function test_user_sms_notifications_require_phone_number(): void
    {
        $userWithoutPhone = User::factory()->create([
            'email_notifications' => false,
            'sms_notifications' => true,
            'phone' => null,
        ]);

        $this->assertEquals([], $userWithoutPhone->getNotificationChannels());
    }

    public function test_should_receive_notification_method(): void
    {
        $user = User::factory()->create([
            'notification_critical_only' => false,
        ]);

        $this->assertTrue($user->shouldReceiveNotification('critical'));
        $this->assertTrue($user->shouldReceiveNotification('warning'));
        $this->assertTrue($user->shouldReceiveNotification('info'));
    }

    public function test_should_receive_notification_critical_only(): void
    {
        $user = User::factory()->create([
            'notification_critical_only' => true,
        ]);

        $this->assertTrue($user->shouldReceiveNotification('critical'));
        $this->assertFalse($user->shouldReceiveNotification('warning'));
        $this->assertFalse($user->shouldReceiveNotification('info'));
    }

    public function test_alert_service_respects_user_preferences(): void
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

        $adminEmailDisabled = User::factory()->create([
            'role' => 'admin',
            'email_notifications' => false,
            'notification_critical_only' => false,
        ]);

        $operator = User::factory()->create([
            'role' => 'operator',
            'email_notifications' => true,
            'notification_critical_only' => false,
        ]);

        // Create test data
        $gateway = Gateway::factory()->create();
        $device = Device::factory()->create(['gateway_id' => $gateway->id]);
        
        $warningAlert = Alert::factory()->create([
            'device_id' => $device->id,
            'severity' => 'warning',
        ]);

        $criticalAlert = Alert::factory()->create([
            'device_id' => $device->id,
            'severity' => 'critical',
        ]);

        // Test warning alert notifications
        $alertService = new AlertService();
        $reflection = new \ReflectionClass($alertService);
        $method = $reflection->getMethod('sendNotifications');
        $method->setAccessible(true);

        $method->invoke($alertService, $warningAlert, \App\Notifications\OutOfRangeAlert::class);

        // Only adminAll should receive warning alerts
        Notification::assertSentTo($adminAll, \App\Notifications\OutOfRangeAlert::class);
        Notification::assertNotSentTo($adminCriticalOnly, \App\Notifications\OutOfRangeAlert::class);
        Notification::assertNotSentTo($adminEmailDisabled, \App\Notifications\OutOfRangeAlert::class);
        Notification::assertNotSentTo($operator, \App\Notifications\OutOfRangeAlert::class);

        // Reset notifications
        Notification::fake();

        // Test critical alert notifications
        $method->invoke($alertService, $criticalAlert, \App\Notifications\CriticalAlert::class);

        // All admins should receive critical alerts (bypasses preferences)
        Notification::assertSentTo($adminAll, \App\Notifications\CriticalAlert::class);
        Notification::assertSentTo($adminCriticalOnly, \App\Notifications\CriticalAlert::class);
        Notification::assertSentTo($adminEmailDisabled, \App\Notifications\CriticalAlert::class);
        Notification::assertNotSentTo($operator, \App\Notifications\CriticalAlert::class);
    }
}
