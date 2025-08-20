<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Gateway;
use App\Services\RTUWidgetErrorHandler;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RTUWidgetErrorHandlerTest extends TestCase
{
    use RefreshDatabase;

    protected RTUWidgetErrorHandler $errorHandler;
    protected Gateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->errorHandler = new RTUWidgetErrorHandler();
        $this->gateway = Gateway::factory()->create([
            'gateway_type' => 'teltonika_rut956',
            'uptime_hours' => 120,
            'cpu_load' => 45.5,
            'memory_usage' => 67.2,
            'last_system_update' => now()->subMinutes(10)
        ]);
    }

    public function test_handles_data_collection_timeout_error()
    {
        $exception = new Exception('Connection timed out');
        
        $result = $this->errorHandler->handleDataCollectionError(
            $this->gateway, 
            'system_health', 
            $exception
        );

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('timeout', $result['error_type']);
        $this->assertStringContains('timed out', $result['message']);
        $this->assertTrue($result['retry_available']);
        $this->assertArrayHasKey('fallback_data', $result);
        $this->assertArrayHasKey('troubleshooting', $result);
    }

    public function test_handles_data_collection_connection_refused_error()
    {
        $exception = new Exception('Connection refused');
        
        $result = $this->errorHandler->handleDataCollectionError(
            $this->gateway, 
            'network_status', 
            $exception
        );

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('connection_refused', $result['error_type']);
        $this->assertStringContains('Unable to connect', $result['message']);
        $this->assertTrue($result['retry_available']);
    }

    public function test_handles_data_collection_authentication_error()
    {
        $exception = new Exception('Authentication failed');
        
        $result = $this->errorHandler->handleDataCollectionError(
            $this->gateway, 
            'io_status', 
            $exception
        );

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('authentication', $result['error_type']);
        $this->assertStringContains('Authentication failed', $result['message']);
        $this->assertFalse($result['retry_available']);
    }

    public function test_provides_fallback_data_for_system_health()
    {
        $exception = new Exception('Network error');
        
        $result = $this->errorHandler->handleDataCollectionError(
            $this->gateway, 
            'system_health', 
            $exception
        );

        $fallbackData = $result['fallback_data'];
        
        $this->assertEquals(120, $fallbackData['uptime_hours']);
        $this->assertEquals(45.5, $fallbackData['cpu_load']);
        $this->assertEquals(67.2, $fallbackData['memory_usage']);
        $this->assertEquals('database', $fallbackData['fallback_source']);
        $this->assertFalse($fallbackData['is_cached']);
    }

    public function test_provides_fallback_data_for_network_status()
    {
        $this->gateway->update([
            'wan_ip' => '192.168.1.100',
            'sim_iccid' => '89012345678901234567',
            'rssi' => -75
        ]);

        $exception = new Exception('Network timeout');
        
        $result = $this->errorHandler->handleDataCollectionError(
            $this->gateway, 
            'network_status', 
            $exception
        );

        $fallbackData = $result['fallback_data'];
        
        $this->assertEquals('192.168.1.100', $fallbackData['wan_ip']);
        $this->assertEquals('89012345678901234567', $fallbackData['sim_iccid']);
        $this->assertEquals(-75, $fallbackData['signal_quality']['rssi']);
    }

    public function test_provides_fallback_data_for_io_status()
    {
        $this->gateway->update([
            'di1_status' => true,
            'di2_status' => false,
            'do1_status' => true,
            'analog_input_voltage' => 7.25
        ]);

        $exception = new Exception('I/O module offline');
        
        $result = $this->errorHandler->handleDataCollectionError(
            $this->gateway, 
            'io_status', 
            $exception
        );

        $fallbackData = $result['fallback_data'];
        
        $this->assertTrue($fallbackData['digital_inputs']['di1']['status']);
        $this->assertFalse($fallbackData['digital_inputs']['di2']['status']);
        $this->assertTrue($fallbackData['digital_outputs']['do1']['status']);
        $this->assertEquals(7.25, $fallbackData['analog_input']['voltage']);
        $this->assertFalse($fallbackData['digital_outputs']['do1']['controllable']);
    }

    public function test_handles_control_operation_permission_denied()
    {
        $exception = new Exception('Permission denied');
        
        $result = $this->errorHandler->handleControlError(
            $this->gateway, 
            'do1', 
            $exception
        );

        $this->assertFalse($result['success']);
        $this->assertEquals('permission_denied', $result['error_type']);
        $this->assertStringContains("don't have permission", $result['message']);
        $this->assertFalse($result['retry_suggested']);
        $this->assertEquals('admin', $result['support_contact']['type']);
    }

    public function test_handles_control_operation_hardware_failure()
    {
        $exception = new Exception('Hardware module offline');
        
        $result = $this->errorHandler->handleControlError(
            $this->gateway, 
            'do2', 
            $exception
        );

        $this->assertFalse($result['success']);
        $this->assertEquals('hardware_failure', $result['error_type']);
        $this->assertStringContains('Hardware module is offline', $result['message']);
        $this->assertFalse($result['retry_suggested']);
        $this->assertEquals('maintenance', $result['support_contact']['type']);
        $this->assertEquals('high', $result['support_contact']['urgency']);
    }

    public function test_handles_control_operation_timeout()
    {
        $exception = new Exception('Operation timed out');
        
        $result = $this->errorHandler->handleControlError(
            $this->gateway, 
            'do1', 
            $exception
        );

        $this->assertFalse($result['success']);
        $this->assertEquals('timeout', $result['error_type']);
        $this->assertTrue($result['retry_suggested']);
        $this->assertEquals(30, $result['retry_delay']);
    }

    public function test_provides_troubleshooting_steps_for_timeout()
    {
        $exception = new Exception('Connection timed out');
        
        $result = $this->errorHandler->handleDataCollectionError(
            $this->gateway, 
            'system_health', 
            $exception
        );

        $troubleshooting = $result['troubleshooting'];
        
        $this->assertContains('Check RTU gateway network connectivity', $troubleshooting);
        $this->assertContains('Check network latency to RTU gateway', $troubleshooting);
        $this->assertContains('Verify firewall settings allow communication', $troubleshooting);
    }

    public function test_provides_troubleshooting_steps_for_control_permission_denied()
    {
        $exception = new Exception('Forbidden access');
        
        $result = $this->errorHandler->handleControlError(
            $this->gateway, 
            'do1', 
            $exception
        );

        $troubleshooting = $result['troubleshooting_steps'];
        
        $this->assertContains('Verify user has control permissions for this gateway', $troubleshooting);
        $this->assertContains('Contact administrator to grant necessary permissions', $troubleshooting);
    }

    public function test_caches_successful_data()
    {
        $successfulData = [
            'uptime_hours' => 150,
            'cpu_load' => 25.0,
            'memory_usage' => 55.0,
            'status' => 'normal'
        ];

        $this->errorHandler->cacheSuccessfulData($this->gateway, 'system_health', $successfulData);

        $cacheKey = "rtu_fallback_{$this->gateway->id}_system_health";
        $cachedData = Cache::get($cacheKey);

        $this->assertNotNull($cachedData);
        $this->assertEquals(150, $cachedData['uptime_hours']);
        $this->assertEquals(25.0, $cachedData['cpu_load']);
        $this->assertArrayHasKey('timestamp', $cachedData);
    }

    public function test_uses_cached_data_as_fallback()
    {
        // Cache some data first
        $cachedData = [
            'uptime_hours' => 200,
            'cpu_load' => 30.0,
            'memory_usage' => 40.0,
            'status' => 'normal',
            'timestamp' => now()
        ];

        $cacheKey = "rtu_fallback_{$this->gateway->id}_system_health";
        Cache::put($cacheKey, $cachedData, 3600);

        $exception = new Exception('Network error');
        
        $result = $this->errorHandler->handleDataCollectionError(
            $this->gateway, 
            'system_health', 
            $exception
        );

        $fallbackData = $result['fallback_data'];
        
        $this->assertEquals(200, $fallbackData['uptime_hours']);
        $this->assertEquals(30.0, $fallbackData['cpu_load']);
        $this->assertTrue($fallbackData['is_cached']);
    }

    public function test_clears_fallback_cache()
    {
        // Cache some data
        $cacheKey = "rtu_fallback_{$this->gateway->id}_system_health";
        Cache::put($cacheKey, ['test' => 'data'], 3600);

        $this->assertTrue(Cache::has($cacheKey));

        $this->errorHandler->clearFallbackCache($this->gateway, 'system_health');

        $this->assertFalse(Cache::has($cacheKey));
    }

    public function test_clears_all_fallback_cache_for_gateway()
    {
        // Cache data for multiple types
        $types = ['system_health', 'network_status', 'io_status'];
        foreach ($types as $type) {
            $cacheKey = "rtu_fallback_{$this->gateway->id}_{$type}";
            Cache::put($cacheKey, ['test' => 'data'], 3600);
        }

        $this->errorHandler->clearFallbackCache($this->gateway);

        foreach ($types as $type) {
            $cacheKey = "rtu_fallback_{$this->gateway->id}_{$type}";
            $this->assertFalse(Cache::has($cacheKey));
        }
    }

    public function test_calculates_cache_age()
    {
        $this->gateway->update([
            'last_system_update' => now()->subMinutes(15)
        ]);

        $exception = new Exception('Test error');
        
        $result = $this->errorHandler->handleDataCollectionError(
            $this->gateway, 
            'system_health', 
            $exception
        );

        $this->assertEquals(15, $result['cache_age']);
    }

    public function test_logs_data_collection_errors()
    {
        Log::shouldReceive('error')
            ->once()
            ->with('RTU data collection failed', \Mockery::type('array'));

        $exception = new Exception('Test error');
        
        $this->errorHandler->handleDataCollectionError(
            $this->gateway, 
            'system_health', 
            $exception
        );
    }

    public function test_logs_control_operation_errors()
    {
        Log::shouldReceive('error')
            ->once()
            ->with('RTU control operation failed', \Mockery::type('array'));

        $exception = new Exception('Control failed');
        
        $this->errorHandler->handleControlError(
            $this->gateway, 
            'do1', 
            $exception
        );
    }

    public function test_provides_fallback_action_for_control_operations()
    {
        $exception = new Exception('Control timeout');
        
        $result = $this->errorHandler->handleControlError(
            $this->gateway, 
            'do1', 
            $exception
        );

        $this->assertStringContains('Manual control may be available', $result['fallback_action']);
    }

    public function test_handles_unknown_data_type()
    {
        $exception = new Exception('Test error');
        
        $result = $this->errorHandler->handleDataCollectionError(
            $this->gateway, 
            'unknown_type', 
            $exception
        );

        $fallbackData = $result['fallback_data'];
        
        $this->assertEquals('unavailable', $fallbackData['status']);
        $this->assertEquals('No fallback data available', $fallbackData['message']);
        $this->assertEquals('none', $fallbackData['fallback_source']);
    }
}