<?php

namespace Tests\Unit;

use App\Models\Gateway;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class GatewayRTUMethodsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test isRTUGateway method returns true for Teltonika RUT956 gateways
     */
    public function test_is_rtu_gateway_returns_true_for_teltonika_rut956(): void
    {
        $gateway = Gateway::factory()->create([
            'gateway_type' => 'teltonika_rut956'
        ]);

        $this->assertTrue($gateway->isRTUGateway());
    }

    /**
     * Test isRTUGateway method returns false for non-RTU gateways
     */
    public function test_is_rtu_gateway_returns_false_for_generic_gateways(): void
    {
        $gateway = Gateway::factory()->create([
            'gateway_type' => 'generic'
        ]);

        $this->assertFalse($gateway->isRTUGateway());
    }

    /**
     * Test isRTUGateway method returns false for different gateway type
     */
    public function test_is_rtu_gateway_returns_false_for_different_gateway_type(): void
    {
        $gateway = Gateway::factory()->create([
            'gateway_type' => 'other_type'
        ]);

        $this->assertFalse($gateway->isRTUGateway());
    }

    /**
     * Test getSystemHealthScore returns 100 for optimal conditions
     */
    public function test_get_system_health_score_returns_100_for_optimal_conditions(): void
    {
        $gateway = Gateway::factory()->create([
            'cpu_load' => 50.0,
            'memory_usage' => 60.0,
            'communication_status' => 'online'
        ]);

        $this->assertEquals(100, $gateway->getSystemHealthScore());
    }

    /**
     * Test getSystemHealthScore deducts points for high CPU usage
     */
    public function test_get_system_health_score_deducts_points_for_high_cpu(): void
    {
        $gateway = Gateway::factory()->create([
            'cpu_load' => 85.0,
            'memory_usage' => 60.0,
            'communication_status' => 'online'
        ]);

        $this->assertEquals(80, $gateway->getSystemHealthScore());
    }

    /**
     * Test getSystemHealthScore deducts points for high memory usage
     */
    public function test_get_system_health_score_deducts_points_for_high_memory(): void
    {
        $gateway = Gateway::factory()->create([
            'cpu_load' => 50.0,
            'memory_usage' => 95.0,
            'communication_status' => 'online'
        ]);

        $this->assertEquals(70, $gateway->getSystemHealthScore());
    }

    /**
     * Test getSystemHealthScore deducts points for communication issues
     */
    public function test_get_system_health_score_deducts_points_for_communication_issues(): void
    {
        $gateway = Gateway::factory()->create([
            'cpu_load' => 50.0,
            'memory_usage' => 60.0,
            'communication_status' => 'offline'
        ]);

        $this->assertEquals(50, $gateway->getSystemHealthScore());
    }

    /**
     * Test getSystemHealthScore returns 0 for worst case scenario
     */
    public function test_get_system_health_score_returns_zero_for_worst_case(): void
    {
        $gateway = Gateway::factory()->create([
            'cpu_load' => 95.0,
            'memory_usage' => 95.0,
            'communication_status' => 'offline'
        ]);

        $this->assertEquals(0, $gateway->getSystemHealthScore());
    }

    /**
     * Test getSignalQualityStatus returns excellent for strong signal
     */
    public function test_get_signal_quality_status_returns_excellent_for_strong_signal(): void
    {
        $gateway = Gateway::factory()->create([
            'rssi' => -60
        ]);

        $this->assertEquals('excellent', $gateway->getSignalQualityStatus());
    }

    /**
     * Test getSignalQualityStatus returns good for moderate signal
     */
    public function test_get_signal_quality_status_returns_good_for_moderate_signal(): void
    {
        $gateway = Gateway::factory()->create([
            'rssi' => -75
        ]);

        $this->assertEquals('good', $gateway->getSignalQualityStatus());
    }

    /**
     * Test getSignalQualityStatus returns fair for weak signal
     */
    public function test_get_signal_quality_status_returns_fair_for_weak_signal(): void
    {
        $gateway = Gateway::factory()->create([
            'rssi' => -90
        ]);

        $this->assertEquals('fair', $gateway->getSignalQualityStatus());
    }

    /**
     * Test getSignalQualityStatus returns poor for very weak signal
     */
    public function test_get_signal_quality_status_returns_poor_for_very_weak_signal(): void
    {
        $gateway = Gateway::factory()->create([
            'rssi' => -110
        ]);

        $this->assertEquals('poor', $gateway->getSignalQualityStatus());
    }

    /**
     * Test getSignalQualityStatus returns unknown for null RSSI
     */
    public function test_get_signal_quality_status_returns_unknown_for_null_rssi(): void
    {
        $gateway = Gateway::factory()->create([
            'rssi' => null
        ]);

        $this->assertEquals('unknown', $gateway->getSignalQualityStatus());
    }

    /**
     * Test that RTU-specific fields are fillable
     */
    public function test_rtu_specific_fields_are_fillable(): void
    {
        $rtuData = [
            'name' => 'Test RTU Gateway',
            'fixed_ip' => '192.168.1.100',
            'gateway_type' => 'teltonika_rut956',
            'wan_ip' => '203.0.113.1',
            'sim_iccid' => '8944501234567890123',
            'sim_apn' => 'internet.provider.com',
            'sim_operator' => 'Test Operator',
            'cpu_load' => 45.5,
            'memory_usage' => 67.8,
            'uptime_hours' => 168,
            'rssi' => -75,
            'rsrp' => -105,
            'rsrq' => -10,
            'sinr' => 15,
            'di1_status' => true,
            'di2_status' => false,
            'do1_status' => true,
            'do2_status' => false,
            'analog_input_voltage' => 5.25,
            'communication_status' => 'online'
        ];

        $gateway = Gateway::create($rtuData);

        $this->assertDatabaseHas('gateways', [
            'name' => 'Test RTU Gateway',
            'gateway_type' => 'teltonika_rut956',
            'wan_ip' => '203.0.113.1',
            'sim_iccid' => '8944501234567890123',
            'cpu_load' => 45.5,
            'memory_usage' => 67.8,
            'communication_status' => 'online'
        ]);
    }

    /**
     * Test that casts work correctly for RTU fields
     */
    public function test_rtu_field_casts_work_correctly(): void
    {
        $gateway = Gateway::factory()->create([
            'cpu_load' => '75.5',
            'memory_usage' => '82.3',
            'uptime_hours' => '240',
            'analog_input_voltage' => '7.85',
            'di1_status' => 1,
            'di2_status' => 0,
            'do1_status' => true,
            'do2_status' => false
        ]);

        $this->assertIsFloat($gateway->cpu_load);
        $this->assertIsFloat($gateway->memory_usage);
        $this->assertIsInt($gateway->uptime_hours);
        $this->assertIsFloat($gateway->analog_input_voltage);
        $this->assertIsBool($gateway->di1_status);
        $this->assertIsBool($gateway->di2_status);
        $this->assertIsBool($gateway->do1_status);
        $this->assertIsBool($gateway->do2_status);
        
        $this->assertEquals(75.5, $gateway->cpu_load);
        $this->assertEquals(82.3, $gateway->memory_usage);
        $this->assertEquals(240, $gateway->uptime_hours);
        $this->assertEquals(7.85, $gateway->analog_input_voltage);
        $this->assertTrue($gateway->di1_status);
        $this->assertFalse($gateway->di2_status);
        $this->assertTrue($gateway->do1_status);
        $this->assertFalse($gateway->do2_status);
    }
}
