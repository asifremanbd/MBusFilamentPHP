<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\RTUWidgetErrorHandler;
use App\Models\Gateway;
use Exception;
use Mockery;

class RTUWidgetErrorHandlerBasicTest extends TestCase
{
    protected RTUWidgetErrorHandler $errorHandler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->errorHandler = new RTUWidgetErrorHandler();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_classifies_timeout_error_correctly()
    {
        $gateway = Mockery::mock(Gateway::class);
        $gateway->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $gateway->shouldReceive('getAttribute')->with('name')->andReturn('Test Gateway');
        $gateway->shouldReceive('getAttribute')->with('gateway_type')->andReturn('teltonika_rut956');
        $gateway->shouldReceive('getAttribute')->with('last_system_update')->andReturn(now());

        $exception = new Exception('Connection timed out');
        
        $result = $this->errorHandler->handleDataCollectionError($gateway, 'system_health', $exception);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('timeout', $result['error_type']);
        $this->assertStringContains('timed out', $result['message']);
        $this->assertTrue($result['retry_available']);
    }

    public function test_classifies_connection_refused_error_correctly()
    {
        $gateway = Mockery::mock(Gateway::class);
        $gateway->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $gateway->shouldReceive('getAttribute')->with('name')->andReturn('Test Gateway');
        $gateway->shouldReceive('getAttribute')->with('gateway_type')->andReturn('teltonika_rut956');
        $gateway->shouldReceive('getAttribute')->with('last_system_update')->andReturn(now());

        $exception = new Exception('Connection refused');
        
        $result = $this->errorHandler->handleDataCollectionError($gateway, 'network_status', $exception);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('connection_refused', $result['error_type']);
        $this->assertStringContains('Unable to connect', $result['message']);
        $this->assertTrue($result['retry_available']);
    }

    public function test_classifies_authentication_error_correctly()
    {
        $gateway = Mockery::mock(Gateway::class);
        $gateway->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $gateway->shouldReceive('getAttribute')->with('name')->andReturn('Test Gateway');
        $gateway->shouldReceive('getAttribute')->with('gateway_type')->andReturn('teltonika_rut956');
        $gateway->shouldReceive('getAttribute')->with('last_system_update')->andReturn(now());

        $exception = new Exception('Authentication failed');
        
        $result = $this->errorHandler->handleDataCollectionError($gateway, 'io_status', $exception);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('authentication', $result['error_type']);
        $this->assertStringContains('Authentication failed', $result['message']);
        $this->assertFalse($result['retry_available']);
    }

    public function test_handles_control_permission_denied_error()
    {
        $gateway = Mockery::mock(Gateway::class);
        $gateway->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $gateway->shouldReceive('getAttribute')->with('name')->andReturn('Test Gateway');

        $exception = new Exception('Permission denied');
        
        $result = $this->errorHandler->handleControlError($gateway, 'do1', $exception);

        $this->assertFalse($result['success']);
        $this->assertEquals('permission_denied', $result['error_type']);
        $this->assertStringContains("don't have permission", $result['message']);
        $this->assertFalse($result['retry_suggested']);
        $this->assertEquals('admin', $result['support_contact']['type']);
    }

    public function test_handles_control_hardware_failure_error()
    {
        $gateway = Mockery::mock(Gateway::class);
        $gateway->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $gateway->shouldReceive('getAttribute')->with('name')->andReturn('Test Gateway');

        $exception = new Exception('Hardware module offline');
        
        $result = $this->errorHandler->handleControlError($gateway, 'do2', $exception);

        $this->assertFalse($result['success']);
        $this->assertEquals('hardware_failure', $result['error_type']);
        $this->assertStringContains('Hardware module is offline', $result['message']);
        $this->assertFalse($result['retry_suggested']);
        $this->assertEquals('maintenance', $result['support_contact']['type']);
        $this->assertEquals('high', $result['support_contact']['urgency']);
    }

    public function test_handles_control_timeout_with_retry()
    {
        $gateway = Mockery::mock(Gateway::class);
        $gateway->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $gateway->shouldReceive('getAttribute')->with('name')->andReturn('Test Gateway');

        $exception = new Exception('Operation timed out');
        
        $result = $this->errorHandler->handleControlError($gateway, 'do1', $exception);

        $this->assertFalse($result['success']);
        $this->assertEquals('timeout', $result['error_type']);
        $this->assertTrue($result['retry_suggested']);
        $this->assertEquals(30, $result['retry_delay']);
    }

    public function test_provides_troubleshooting_steps_for_timeout()
    {
        $gateway = Mockery::mock(Gateway::class);
        $gateway->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $gateway->shouldReceive('getAttribute')->with('name')->andReturn('Test Gateway');
        $gateway->shouldReceive('getAttribute')->with('gateway_type')->andReturn('teltonika_rut956');
        $gateway->shouldReceive('getAttribute')->with('last_system_update')->andReturn(now());

        $exception = new Exception('Connection timed out');
        
        $result = $this->errorHandler->handleDataCollectionError($gateway, 'system_health', $exception);

        $troubleshooting = $result['troubleshooting'];
        
        $this->assertContains('Check RTU gateway network connectivity', $troubleshooting);
        $this->assertContains('Check network latency to RTU gateway', $troubleshooting);
        $this->assertContains('Verify firewall settings allow communication', $troubleshooting);
    }

    public function test_provides_troubleshooting_steps_for_control_permission_denied()
    {
        $gateway = Mockery::mock(Gateway::class);
        $gateway->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $gateway->shouldReceive('getAttribute')->with('name')->andReturn('Test Gateway');

        $exception = new Exception('Forbidden access');
        
        $result = $this->errorHandler->handleControlError($gateway, 'do1', $exception);

        $troubleshooting = $result['troubleshooting_steps'];
        
        $this->assertContains('Verify user has control permissions for this gateway', $troubleshooting);
        $this->assertContains('Contact administrator to grant necessary permissions', $troubleshooting);
    }

    public function test_provides_fallback_action_for_control_operations()
    {
        $gateway = Mockery::mock(Gateway::class);
        $gateway->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $gateway->shouldReceive('getAttribute')->with('name')->andReturn('Test Gateway');

        $exception = new Exception('Control timeout');
        
        $result = $this->errorHandler->handleControlError($gateway, 'do1', $exception);

        $this->assertStringContains('Manual control may be available', $result['fallback_action']);
    }

    public function test_handles_unknown_data_type_gracefully()
    {
        $gateway = Mockery::mock(Gateway::class);
        $gateway->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $gateway->shouldReceive('getAttribute')->with('name')->andReturn('Test Gateway');
        $gateway->shouldReceive('getAttribute')->with('gateway_type')->andReturn('teltonika_rut956');
        $gateway->shouldReceive('getAttribute')->with('last_system_update')->andReturn(now());

        $exception = new Exception('Test error');
        
        $result = $this->errorHandler->handleDataCollectionError($gateway, 'unknown_type', $exception);

        $fallbackData = $result['fallback_data'];
        
        $this->assertEquals('unavailable', $fallbackData['status']);
        $this->assertEquals('No fallback data available', $fallbackData['message']);
        $this->assertEquals('none', $fallbackData['fallback_source']);
    }
}