<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Gateway;
use App\Models\Device;
use App\Models\Register;
use App\Models\Reading;
use App\Models\Alert;
use App\Models\User;
use Carbon\Carbon;

class DemoDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create demo user
        $user = User::firstOrCreate([
            'email' => 'admin@energymonitor.com'
        ], [
            'name' => 'System Administrator',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'email_notifications' => true,
            'sms_notifications' => false,
            'notification_critical_only' => false,
        ]);

        // Create demo gateways
        $gateway1 = Gateway::create([
            'name' => 'Site A - Main Building',
            'fixed_ip' => '192.168.100.10',
            'sim_number' => '+1234567890',
            'gsm_signal' => -68,
            'gnss_location' => '40.7128,-74.0060', // New York coordinates
        ]);

        $gateway2 = Gateway::create([
            'name' => 'Site B - Warehouse',
            'fixed_ip' => '192.168.100.20',
            'sim_number' => '+1234567891',
            'gsm_signal' => -75,
            'gnss_location' => '34.0522,-118.2437', // Los Angeles coordinates
        ]);

        // Create devices for Gateway 1
        $energyMeter = Device::create([
            'name' => 'Energy Meter #1',
            'slave_id' => 1,
            'location_tag' => 'Main Panel',
            'gateway_id' => $gateway1->id,
        ]);

        $waterMeter = Device::create([
            'name' => 'Water Meter',
            'slave_id' => 2,
            'location_tag' => 'Basement',
            'gateway_id' => $gateway1->id,
        ]);

        $acUnit1 = Device::create([
            'name' => 'AC Unit #1',
            'slave_id' => 3,
            'location_tag' => 'Floor 1',
            'gateway_id' => $gateway1->id,
        ]);

        // Create devices for Gateway 2
        $energyMeter2 = Device::create([
            'name' => 'Energy Meter #2',
            'slave_id' => 1,
            'location_tag' => 'Main Panel',
            'gateway_id' => $gateway2->id,
        ]);

        $heater1 = Device::create([
            'name' => 'Heater #1',
            'slave_id' => 6,
            'location_tag' => 'North Wing',
            'gateway_id' => $gateway2->id,
        ]);

        // Create registers for Energy Meter #1
        $registers = [
            // Energy Meter registers
            [
                'device_id' => $energyMeter->id,
                'parameter_name' => 'Voltage (L-N)',
                'register_address' => 40001,
                'data_type' => 'float',
                'unit' => 'V',
                'scale' => 1.0,
                'normal_range' => '220-240',
                'critical' => false,
                'notes' => 'Line to Neutral Voltage',
            ],
            [
                'device_id' => $energyMeter->id,
                'parameter_name' => 'Current',
                'register_address' => 40002,
                'data_type' => 'float',
                'unit' => 'A',
                'scale' => 1.0,
                'normal_range' => '0-50',
                'critical' => false,
                'notes' => 'Line Current',
            ],
            [
                'device_id' => $energyMeter->id,
                'parameter_name' => 'Active Power',
                'register_address' => 40003,
                'data_type' => 'float',
                'unit' => 'kW',
                'scale' => 0.001,
                'normal_range' => '0-25',
                'critical' => true,
                'notes' => 'Active Power Consumption',
            ],
            [
                'device_id' => $energyMeter->id,
                'parameter_name' => 'Total Energy',
                'register_address' => 40004,
                'data_type' => 'float',
                'unit' => 'kWh',
                'scale' => 0.01,
                'normal_range' => '0-10000',
                'critical' => false,
                'notes' => 'Cumulative Energy',
            ],

            // Water Meter registers
            [
                'device_id' => $waterMeter->id,
                'parameter_name' => 'Flow Rate',
                'register_address' => 40001,
                'data_type' => 'float',
                'unit' => 'L/min',
                'scale' => 0.1,
                'normal_range' => '0-100',
                'critical' => false,
                'notes' => 'Current Flow Rate',
            ],
            [
                'device_id' => $waterMeter->id,
                'parameter_name' => 'Total Volume',
                'register_address' => 40002,
                'data_type' => 'float',
                'unit' => 'L',
                'scale' => 1.0,
                'normal_range' => '0-50000',
                'critical' => false,
                'notes' => 'Cumulative Water Volume',
            ],

            // AC Unit registers
            [
                'device_id' => $acUnit1->id,
                'parameter_name' => 'Power Consumption',
                'register_address' => 40001,
                'data_type' => 'float',
                'unit' => 'kW',
                'scale' => 0.001,
                'normal_range' => '0-5',
                'critical' => false,
                'notes' => 'AC Power Consumption',
            ],
            [
                'device_id' => $acUnit1->id,
                'parameter_name' => 'Runtime',
                'register_address' => 40002,
                'data_type' => 'int',
                'unit' => 'hours',
                'scale' => 1.0,
                'normal_range' => '0-8760',
                'critical' => false,
                'notes' => 'Total Runtime Hours',
            ],

            // Gateway 2 devices
            [
                'device_id' => $energyMeter2->id,
                'parameter_name' => 'Voltage (L-N)',
                'register_address' => 40001,
                'data_type' => 'float',
                'unit' => 'V',
                'scale' => 1.0,
                'normal_range' => '220-240',
                'critical' => false,
                'notes' => 'Line to Neutral Voltage',
            ],
            [
                'device_id' => $energyMeter2->id,
                'parameter_name' => 'Active Power',
                'register_address' => 40003,
                'data_type' => 'float',
                'unit' => 'kW',
                'scale' => 0.001,
                'normal_range' => '0-30',
                'critical' => true,
                'notes' => 'Active Power Consumption',
            ],
            [
                'device_id' => $heater1->id,
                'parameter_name' => 'Power Usage',
                'register_address' => 40001,
                'data_type' => 'float',
                'unit' => 'kW',
                'scale' => 0.001,
                'normal_range' => '0-3',
                'critical' => false,
                'notes' => 'Heater Power Usage',
            ],
        ];

        foreach ($registers as $registerData) {
            Register::create($registerData);
        }

        // Generate demo readings for the last 48 hours
        $this->generateDemoReadings();

        // Create some demo alerts
        $this->createDemoAlerts();

        $this->command->info('Demo data seeded successfully!');
        $this->command->info('Admin user: admin@energymonitor.com / password');
    }

    private function generateDemoReadings(): void
    {
        $registers = Register::all();
        $now = Carbon::now();

        // Generate readings for the last 48 hours, every 30 minutes
        for ($i = 0; $i < 96; $i++) { // 48 hours * 2 (every 30 min)
            $timestamp = $now->copy()->subMinutes($i * 30);

            foreach ($registers as $register) {
                $baseValue = $this->getBaseValue($register->parameter_name);
                $variation = $this->getRandomVariation($register->parameter_name);
                $value = $baseValue + $variation;

                // Add some realistic patterns
                if (in_array($register->parameter_name, ['Active Power', 'Power Consumption', 'Power Usage'])) {
                    // Power consumption varies by time of day
                    $hour = $timestamp->hour;
                    if ($hour >= 22 || $hour <= 6) {
                        $value *= 0.3; // Lower consumption at night
                    } elseif ($hour >= 9 && $hour <= 17) {
                        $value *= 1.2; // Higher consumption during work hours
                    }
                }

                Reading::create([
                    'device_id' => $register->device_id,
                    'register_id' => $register->id,
                    'value' => round($value, 2),
                    'timestamp' => $timestamp,
                ]);
            }
        }
    }

    private function createDemoAlerts(): void
    {
        // Create some demo alerts for testing
        $devices = Device::all();

        foreach ($devices->take(2) as $device) {
            // Create a resolved alert
            Alert::create([
                'device_id' => $device->id,
                'parameter_name' => 'Voltage (L-N)',
                'value' => 250.5,
                'severity' => 'warning',
                'timestamp' => Carbon::now()->subHours(2),
                'resolved' => true,
                'resolved_by' => 1,
                'resolved_at' => Carbon::now()->subHour(),
                'message' => 'Voltage exceeded normal range (220-240V)',
            ]);

            // Create an active alert
            if ($device->id <= 2) {
                Alert::create([
                    'device_id' => $device->id,
                    'parameter_name' => 'Active Power',
                    'value' => 28.5,
                    'severity' => $device->id === 1 ? 'critical' : 'warning',
                    'timestamp' => Carbon::now()->subMinutes(15),
                    'resolved' => false,
                    'message' => 'Power consumption is ' . ($device->id === 1 ? 'critically high' : 'above normal range'),
                ]);
            }
        }
    }

    private function getBaseValue(string $parameter): float
    {
        return match ($parameter) {
            'Voltage (L-N)' => 230.0,
            'Current' => 15.0,
            'Active Power' => 12.0,
            'Total Energy' => 1500.0,
            'Flow Rate' => 25.0,
            'Total Volume' => 15000.0,
            'Power Consumption' => 2.5,
            'Runtime' => 2400.0,
            'Power Usage' => 1.8,
            default => 10.0,
        };
    }

    private function getRandomVariation(string $parameter): float
    {
        $maxVariation = match ($parameter) {
            'Voltage (L-N)' => 5.0,
            'Current' => 3.0,
            'Active Power' => 4.0,
            'Total Energy' => 0.5,
            'Flow Rate' => 8.0,
            'Total Volume' => 1.0,
            'Power Consumption' => 1.0,
            'Runtime' => 0.1,
            'Power Usage' => 0.5,
            default => 2.0,
        };

        return (rand(-100, 100) / 100) * $maxVariation;
    }
} 