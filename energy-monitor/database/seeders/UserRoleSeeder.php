<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Device;
use App\Models\UserDeviceAssignment;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin users
        $admin1 = User::firstOrCreate(
            ['email' => 'admin@energymonitor.com'],
            [
                'name' => 'System Administrator',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'phone' => '+1234567890',
                'email_notifications' => true,
                'sms_notifications' => false,
                'notification_critical_only' => false,
            ]
        );

        $admin2 = User::firstOrCreate(
            ['email' => 'admin-critical@energymonitor.com'],
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
            ['email' => 'operator1@energymonitor.com'],
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
            ['email' => 'operator2@energymonitor.com'],
            [
                'name' => 'Operator Two',
                'password' => Hash::make('password'),
                'role' => 'operator',
                'phone' => '+1234567893',
                'email_notifications' => false,
                'sms_notifications' => false,
                'notification_critical_only' => false,
            ]
        );

        $operator3 = User::firstOrCreate(
            ['email' => 'operator-critical@energymonitor.com'],
            [
                'name' => 'Operator Critical',
                'password' => Hash::make('password'),
                'role' => 'operator',
                'phone' => '+1234567894',
                'email_notifications' => true,
                'sms_notifications' => false,
                'notification_critical_only' => true,
            ]
        );

        // Assign devices to operators if devices exist
        $devices = Device::all();
        if ($devices->count() > 0) {
            // Assign first half of devices to operator1
            $firstHalf = $devices->take(ceil($devices->count() / 2));
            foreach ($firstHalf as $device) {
                UserDeviceAssignment::create([
                    'user_id' => $operator1->id,
                    'device_id' => $device->id,
                    'assigned_by' => $admin1->id,
                ]);
            }

            // Assign second half to operator2
            $secondHalf = $devices->skip(ceil($devices->count() / 2));
            foreach ($secondHalf as $device) {
                UserDeviceAssignment::create([
                    'user_id' => $operator2->id,
                    'device_id' => $device->id,
                    'assigned_by' => $admin1->id,
                ]);
            }

            // Assign some devices to operator3 as well (overlap for testing)
            $someDevices = $devices->take(2);
            foreach ($someDevices as $device) {
                UserDeviceAssignment::create([
                    'user_id' => $operator3->id,
                    'device_id' => $device->id,
                    'assigned_by' => $admin1->id,
                ]);
            }
        }
    }
}
