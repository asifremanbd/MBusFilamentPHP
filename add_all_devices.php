<?php

require_once 'energy-monitor/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable('energy-monitor');
$dotenv->load();

// Database configuration
$capsule = new Capsule;
$capsule->addConnection([
    'driver' => 'mysql',
    'host' => $_ENV['DB_HOST'],
    'database' => $_ENV['DB_DATABASE'],
    'username' => $_ENV['DB_USERNAME'],
    'password' => $_ENV['DB_PASSWORD'],
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

echo "Adding devices to database...\n";

try {
    // First, ensure we have a gateway (assuming main gateway exists or create one)
    $gateway = Capsule::table('gateways')->where('name', 'Main Gateway')->first();
    if (!$gateway) {
        $gatewayId = Capsule::table('gateways')->insertGetId([
            'name' => 'Main Gateway',
            'fixed_ip' => '192.168.1.1',
            'sim_number' => null,
            'gsm_signal' => null,
            'gnss_location' => null,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    } else {
        $gatewayId = $gateway->id;
    }

    // Device configurations from your table
    $devices = [
        [
            'name' => 'Energy Meter',
            'location_tag' => 'Main Distribution board',
            'slave_id' => 1,
            'registers' => [
                ['parameter_name' => 'Voltage (L-N)', 'register_address' => 40001, 'data_type' => 'float', 'unit' => 'Volts', 'scale' => 1, 'normal_range' => '220-400 V', 'critical' => true, 'notes' => 'Phase L1, L2, L3'],
                ['parameter_name' => 'Current', 'register_address' => 40003, 'data_type' => 'float', 'unit' => 'Amps', 'scale' => 1, 'normal_range' => '0-100 A', 'critical' => true, 'notes' => ''],
                ['parameter_name' => 'Active Power', 'register_address' => 40005, 'data_type' => 'float', 'unit' => 'kW', 'scale' => 0.1, 'normal_range' => '0-20 kW', 'critical' => true, 'notes' => ''],
                ['parameter_name' => 'Total Energy (kWh)', 'register_address' => 40007, 'data_type' => 'float', 'unit' => 'kWh', 'scale' => 1, 'normal_range' => '0-99999', 'critical' => true, 'notes' => 'Accumulated']
            ]
        ],
        [
            'name' => 'Water Meter',
            'location_tag' => 'Inlet Pipe',
            'slave_id' => 2,
            'registers' => [
                ['parameter_name' => 'Flow Rate', 'register_address' => 40010, 'data_type' => 'float', 'unit' => 'L/min', 'scale' => 1, 'normal_range' => '0-500', 'critical' => true, 'notes' => ''],
                ['parameter_name' => 'Total Volume', 'register_address' => 40012, 'data_type' => 'float', 'unit' => 'Liters', 'scale' => 1, 'normal_range' => '0-100000', 'critical' => true, 'notes' => 'Accumulated']
            ]
        ],
        [
            'name' => 'AC Unit 1 Meter',
            'location_tag' => 'External Condenser 1',
            'slave_id' => 3,
            'registers' => [
                ['parameter_name' => 'Power Consumption', 'register_address' => 40020, 'data_type' => 'int', 'unit' => 'Watts', 'scale' => 1, 'normal_range' => '0-2000', 'critical' => true, 'notes' => ''],
                ['parameter_name' => 'Runtime', 'register_address' => 40022, 'data_type' => 'int', 'unit' => 'Hours', 'scale' => 1, 'normal_range' => '0-8760', 'critical' => false, 'notes' => 'Optional']
            ]
        ],
        [
            'name' => 'AC Unit 2 Meter',
            'location_tag' => 'External Condenser 2',
            'slave_id' => 4,
            'registers' => [
                ['parameter_name' => 'Power Consumption', 'register_address' => 40020, 'data_type' => 'int', 'unit' => 'Watts', 'scale' => 1, 'normal_range' => '0-2000', 'critical' => true, 'notes' => ''],
                ['parameter_name' => 'Runtime', 'register_address' => 40022, 'data_type' => 'int', 'unit' => 'Hours', 'scale' => 1, 'normal_range' => '0-8760', 'critical' => false, 'notes' => 'Optional']
            ]
        ],
        [
            'name' => 'AC Unit 3 Meter',
            'location_tag' => 'External Condenser 3',
            'slave_id' => 5,
            'registers' => [
                ['parameter_name' => 'Power Consumption', 'register_address' => 40020, 'data_type' => 'int', 'unit' => 'Watts', 'scale' => 1, 'normal_range' => '0-2000', 'critical' => true, 'notes' => ''],
                ['parameter_name' => 'Runtime', 'register_address' => 40022, 'data_type' => 'int', 'unit' => 'Hours', 'scale' => 1, 'normal_range' => '0-8760', 'critical' => false, 'notes' => 'Optional']
            ]
        ],
        [
            'name' => 'Heater 1st floor 1',
            'location_tag' => '1st Floor',
            'slave_id' => 6,
            'registers' => [
                ['parameter_name' => 'Power Usage', 'register_address' => 40030, 'data_type' => 'float', 'unit' => 'kW', 'scale' => 1, 'normal_range' => '0-3', 'critical' => true, 'notes' => '']
            ]
        ],
        [
            'name' => 'Heater 1st floor 2',
            'location_tag' => '1st floor',
            'slave_id' => 7,
            'registers' => [
                ['parameter_name' => 'Power Usage', 'register_address' => 40030, 'data_type' => 'float', 'unit' => 'kW', 'scale' => 1, 'normal_range' => '0-3', 'critical' => true, 'notes' => '']
            ]
        ],
        [
            'name' => 'Router Power Meter',
            'location_tag' => 'Server Rack',
            'slave_id' => 8, // Changed from 6 to avoid conflict
            'registers' => [
                ['parameter_name' => 'Voltage Input', 'register_address' => 40035, 'data_type' => 'float', 'unit' => 'Volts', 'scale' => 1, 'normal_range' => '12-24', 'critical' => false, 'notes' => 'For backup power tracking']
            ]
        ]
    ];

    foreach ($devices as $deviceData) {
        echo "Adding device: {$deviceData['name']}\n";
        
        // Check if device already exists
        $existingDevice = Capsule::table('devices')
            ->where('name', $deviceData['name'])
            ->where('slave_id', $deviceData['slave_id'])
            ->first();

        if ($existingDevice) {
            echo "  Device already exists, updating...\n";
            $deviceId = $existingDevice->id;
            
            // Update device
            Capsule::table('devices')
                ->where('id', $deviceId)
                ->update([
                    'location_tag' => $deviceData['location_tag'],
                    'updated_at' => now()
                ]);
        } else {
            // Insert new device
            $deviceId = Capsule::table('devices')->insertGetId([
                'name' => $deviceData['name'],
                'slave_id' => $deviceData['slave_id'],
                'location_tag' => $deviceData['location_tag'],
                'gateway_id' => $gatewayId,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            echo "  Device created with ID: $deviceId\n";
        }

        // Add registers
        foreach ($deviceData['registers'] as $registerData) {
            $existingRegister = Capsule::table('registers')
                ->where('device_id', $deviceId)
                ->where('register_address', $registerData['register_address'])
                ->where('parameter_name', $registerData['parameter_name'])
                ->first();

            if ($existingRegister) {
                echo "    Register {$registerData['parameter_name']} already exists, updating...\n";
                Capsule::table('registers')
                    ->where('id', $existingRegister->id)
                    ->update([
                        'data_type' => $registerData['data_type'],
                        'unit' => $registerData['unit'],
                        'scale' => $registerData['scale'],
                        'normal_range' => $registerData['normal_range'],
                        'critical' => $registerData['critical'],
                        'notes' => $registerData['notes'],
                        'updated_at' => now()
                    ]);
            } else {
                Capsule::table('registers')->insert([
                    'device_id' => $deviceId,
                    'parameter_name' => $registerData['parameter_name'],
                    'register_address' => $registerData['register_address'],
                    'data_type' => $registerData['data_type'],
                    'unit' => $registerData['unit'],
                    'scale' => $registerData['scale'],
                    'normal_range' => $registerData['normal_range'],
                    'critical' => $registerData['critical'],
                    'notes' => $registerData['notes'],
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                echo "    Register {$registerData['parameter_name']} added\n";
            }
        }
    }

    echo "\nAll devices and registers have been added successfully!\n";
    
    // Display summary
    $deviceCount = Capsule::table('devices')->count();
    $registerCount = Capsule::table('registers')->count();
    echo "Total devices in database: $deviceCount\n";
    echo "Total registers in database: $registerCount\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

function now() {
    return date('Y-m-d H:i:s');
}