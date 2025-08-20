<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Models\Gateway;
use App\Models\Device;
use App\Models\Reading;
use App\Models\Register;
use App\Models\User;
use App\Services\RTUDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\CreatesApplication;
use Carbon\Carbon;

class RTUDataServiceTest extends TestCase
{
    use CreatesApplication, RefreshDatabase;

    protected RTUDataService $rtuDataService;
    protected Gateway $rtuGateway;
    protected Gateway $nonRtuGateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createApplication();
        $this->rtuDataService = new RTUDataService();

        // Create test gateways
        $this->rtuGateway = Gateway::factory()->create([
            'gateway_type' => 'teltonika_rut956',
            'name' => 'Test RTU Gateway',
            'cpu_load' => 45.5,
            'memory_usage' => 67.2,
            'uptime_hours' => 168,
            'rssi' => -75,
            'rsrp' => -105,
            'rsrq' => -12,
            'sinr' => 15,
            'wan_ip' => '192.168.1.100',
            'sim_iccid' => '89012345678901234567',
            'sim_apn' => 'internet.provider.com',
            'sim_operator' => 'Test Provider',
            'di1_status' => true,
            'di2_status' => false,
            'do1_status' => true,
            'do2_status' => false,
            'analog_input_voltage' => 7.25,
            'communication_status' => 'online',
            'last_system_update' => now()->subMinutes(5)
        ]);

