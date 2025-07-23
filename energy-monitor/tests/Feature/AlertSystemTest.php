<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Device;
use App\Models\Gateway;
use App\Models\Register;
use App\Models\Alert;
use App\Services\AlertService;
use App\Notifications\OutOfRangeAlert;
use App\Notifications\CriticalAlert;
use App\Notifications\OffHoursAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AlertSystemTest extends TestCase
{
    use RefreshDatabase;

    protected AlertService $alertService;

    public function setUp(): void
    {
        parent::setUp();
        $this->alertService = app(AlertService::class);
        Notification::fake();
    }

    public function test_creates_out_of_range_alert()
    {
        // Create test data
        $user = User::factory()->create([
            'role' => 'admin',
            'email_notifications' => true,
            'sms_notifications' => false,
            'notification_critical_only' => false
        ]);

        $gateway = Gateway::create([
            'name' => 'Test Gateway',
            'fixed_ip' => '192.168.1.100',
            'sim_number' => '+1234567890',
            'gsm_signal' => -70,
            'gnss_location' => '40.7128,-74.0060'
        ]);

        $device = Device::create([
            'name' => 'Test Device',
            'slave_id' => 1,
            'location_tag' => 'Building A',
            'gateway_id' => $gateway->id
        ]);

        $register = Register::create([
            'device_id' => $device->id,
            'parameter_name' => 'Voltage (L-N)',
            'register_address' => 40001,
            'data_type' => 'float',
            'unit' => 'V',
            'scale' => 1.0,
            'normal_range' => '220-240',
            'critical' => false,
            'notes' => 'Line to Neutral Voltage'
        ]);

        // Test out of range value
        $alerts = $this->alertService->processAlerts($register, 260.0, '2025-07-08T16:00:00Z');

        // Assert alert was created
        $this->assertCount(1, $alerts);
        $this->assertEquals('warning', $alerts[0]->severity);
        $this->assertEquals(260.0, $alerts[0]->value);
        $this->assertStringContainsString('outside normal range', $alerts[0]->message);

        // Assert notification was sent
        Notification::assertSentTo($user, OutOfRangeAlert::class);
    }

    public function test_creates_critical_alert()
    {
        // Create test data
        $user = User::factory()->create([
            'role' => 'admin',
            'email_notifications' => true,
            'sms_notifications' => true,
            'phone' => '+1234567890',
            'notification_critical_only' => true
        ]);

        $gateway = Gateway::create([
            'name' => 'Test Gateway',
            'fixed_ip' => '192.168.1.100',
            'sim_number' => '+1234567890',
            'gsm_signal' => -70,
            'gnss_location' => '40.7128,-74.0060'
        ]);

        $device = Device::create([
            'name' => 'Critical Device',
            'slave_id' => 1,
            'location_tag' => 'Building A',
            'gateway_id' => $gateway->id
        ]);

        $register = Register::create([
            'device_id' => $device->id,
            'parameter_name' => 'Temperature',
            'register_address' => 40001,
            'data_type' => 'float',
            'unit' => '°C',
            'scale' => 1.0,
            'normal_range' => '20-30',
            'critical' => true,
            'notes' => 'Critical temperature sensor'
        ]);

        // Test critical value (beyond 20% of normal range)
        $alerts = $this->alertService->processAlerts($register, 35.0, '2025-07-08T16:00:00Z');

        // Assert alerts were created (out of range + critical)
        $this->assertGreaterThan(0, count($alerts));
        $criticalAlert = collect($alerts)->firstWhere('severity', 'critical');
        $this->assertNotNull($criticalAlert);

        // Assert notification was sent (user wants critical only)
        Notification::assertSentTo($user, CriticalAlert::class);
    }

    public function test_creates_off_hours_alert()
    {
        // Create test data
        $user = User::factory()->create([
            'role' => 'admin',
            'email_notifications' => true,
            'sms_notifications' => false,
            'notification_critical_only' => false
        ]);

        $gateway = Gateway::create([
            'name' => 'Test Gateway',
            'fixed_ip' => '192.168.1.100',
            'sim_number' => '+1234567890',
            'gsm_signal' => -70,
            'gnss_location' => '40.7128,-74.0060'
        ]);

        $device = Device::create([
            'name' => 'Test Device',
            'slave_id' => 1,
            'location_tag' => 'Building A',
            'gateway_id' => $gateway->id
        ]);

        $register = Register::create([
            'device_id' => $device->id,
            'parameter_name' => 'Power Consumption',
            'register_address' => 40001,
            'data_type' => 'float',
            'unit' => 'kW',
            'scale' => 1.0,
            'normal_range' => '0-50',
            'critical' => false,
            'notes' => 'Power consumption meter'
        ]);

        // Test off-hours reading (11 PM)
        $alerts = $this->alertService->processAlerts($register, 25.0, '2025-07-08T23:00:00Z');

        // Assert alert was created
        $this->assertGreaterThan(0, count($alerts));
        $offHoursAlert = collect($alerts)->firstWhere('severity', 'info');
        $this->assertNotNull($offHoursAlert);
        $this->assertStringContainsString('off-hours', $offHoursAlert->message);

        // Assert notification was sent
        Notification::assertSentTo($user, OffHoursAlert::class);
    }

    public function test_resolve_alert()
    {
        // Create test data
        $user = User::factory()->create(['role' => 'admin']);
        $alert = Alert::create([
            'device_id' => 1,
            'parameter_name' => 'Test Parameter',
            'value' => 100.0,
            'severity' => 'warning',
            'timestamp' => now(),
            'resolved' => false,
            'message' => 'Test alert'
        ]);

        // Resolve the alert
        $result = $this->alertService->resolveAlert($alert->id, $user->id);

        $this->assertTrue($result);
        $alert->refresh();
        $this->assertTrue($alert->resolved);
        $this->assertEquals($user->id, $alert->resolved_by);
        $this->assertNotNull($alert->resolved_at);
    }

    public function test_get_alert_statistics()
    {
        // Create test alerts
        Alert::create([
            'device_id' => 1,
            'parameter_name' => 'Test',
            'value' => 100.0,
            'severity' => 'critical',
            'timestamp' => now(),
            'resolved' => false,
            'message' => 'Critical alert'
        ]);

        Alert::create([
            'device_id' => 1,
            'parameter_name' => 'Test',
            'value' => 100.0,
            'severity' => 'warning',
            'timestamp' => now(),
            'resolved' => true,
            'message' => 'Resolved alert'
        ]);

        Alert::create([
            'device_id' => 1,
            'parameter_name' => 'Test',
            'value' => 100.0,
            'severity' => 'info',
            'timestamp' => today(),
            'resolved' => false,
            'message' => 'Today alert'
        ]);

        $stats = $this->alertService->getAlertStats();

        $this->assertEquals(3, $stats['total']);
        $this->assertEquals(2, $stats['active']);
        $this->assertEquals(1, $stats['critical']);
        $this->assertEquals(1, $stats['today']);
    }

    public function test_user_notification_preferences()
    {
        // Create user with critical-only preference
        $user = User::factory()->create([
            'role' => 'admin',
            'email_notifications' => true,
            'sms_notifications' => true,
            'phone' => '+1234567890',
            'notification_critical_only' => true
        ]);

        // Test notification channels
        $channels = $user->getNotificationChannels();
        $this->assertContains('mail', $channels);
        $this->assertContains('vonage', $channels);

        // Test notification preference
        $this->assertTrue($user->shouldReceiveNotification('critical'));
        $this->assertFalse($user->shouldReceiveNotification('warning'));
        $this->assertFalse($user->shouldReceiveNotification('info'));
    }
} 