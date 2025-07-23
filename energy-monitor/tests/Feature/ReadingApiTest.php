<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Gateway;
use App\Models\Register;
use App\Models\Reading;
use App\Models\Alert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_store_reading_successfully()
    {
        // Create test data
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

        // Test payload
        $payload = [
            'device_id' => $device->id,
            'parameter' => 'Voltage (L-N)',
            'value' => 228.6,
            'timestamp' => '2025-07-08T16:00:00Z'
        ];

        // Make API call
        $response = $this->postJson('/api/readings', $payload);

        // Assert response
        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'reading_id',
                    'device_id',
                    'parameter',
                    'value',
                    'timestamp'
                ]
            ]);

        // Assert database
        $this->assertDatabaseHas('readings', [
            'device_id' => $device->id,
            'register_id' => $register->id,
            'value' => 228.6
        ]);
    }

    public function test_creates_alert_when_value_out_of_range()
    {
        // Create test data
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
            'critical' => true,
            'notes' => 'Line to Neutral Voltage'
        ]);

        // Test payload with out-of-range value
        $payload = [
            'device_id' => $device->id,
            'parameter' => 'Voltage (L-N)',
            'value' => 260.0, // Out of range (220-240)
            'timestamp' => '2025-07-08T16:00:00Z'
        ];

        // Make API call
        $response = $this->postJson('/api/readings', $payload);

        // Assert response
        $response->assertStatus(201);

        // Assert alert was created
        $this->assertDatabaseHas('alerts', [
            'device_id' => $device->id,
            'parameter_name' => 'Voltage (L-N)',
            'value' => 260.0,
            'severity' => 'critical',
            'resolved' => false
        ]);
    }

    public function test_creates_alert_for_off_hours_reading()
    {
        // Create test data
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

        // Test payload with off-hours timestamp (11 PM)
        $payload = [
            'device_id' => $device->id,
            'parameter' => 'Voltage (L-N)',
            'value' => 230.0,
            'timestamp' => '2025-07-08T23:00:00Z'
        ];

        // Make API call
        $response = $this->postJson('/api/readings', $payload);

        // Assert response
        $response->assertStatus(201);

        // Assert alert was created for off-hours
        $this->assertDatabaseHas('alerts', [
            'device_id' => $device->id,
            'parameter_name' => 'Voltage (L-N)',
            'severity' => 'info',
            'resolved' => false
        ]);
    }

    public function test_validation_fails_for_invalid_payload()
    {
        $payload = [
            'device_id' => 'invalid',
            'parameter' => '',
            'value' => 'not_a_number',
            'timestamp' => 'invalid_date'
        ];

        $response = $this->postJson('/api/readings', $payload);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
                'errors'
            ]);
    }
} 