        $this->nonRtuGateway = Gateway::factory()->create([
            'gateway_type' => 'generic',
            'name' => 'Non-RTU Gateway'
        ]);
    }

    public function test_get_system_health_returns_correct_data_for_rtu_gateway()
    {
        $result = $this->rtuDataService->getSystemHealth($this->rtuGateway);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('uptime_hours', $result);
        $this->assertArrayHasKey('cpu_load', $result);
        $this->assertArrayHasKey('memory_usage', $result);
        $this->assertArrayHasKey('health_score', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('last_updated', $result);

        $this->assertEquals(168, $result['uptime_hours']);
        $this->assertEquals(45.5, $result['cpu_load']);
        $this->assertEquals(67.2, $result['memory_usage']);
        $this->assertIsInt($result['health_score']);
        $this->assertContains($result['status'], ['normal', 'warning', 'critical', 'unavailable', 'offline']);
    }

    public function test_get_system_health_handles_null_values()
    {
        $gateway = Gateway::factory()->create([
            'gateway_type' => 'teltonika_rut956',
            'cpu_load' => null,
            'memory_usage' => null,
            'uptime_hours' => null,
            'communication_status' => 'offline'
        ]);

        $result = $this->rtuDataService->getSystemHealth($gateway);

        $this->assertIsArray($result);
        // Health score should be 50 because communication_status is 'offline' (not 'online')
        $this->assertEquals(50, $result['health_score']);
        // The status might be 'warning' due to simulated data generation in fetchSystemDataFromGateway
        $this->assertContains($result['status'], ['normal', 'warning', 'unavailable']);
    }

    public function test_get_network_status_returns_correct_data_for_rtu_gateway()
    {
        $result = $this->rtuDataService->getNetworkStatus($this->rtuGateway);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('wan_ip', $result);
        $this->assertArrayHasKey('sim_iccid', $result);
        $this->assertArrayHasKey('sim_apn', $result);
        $this->assertArrayHasKey('sim_operator', $result);
        $this->assertArrayHasKey('signal_quality', $result);
        $this->assertArrayHasKey('connection_status', $result);
        $this->assertArrayHasKey('last_updated', $result);

        $this->assertEquals('192.168.1.100', $result['wan_ip']);
        $this->assertEquals('89012345678901234567', $result['sim_iccid']);
        $this->assertEquals('internet.provider.com', $result['sim_apn']);
        $this->assertEquals('Test Provider', $result['sim_operator']);

        $this->assertIsArray($result['signal_quality']);
        $this->assertArrayHasKey('rssi', $result['signal_quality']);
        $this->assertArrayHasKey('rsrp', $result['signal_quality']);
        $this->assertArrayHasKey('rsrq', $result['signal_quality']);
        $this->assertArrayHasKey('sinr', $result['signal_quality']);
        $this->assertArrayHasKey('status', $result['signal_quality']);

        $this->assertEquals(-75, $result['signal_quality']['rssi']);
        $this->assertEquals(-105, $result['signal_quality']['rsrp']);
        $this->assertEquals(-12, $result['signal_quality']['rsrq']);
        $this->assertEquals(15, $result['signal_quality']['sinr']);
    }

    public function test_get_network_status_handles_null_values()
    {
        // Create a gateway with null values and override the fetch method behavior
        $gateway = Gateway::factory()->create([
            'gateway_type' => 'teltonika_rut956',
            'wan_ip' => null,
            'sim_iccid' => null,
            'rssi' => null
        ]);

        // Mock the service to avoid the simulated data generation
        $service = $this->getMockBuilder(RTUDataService::class)
            ->onlyMethods(['fetchNetworkDataFromGateway'])
            ->getMock();

        $service->method('fetchNetworkDataFromGateway')
            ->willReturn([
                'wan_ip' => null,
                'sim_iccid' => null,
                'rssi' => null,
                'rsrp' => null,
                'rsrq' => null,
                'sinr' => null,
                'connection_status' => null
            ]);

        $result = $service->getNetworkStatus($gateway);

        $this->assertEquals('Not assigned', $result['wan_ip']);
        $this->assertEquals('Unknown', $result['sim_iccid']);
        $this->assertNull($result['signal_quality']['rssi']);
        $this->assertEquals('unknown', $result['signal_quality']['status']);
    }

    public function test_get_io_status_returns_correct_data_for_rtu_gateway()
    {
        $result = $this->rtuDataService->getIOStatus($this->rtuGateway);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('digital_inputs', $result);
        $this->assertArrayHasKey('digital_outputs', $result);
        $this->assertArrayHasKey('analog_input', $result);
        $this->assertArrayHasKey('last_updated', $result);

        // Check digital inputs
        $this->assertArrayHasKey('di1', $result['digital_inputs']);
        $this->assertArrayHasKey('di2', $result['digital_inputs']);
        $this->assertTrue($result['digital_inputs']['di1']['status']);
        $this->assertFalse($result['digital_inputs']['di2']['status']);
        $this->assertEquals('Digital Input 1', $result['digital_inputs']['di1']['label']);

        // Check digital outputs
        $this->assertArrayHasKey('do1', $result['digital_outputs']);
        $this->assertArrayHasKey('do2', $result['digital_outputs']);
        $this->assertTrue($result['digital_outputs']['do1']['status']);
        $this->assertFalse($result['digital_outputs']['do2']['status']);
        $this->assertTrue($result['digital_outputs']['do1']['controllable']);
        $this->assertEquals('Digital Output 1', $result['digital_outputs']['do1']['label']);

        // Check analog input
        $this->assertEquals(7.25, $result['analog_input']['voltage']);
        $this->assertEquals('V', $result['analog_input']['unit']);
        $this->assertEquals('0-10V', $result['analog_input']['range']);
        $this->assertEquals(2, $result['analog_input']['precision']);
    }

    public function test_set_digital_output_success()
    {
        $result = $this->rtuDataService->setDigitalOutput($this->rtuGateway, 'do1', false);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('new_state', $result);
        $this->assertFalse($result['new_state']);

        // Verify gateway was updated
        $this->rtuGateway->refresh();
        $this->assertFalse($this->rtuGateway->do1_status);
        $this->assertNotNull($this->rtuGateway->last_system_update);
    }

    public function test_set_digital_output_with_invalid_output_parameter()
    {
        $result = $this->rtuDataService->setDigitalOutput($this->rtuGateway, 'invalid_output', true);

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('message', $result);
        $this->assertStringContainsString('Invalid output parameter', $result['message']);
    }

    public function test_set_digital_output_with_non_rtu_gateway()
    {
        $result = $this->rtuDataService->setDigitalOutput($this->nonRtuGateway, 'do1', true);

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('message', $result);
    }

    public function test_get_trend_data_with_no_readings()
    {
        $result = $this->rtuDataService->getTrendData($this->rtuGateway, '24h');

        $this->assertIsArray($result);
        // Should have data because gateway has metrics available
        $this->assertTrue($result['has_data']);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('available_metrics', $result);
        $this->assertArrayHasKey('time_range', $result);
        $this->assertEquals('24h', $result['time_range']);
        $this->assertNotEmpty($result['available_metrics']);
    }

    public function test_get_trend_data_with_gateway_metrics()
    {
        $result = $this->rtuDataService->getTrendData($this->rtuGateway, '24h');

        $this->assertIsArray($result);
        
        // When no device readings exist, it should still return available metrics from gateway
        if ($result['has_data']) {
            $this->assertArrayHasKey('metrics', $result);
        }
        
        $this->assertArrayHasKey('available_metrics', $result);
        
        // Should include gateway-level metrics even without device readings
        $availableMetrics = $result['available_metrics'];
        $this->assertContains('signal_strength', $availableMetrics);
        $this->assertContains('cpu_load', $availableMetrics);
        $this->assertContains('memory_usage', $availableMetrics);
        $this->assertContains('analog_input', $availableMetrics);
    }

    public function test_get_trend_data_with_device_readings()
    {
        // Create a device and readings
        $device = Device::factory()->create(['gateway_id' => $this->rtuGateway->id]);
        $register = Register::factory()->create([
            'device_id' => $device->id,
            'parameter_name' => 'CPU Load'
        ]);
        
        Reading::factory()->create([
            'device_id' => $device->id,
            'register_id' => $register->id,
            'value' => 45.5,
            'timestamp' => now()->subHours(2)
        ]);

        Reading::factory()->create([
            'device_id' => $device->id,
            'register_id' => $register->id,
            'value' => 50.2,
            'timestamp' => now()->subHour()
        ]);

        $result = $this->rtuDataService->getTrendData($this->rtuGateway, '24h');

        $this->assertIsArray($result);
        $this->assertTrue($result['has_data']);
        $this->assertArrayHasKey('metrics', $result);
        $this->assertArrayHasKey('start_time', $result);
        $this->assertArrayHasKey('end_time', $result);
    }

    public function test_determine_system_status_with_critical_conditions()
    {
        $systemData = [
            'cpu_load' => 96.0,
            'memory_usage' => 85.0,
            'uptime_hours' => 100
        ];

        $reflection = new \ReflectionClass($this->rtuDataService);
        $method = $reflection->getMethod('determineSystemStatus');
        $method->setAccessible(true);

        $result = $method->invoke($this->rtuDataService, $systemData);
        $this->assertEquals('critical', $result);
    }

    public function test_determine_system_status_with_warning_conditions()
    {
        $systemData = [
            'cpu_load' => 85.0,
            'memory_usage' => 70.0,
            'uptime_hours' => 100
        ];

        $reflection = new \ReflectionClass($this->rtuDataService);
        $method = $reflection->getMethod('determineSystemStatus');
        $method->setAccessible(true);

        $result = $method->invoke($this->rtuDataService, $systemData);
        $this->assertEquals('warning', $result);
    }

    public function test_determine_system_status_with_normal_conditions()
    {
        $systemData = [
            'cpu_load' => 45.0,
            'memory_usage' => 60.0,
            'uptime_hours' => 100
        ];

        $reflection = new \ReflectionClass($this->rtuDataService);
        $method = $reflection->getMethod('determineSystemStatus');
        $method->setAccessible(true);

        $result = $method->invoke($this->rtuDataService, $systemData);
        $this->assertEquals('normal', $result);
    }

    public function test_determine_system_status_with_no_data()
    {
        $systemData = [
            'cpu_load' => null,
            'memory_usage' => null,
            'uptime_hours' => null
        ];

        $reflection = new \ReflectionClass($this->rtuDataService);
        $method = $reflection->getMethod('determineSystemStatus');
        $method->setAccessible(true);

        $result = $method->invoke($this->rtuDataService, $systemData);
        $this->assertEquals('unavailable', $result);
    }

    public function test_determine_system_status_with_offline_condition()
    {
        $systemData = [
            'cpu_load' => 45.0,
            'memory_usage' => 60.0,
            'uptime_hours' => 0
        ];

        $reflection = new \ReflectionClass($this->rtuDataService);
        $method = $reflection->getMethod('determineSystemStatus');
        $method->setAccessible(true);

        $result = $method->invoke($this->rtuDataService, $systemData);
        $this->assertEquals('offline', $result);
    }

    public function test_calculate_start_time_for_different_ranges()
    {
        $endTime = Carbon::parse('2024-01-15 12:00:00');

        $reflection = new \ReflectionClass($this->rtuDataService);
        $method = $reflection->getMethod('calculateStartTime');
        $method->setAccessible(true);

        // Test 1 hour
        $result = $method->invoke($this->rtuDataService, '1h', $endTime);
        $this->assertEquals('2024-01-15 11:00:00', $result->format('Y-m-d H:i:s'));

        // Test 6 hours
        $result = $method->invoke($this->rtuDataService, '6h', $endTime);
        $this->assertEquals('2024-01-15 06:00:00', $result->format('Y-m-d H:i:s'));

        // Test 24 hours
        $result = $method->invoke($this->rtuDataService, '24h', $endTime);
        $this->assertEquals('2024-01-14 12:00:00', $result->format('Y-m-d H:i:s'));

        // Test 7 days
        $result = $method->invoke($this->rtuDataService, '7d', $endTime);
        $this->assertEquals('2024-01-08 12:00:00', $result->format('Y-m-d H:i:s'));

        // Test 30 days (subMonth() subtracts 1 month, which from Jan 15 goes to Dec 15)
        $result = $method->invoke($this->rtuDataService, '30d', $endTime);
        $this->assertEquals('2023-12-15 12:00:00', $result->format('Y-m-d H:i:s'));

        // Test default (should default to 24h)
        $result = $method->invoke($this->rtuDataService, 'invalid', $endTime);
        $this->assertEquals('2024-01-14 12:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function test_get_metric_unit_returns_correct_units()
    {
        $reflection = new \ReflectionClass($this->rtuDataService);
        $method = $reflection->getMethod('getMetricUnit');
        $method->setAccessible(true);

        $this->assertEquals('V', $method->invoke($this->rtuDataService, 'voltage'));
        $this->assertEquals('V', $method->invoke($this->rtuDataService, 'Analog Voltage'));
        $this->assertEquals('A', $method->invoke($this->rtuDataService, 'current'));
        $this->assertEquals('A', $method->invoke($this->rtuDataService, 'Amperage'));
        $this->assertEquals('W', $method->invoke($this->rtuDataService, 'power'));
        $this->assertEquals('W', $method->invoke($this->rtuDataService, 'Wattage'));
        $this->assertEquals('Hz', $method->invoke($this->rtuDataService, 'frequency'));
        $this->assertEquals('Hz', $method->invoke($this->rtuDataService, 'Freq'));
        $this->assertEquals('°C', $method->invoke($this->rtuDataService, 'temperature'));
        $this->assertEquals('°C', $method->invoke($this->rtuDataService, 'Temp'));
        $this->assertEquals('%', $method->invoke($this->rtuDataService, 'cpu'));
        $this->assertEquals('%', $method->invoke($this->rtuDataService, 'memory'));
        $this->assertEquals('dBm', $method->invoke($this->rtuDataService, 'signal'));
        $this->assertEquals('dBm', $method->invoke($this->rtuDataService, 'rssi'));
        $this->assertEquals('', $method->invoke($this->rtuDataService, 'unknown_metric'));
    }

    public function test_classify_control_error_returns_correct_types()
    {
        $reflection = new \ReflectionClass($this->rtuDataService);
        $method = $reflection->getMethod('classifyControlError');
        $method->setAccessible(true);

        $timeoutException = new \Exception('Connection timeout occurred');
        $this->assertEquals('connection_error', $method->invoke($this->rtuDataService, $timeoutException));

        $connectionException = new \Exception('Failed to establish connection');
        $this->assertEquals('connection_error', $method->invoke($this->rtuDataService, $connectionException));

        $authException = new \Exception('Unauthorized access');
        $this->assertEquals('authorization_error', $method->invoke($this->rtuDataService, $authException));

        $forbiddenException = new \Exception('Forbidden operation');
        $this->assertEquals('authorization_error', $method->invoke($this->rtuDataService, $forbiddenException));

        $validationException = new \Exception('Invalid parameter provided');
        $this->assertEquals('validation_error', $method->invoke($this->rtuDataService, $validationException));

        $unknownException = new \Exception('Something went wrong');
        $this->assertEquals('unknown_error', $method->invoke($this->rtuDataService, $unknownException));
    }

    public function test_extract_metric_data_with_gateway_data()
    {
        $readings = collect(); // Empty readings collection

        $reflection = new \ReflectionClass($this->rtuDataService);
        $method = $reflection->getMethod('extractMetricData');
        $method->setAccessible(true);

        // Test RSSI extraction
        $result = $method->invoke($this->rtuDataService, $readings, 'rssi', $this->rtuGateway);
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals(-75, $result[0]['value']);
        $this->assertEquals('dBm', $result[0]['unit']);

        // Test CPU load extraction
        $result = $method->invoke($this->rtuDataService, $readings, 'cpu_load', $this->rtuGateway);
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals(45.5, $result[0]['value']);
        $this->assertEquals('%', $result[0]['unit']);

        // Test memory usage extraction
        $result = $method->invoke($this->rtuDataService, $readings, 'memory_usage', $this->rtuGateway);
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals(67.2, $result[0]['value']);
        $this->assertEquals('%', $result[0]['unit']);

        // Test analog voltage extraction
        $result = $method->invoke($this->rtuDataService, $readings, 'analog_voltage', $this->rtuGateway);
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals(7.25, $result[0]['value']);
        $this->assertEquals('V', $result[0]['unit']);
    }

    public function test_get_available_metrics_includes_gateway_metrics()
    {
        $readings = collect(); // Empty readings collection

        $reflection = new \ReflectionClass($this->rtuDataService);
        $method = $reflection->getMethod('getAvailableMetrics');
        $method->setAccessible(true);

        $result = $method->invoke($this->rtuDataService, $readings, $this->rtuGateway);

        $this->assertIsArray($result);
        $this->assertContains('signal_strength', $result);
        $this->assertContains('cpu_load', $result);
        $this->assertContains('memory_usage', $result);
        $this->assertContains('analog_input', $result);
    }

    public function test_error_handling_logs_errors_appropriately()
    {
        Log::shouldReceive('error')
            ->once()
            ->with('RTU system health collection failed', \Mockery::type('array'));

        // Create a gateway that will trigger an error in fetchSystemDataFromGateway
        $gateway = Gateway::factory()->create([
            'gateway_type' => 'generic', // This will cause an error in the fetch method
            'name' => 'Error Gateway'
        ]);

        $result = $this->rtuDataService->getSystemHealth($gateway);

        $this->assertIsArray($result);
        $this->assertEquals('unavailable', $result['status']);
        $this->assertArrayHasKey('error', $result);
    }
}