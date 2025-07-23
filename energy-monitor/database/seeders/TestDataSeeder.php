<?php

namespace Database\Seeders;

use App\Models\Alert;
use App\Models\Device;
use App\Models\Gateway;
use App\Models\User;
use App\Models\UserDeviceAssignment;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin users
        $admin1 = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'phone' => '+1234567890',
                'email_notifications' => true,
                'sms_notifications' => false,
                'notification_critical_only' => false,
            ]
        );

        $admin2 = User::firstOrCreate(
            ['email' => 'admin-critical@example.com'],
            [
                'name' => 'Admin Critical Only',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'phone' => '+1234567891',
                'email_notifications' => true,
                'sms_notifications' => false,
                'notification_critical_only' => true,
            ]
        );

        // Create operator users
        $operator1 = User::firstOrCreate(
            ['email' => 'operator1@example.com'],
            [
                'name' => 'Operator One',
                'password' => Hash::make('password'),
                'role' => 'operator',
                'phone' => '+1234567892',
                'email_notifications' => true,
                'sms_notifications' => false,
                'notification_critical_only' => false,
            ]
        );

        $operator2 = User::firstOrCreate(
            ['email' => 'operator2@example.com'],
            [
                'name' => 'Operator Two',
                'password' => Hash::make('password'),
                'role' => 'operator',
                'phone' => '+1234567893',
                'email_notifications' => false,
                'sms_notifications' => true,
                'notification_critical_only' => false,
            ]
        );

        // Create gateways
        $gateway1 = Gateway::firstOrCreate(
            ['name' => 'OEMIA Gateway 1'],
            [
                'fixed_ip' => '10.225.57.5',
                'sim_number' => '00467191031035460',
                'gsm_signal' => -70,
                'gnss_location' => '54.7753, 9.9348', // Flintbek, Germany coordinates
            ]
        );

        $gateway2 = Gateway::firstOrCreate(
            ['name' => 'Test Gateway 2'],
            [
                'fixed_ip' => '192.168.1.101',
                'sim_number' => '987654321',
                'gsm_signal' => -65,
                'gnss_location' => '34.0522, -118.2437',
            ]
        );

        // Create devices
        $device1 = Device::firstOrCreate(
            ['name' => 'Device 1', 'gateway_id' => $gateway1->id],
            [
                'slave_id' => 1,
                'location_tag' => 'Building A, Room 101',
            ]
        );

        $device2 = Device::firstOrCreate(
            ['name' => 'Device 2', 'gateway_id' => $gateway1->id],
            [
                'slave_id' => 2,
                'location_tag' => 'Building A, Room 102',
            ]
        );

        $device3 = Device::firstOrCreate(
            ['name' => 'Device 3', 'gateway_id' => $gateway2->id],
            [
                'slave_id' => 1,
                'location_tag' => 'Building B, Room 201',
            ]
        );

        $device4 = Device::firstOrCreate(
            ['name' => 'Device 4', 'gateway_id' => $gateway2->id],
            [
                'slave_id' => 2,
                'location_tag' => 'Building B, Room 202',
            ]
        );

        // Create device assignments
        UserDeviceAssignment::firstOrCreate(
            ['user_id' => $operator1->id, 'device_id' => $device1->id],
            ['assigned_by' => $admin1->id]
        );

        UserDeviceAssignment::firstOrCreate(
            ['user_id' => $operator1->id, 'device_id' => $device2->id],
            ['assigned_by' => $admin1->id]
        );

        UserDeviceAssignment::firstOrCreate(
            ['user_id' => $operator2->id, 'device_id' => $device3->id],
            ['assigned_by' => $admin1->id]
        );

        UserDeviceAssignment::firstOrCreate(
            ['user_id' => $operator2->id, 'device_id' => $device4->id],
            ['assigned_by' => $admin1->id]
        );

        // Create alerts
        $this->createAlerts($device1, $device2, $device3, $device4);
    }

    /**
     * Create sample alerts for testing
     */
    private function createAlerts($device1, $device2, $device3, $device4): void
    {
        // Create alerts for device 1
        Alert::firstOrCreate(
            [
                'device_id' => $device1->id,
                'parameter_name' => 'voltage',
                'value' => 250,
                'severity' => 'warning',
            ],
            [
                'timestamp' => now()->subHours(2),
                'resolved' => false,
                'message' => 'Voltage out of normal range',
            ]
        );

        Alert::firstOrCreate(
            [
                'device_id' => $device1->id,
                'parameter_name' => 'temperature',
                'value' => 85,
                'severity' => 'critical',
            ],
            [
                'timestamp' => now()->subHours(1),
                'resolved' => false,
                'message' => 'Temperature critically high',
            ]
        );

        // Create alerts for device 2
        Alert::firstOrCreate(
            [
                'device_id' => $device2->id,
                'parameter_name' => 'current',
                'value' => 15,
                'severity' => 'info',
            ],
            [
                'timestamp' => now()->subHours(5),
                'resolved' => true,
                'resolved_by' => 1,
                'resolved_at' => now()->subHours(4),
                'message' => 'Current slightly elevated',
            ]
        );

        // Create alerts for device 3
        Alert::firstOrCreate(
            [
                'device_id' => $device3->id,
                'parameter_name' => 'power',
                'value' => 2200,
                'severity' => 'warning',
            ],
            [
                'timestamp' => now()->subHours(3),
                'resolved' => false,
                'message' => 'Power consumption above normal',
            ]
        );

        // Create alerts for device 4
        Alert::firstOrCreate(
            [
                'device_id' => $device4->id,
                'parameter_name' => 'frequency',
                'value' => 48,
                'severity' => 'critical',
            ],
            [
                'timestamp' => now()->subMinutes(30),
                'resolved' => false,
                'message' => 'Frequency critically low',
            ]
        );
    }
}
