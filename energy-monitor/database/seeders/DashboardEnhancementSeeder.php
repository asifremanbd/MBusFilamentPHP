<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Gateway;
use App\Models\Device;
use App\Models\UserDeviceAssignment;
use App\Models\UserGatewayAssignment;
use App\Models\UserDashboardConfig;

class DashboardEnhancementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create test users if they don't exist
        $admin = User::firstOrCreate([
            'email' => 'admin@energymonitor.com'
        ], [
            'name' => 'System Administrator',
            'password' => bcrypt('password'),
            'role' => 'admin'
        ]);

        $manager = User::firstOrCreate([
            'email' => 'manager@energymonitor.com'
        ], [
            'name' => 'Energy Manager',
            'password' => bcrypt('password'),
            'role' => 'operator'
        ]);

        $technician = User::firstOrCreate([
            'email' => 'tech@energymonitor.com'
        ], [
            'name' => 'Maintenance Technician',
            'password' => bcrypt('password'),
            'role' => 'operator'
        ]);

        // Get existing gateways and devices
        $gateways = Gateway::all();
        $devices = Device::all();

        if ($gateways->isEmpty() || $devices->isEmpty()) {
            $this->command->info('No gateways or devices found. Please run the main seeder first.');
            return;
        }

        // Assign gateways to users
        // Admin gets all gateways
        foreach ($gateways as $gateway) {
            UserGatewayAssignment::firstOrCreate([
                'user_id' => $admin->id,
                'gateway_id' => $gateway->id
            ], [
                'assigned_by' => $admin->id
            ]);
        }

        // Manager gets first 2 gateways
        foreach ($gateways->take(2) as $gateway) {
            UserGatewayAssignment::firstOrCreate([
                'user_id' => $manager->id,
                'gateway_id' => $gateway->id
            ], [
                'assigned_by' => $admin->id
            ]);
        }

        // Technician gets only first gateway
        if ($gateways->first()) {
            UserGatewayAssignment::firstOrCreate([
                'user_id' => $technician->id,
                'gateway_id' => $gateways->first()->id
            ], [
                'assigned_by' => $admin->id
            ]);
        }

        // Assign devices to users
        // Admin gets all devices
        foreach ($devices as $device) {
            UserDeviceAssignment::firstOrCreate([
                'user_id' => $admin->id,
                'device_id' => $device->id
            ], [
                'assigned_by' => $admin->id
            ]);
        }

        // Manager gets devices from their assigned gateways
        $managerGateways = $gateways->take(2)->pluck('id');
        $managerDevices = $devices->whereIn('gateway_id', $managerGateways);
        foreach ($managerDevices as $device) {
            UserDeviceAssignment::firstOrCreate([
                'user_id' => $manager->id,
                'device_id' => $device->id
            ], [
                'assigned_by' => $admin->id
            ]);
        }

        // Technician gets devices from first gateway only
        if ($gateways->first()) {
            $techDevices = $devices->where('gateway_id', $gateways->first()->id);
            foreach ($techDevices as $device) {
                UserDeviceAssignment::firstOrCreate([
                    'user_id' => $technician->id,
                    'device_id' => $device->id
                ], [
                    'assigned_by' => $admin->id
                ]);
            }
        }

        // Create default dashboard configurations
        $this->createDefaultDashboardConfigs($admin);
        $this->createDefaultDashboardConfigs($manager);
        $this->createDefaultDashboardConfigs($technician);

        $this->command->info('Dashboard enhancement seeder completed successfully.');
    }

    private function createDefaultDashboardConfigs(User $user): void
    {
        // Global dashboard config
        UserDashboardConfig::firstOrCreate([
            'user_id' => $user->id,
            'dashboard_type' => 'global'
        ], [
            'widget_config' => [
                'visibility' => [
                    'system-overview' => true,
                    'cross-gateway-alerts' => true,
                    'top-consuming-gateways' => true,
                    'system-health' => true
                ]
            ],
            'layout_config' => [
                'positions' => [
                    'system-overview' => ['row' => 0, 'col' => 0],
                    'cross-gateway-alerts' => ['row' => 0, 'col' => 6],
                    'top-consuming-gateways' => ['row' => 1, 'col' => 0],
                    'system-health' => ['row' => 1, 'col' => 6]
                ],
                'sizes' => [
                    'system-overview' => ['width' => 6, 'height' => 4],
                    'cross-gateway-alerts' => ['width' => 6, 'height' => 4],
                    'top-consuming-gateways' => ['width' => 6, 'height' => 4],
                    'system-health' => ['width' => 6, 'height' => 4]
                ]
            ]
        ]);

        // Gateway dashboard config
        UserDashboardConfig::firstOrCreate([
            'user_id' => $user->id,
            'dashboard_type' => 'gateway'
        ], [
            'widget_config' => [
                'visibility' => [
                    'gateway-device-list' => true,
                    'real-time-readings' => true,
                    'gateway-stats' => true,
                    'gateway-alerts' => true
                ]
            ],
            'layout_config' => [
                'positions' => [
                    'gateway-device-list' => ['row' => 0, 'col' => 0],
                    'real-time-readings' => ['row' => 0, 'col' => 8],
                    'gateway-stats' => ['row' => 1, 'col' => 0],
                    'gateway-alerts' => ['row' => 1, 'col' => 6]
                ],
                'sizes' => [
                    'gateway-device-list' => ['width' => 8, 'height' => 6],
                    'real-time-readings' => ['width' => 4, 'height' => 6],
                    'gateway-stats' => ['width' => 6, 'height' => 4],
                    'gateway-alerts' => ['width' => 6, 'height' => 4]
                ]
            ]
        ]);
    }
}