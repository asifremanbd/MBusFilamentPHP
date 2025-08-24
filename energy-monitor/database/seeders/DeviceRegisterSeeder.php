<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Gateway;
use App\Models\Device;
use App\Models\Register;

class DeviceRegisterSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        // Get the first gateway to assign devices to
        $gateway = Gateway::first();
        
        if (!$gateway) {
            $this->command->error('No gateway found. Please create a gateway first.');
            return;
        }

        $this->command->info("Adding devices to gateway: {$gateway->name}");

        // Device 1: Energy Meter - Main Distribution board
        $device1 = Device::create([
            'name' => 'Energy Meter',
            'location_tag' => 'Main Distribution board',
            'slave_id' => 1,
            'manufacturer' => 'Eastron',
            'part_number' => 'SDM630MCT V2',
            'serial_number' => '240645050',
            'notes' => 'Phase L1 , L2 ,L3',
            'gateway_id' => $gateway->id,
        ]);

        // Registers for Device 1
        Register::create([
            'device_id' => $device1->id,
            'parameter_name' => 'Voltage (L-N)',
            'register_address' => 40001,
            'data_type' => 'float',
            'unit' => 'Volts',
            'scale' => 1,
            'normal_range' => '220–400 V',
            'critical' => true,
        ]);

        Register::create([
            'device_id' => $device1->id,
            'parameter_name' => 'Current',
            'register_address' => 40003,
            'data_type' => 'float',
            'unit' => 'Amps',
            'scale' => 1,
            'normal_range' => '0–100 A',
            'critical' => true,
        ]);

        Register::create([
            'device_id' => $device1->id,
            'parameter_name' => 'Active Power',
            'register_address' => 40005,
            'data_type' => 'float',
            'unit' => 'kW',
            'scale' => 0.1,
            'normal_range' => '0–20 kW',
            'critical' => true,
        ]);

        // Device 2: AC Unit 1 Meter
        $device2 = Device::create([
            'name' => 'AC Unit 1 Meter',
            'location_tag' => 'External AC Condenser 1',
            'slave_id' => 2,
            'manufacturer' => 'Eastron',
            'part_number' => 'SDM120CT-M',
            'serial_number' => '250276588',
            'notes' => 'Accumulated',
            'gateway_id' => $gateway->id,
        ]);

        Register::create([
            'device_id' => $device2->id,
            'parameter_name' => 'Total Energy (kWh)',
            'register_address' => 40007,
            'data_type' => 'float',
            'unit' => 'kWh',
            'scale' => 1,
            'normal_range' => '0–99999',
            'critical' => true,
        ]);

        // Device 3: Energy Meter - Sockets GF Office
        $device3 = Device::create([
            'name' => 'Energy Meter',
            'location_tag' => 'Sockets GF Office',
            'slave_id' => 3,
            'manufacturer' => 'Eastron',
            'part_number' => 'SDM120CT-M',
            'serial_number' => '250275655',
            'notes' => 'Accumulated',
            'gateway_id' => $gateway->id,
        ]);

        Register::create([
            'device_id' => $device3->id,
            'parameter_name' => 'Power Consumption',
            'register_address' => 40010,
            'data_type' => 'float',
            'unit' => 'kWh',
            'scale' => 1,
            'normal_range' => '0–99999',
            'critical' => true,
        ]);

        // Device 4: Energy Meter - Sockets 1st Floor
        $device4 = Device::create([
            'name' => 'Energy Meter',
            'location_tag' => 'Sockets 1st Floor',
            'slave_id' => 4,
            'manufacturer' => 'Eastron',
            'part_number' => 'SDM120CT-M',
            'serial_number' => '250275818',
            'notes' => 'Accumulated',
            'gateway_id' => $gateway->id,
        ]);

        Register::create([
            'device_id' => $device4->id,
            'parameter_name' => 'Power Consumption',
            'register_address' => 40012,
            'data_type' => 'float',
            'unit' => 'kWh',
            'scale' => 1,
            'normal_range' => '0–99999',
            'critical' => true,
        ]);

        // Device 5: AC Unit 2 Meter
        $device5 = Device::create([
            'name' => 'AC Unit 2 Meter',
            'location_tag' => 'External AC Condenser 2',
            'slave_id' => 5,
            'manufacturer' => 'Eastron',
            'part_number' => 'SDM120CT-M',
            'serial_number' => '250275666',
            'notes' => 'Accumulated',
            'gateway_id' => $gateway->id,
        ]);

        Register::create([
            'device_id' => $device5->id,
            'parameter_name' => 'Total Energy (kWh)',
            'register_address' => 40020,
            'data_type' => 'float',
            'unit' => 'kWh',
            'scale' => 1,
            'normal_range' => '0–99999',
            'critical' => true,
        ]);

        // Device 6: Energy Meter - Sockets Warehouse
        $device6 = Device::create([
            'name' => 'Energy Meter',
            'location_tag' => 'Sockets Warehouse',
            'slave_id' => 6,
            'manufacturer' => 'Eastron',
            'part_number' => 'SDM120CT-M',
            'serial_number' => '250276335',
            'notes' => 'Accumulated',
            'gateway_id' => $gateway->id,
        ]);

        Register::create([
            'device_id' => $device6->id,
            'parameter_name' => 'Total Energy (kWh)',
            'register_address' => 40022,
            'data_type' => 'float',
            'unit' => 'kWh',
            'scale' => 1,
            'normal_range' => '0–99999',
            'critical' => true,
        ]);

        // Device 7: Water Meter Pulse
        $device7 = Device::create([
            'name' => 'Water Meter Pulse',
            'location_tag' => 'Water Mains',
            'slave_id' => 7,
            'manufacturer' => 'Aspar',
            'part_number' => '4DI-M',
            'serial_number' => '307123007',
            'notes' => 'Accumulated',
            'gateway_id' => $gateway->id,
        ]);

        Register::create([
            'device_id' => $device7->id,
            'parameter_name' => 'Usage per Litre',
            'register_address' => 40021,
            'data_type' => 'uint32', // Changed from Pulse to uint32
            'unit' => 'Litre',
            'scale' => 1,
            'normal_range' => '0–',
            'critical' => true,
            'notes' => 'Pulse counter type',
        ]);

        $this->command->info('Successfully created 7 devices with their registers.');
        $this->command->info('Devices created:');
        $this->command->info('1. Energy Meter (Main Distribution board) - 3 registers');
        $this->command->info('2. AC Unit 1 Meter (External AC Condenser 1) - 1 register');
        $this->command->info('3. Energy Meter (Sockets GF Office) - 1 register');
        $this->command->info('4. Energy Meter (Sockets 1st Floor) - 1 register');
        $this->command->info('5. AC Unit 2 Meter (External AC Condenser 2) - 1 register');
        $this->command->info('6. Energy Meter (Sockets Warehouse) - 1 register');
        $this->command->info('7. Water Meter Pulse (Water Mains) - 1 register');
    }
}