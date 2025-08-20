<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Gateway;
use App\Models\User;
use App\Services\RTUDataService;
use App\Services\RTUWidgetErrorHandler;
use App\Services\RTURetryService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Mockery;

class RTUErrorHandlingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Gateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->gateway = Gateway::factory()->create([
            'gateway_type' => 'teltonika_rut956',
            'name' => 'Test RTU Gateway',
            'uptime_hours' => 100,
            'cpu_load' => 50.0,
            'memory_usage' => 60.0,
            'wan_ip' => '192.168.1.100',
            'rssi' => -70,
            'di1_status' => true,
            'do1_status' => false,
            'analog_input_voltage' => 5.5,
            'last_system_update' => now()->subMinutes(5)
        ]);
    }

    public function test_rtu_dashboard_handles_system_health_collection_failure()
    {
        // Mock RTUDataService to throw exception
        $this->mock(RTUDataService::class, function ($mock) {
            $mock->shouldReceive('getSystemHealth')
                ->andThrow(new Exception('Connection timeout'));
            
            $mock->shouldReceive('getNetworkStatus')
                ->andReturn(['connection_status' => 'online']);
            
            $mock->shouldReceive('getIOStatus')
                ->andReturn(['digital_inputs' => []]);
        });

        $response = $this->actingAs($this->user)
            ->get(route('rtu.dashboard', $this->gateway));

        $response->assertStatus(200);
        $response->assertSee('Connection timeout');
        $response->assertSee('Cached Data');
        $response->assertSee('Retry');
    }

    public function test_rtu_dashboard_handles_network_status_collection_failure()
    {
        $this->mock(RTUDataService::class, function ($mock) {
            $mock->shouldReceive('getSystemHealth')
                ->andReturn(['status' => 'normal']);
            
            $mock->shouldReceive('getNetworkStatus')
                ->andThrow(new Exception('Network API unavailable'));
            
            $mock->shouldReceive('getIOStatus')
                ->andReturn(['digital_inputs' => []]);
        });

        $response = $this->actingAs($this->user)
            ->get(route('rtu.dashboard', $this->gateway));

        $response->assertStatus(200);
        $response->assertSee('Network API unavailable');
    }

    public function test_rtu_dashboard_handles_io_status_collection_failure()
    {
        $this->mock(RTUDataService::class, function ($mock) {
            $mock->shouldReceive('getSystemHealth')
                ->andReturn(['status' => 'normal']);
            
            $mock->shouldReceive('getNetworkStatus')
                ->andReturn(['connection_status' => 'online']);
            
            $mock->shouldReceive('getIOStatus')
                ->andThrow(new Exception('I/O module not responding'));
        });

        $response = $this->actingAs($this->user)
            ->get(route('rtu.dashboard', $this->gateway));

        $response->assertStatus(200);
        $response->assertSee('I/O module not responding');
    }

    public function test_digital_output_control_handles_permission_denied()
    {
        $this->mock(RTUDataService::class, function ($mock) {
            $mock->shouldReceive('setDigitalOutput')
                ->andReturn([
                    'success' => false,
                    'error_type' => 'permission_denied',
                    'message' => "You don't have permission to perform this control operation.",
                    'retry_suggested' => false,
                    'support_contact' => ['type' => 'admin']
                ]);
        });

        $response = $this->actingAs($this->user)
            ->postJson(route('rtu.control.output', [$this->gateway, 'do1']), [
                'state' => true
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => false,
            'error_type' => 'permission_denied'
        ]);
        $response->assertJsonFragment(['retry_suggested' => false]);
    }

    public function test_digital_output_control_handles_hardware_failure()
    {
        $this->mock(RTUDataService::class, function ($mock) {
            $mock->shouldReceive('setDigitalOutput')
                ->andReturn([
                    'success' => false,
                    'error_type' => 'hardware_failure',
                    'message' => 'Hardware module is offline or not responding.',
                    'retry_suggested' => false,
                    'support_contact' => ['type' => 'maintenance', 'urgency' => 'high'],
                    'troubleshooting_steps' => [
                        'Check I/O module status on RTU gateway',
                        'Verify physical connections to I/O terminals'
                    ]
                ]);
        });

        $response = $this->actingAs($this->user)
            ->postJson(route('rtu.control.output', [$this->gateway, 'do1']), [
                'state' => true
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => false,
            'error_type' => 'hardware_failure'
        ]);
        $response->assertJsonFragment(['urgency' => 'high']);
    }

    public function test_digital_output_control_handles_timeout_with_retry()
    {
        $this->mock(RTUDataService::class, function ($mock) {
            $mock->shouldReceive('setDigitalOutput')
                ->andReturn([
                    'success' => false,
                    'error_type' => 'timeout',
                    'message' => 'Control operation timed out.',
                    'retry_suggested' => true,
                    'retry_delay' => 30
                ]);
        });

        $response = $this->actingAs($this->user)
            ->postJson(route('rtu.control.output', [$this->gateway, 'do1']), [
                'state' => true
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => false,
            'retry_suggested' => true,
            'retry_delay' => 30
        ]);
    }

    public function test_retry_service_handles_data_collection_retry()
    {
        $retryService = app(RTURetryService::class);
        
        // Mock the data service to fail first, then succeed
        $this->mock(RTUDataService::class, function ($mock) {
            $mock->shouldReceive('getSystemHealth')
                ->once()
                ->andThrow(new Exception('Temporary network error'))
                ->shouldReceive('getSystemHealth')
                ->once()
                ->andReturn([
                    'uptime_hours' => 120,
                    'cpu_load' => 45.0,
                    'status' => 'normal',
                    'retry_successful' => true
                ]);
        });

        $result = $retryService->retryDataCollection($this->gateway, 'system_health');

        $this->assertTrue($result['retry_successful'] ?? false);
    }

    public function test_retry_service_handles_control_operation_retry()
    {
        $retryService = app(RTURetryService::class);
        
        // Mock the data service to fail first, then succeed
        $this->mock(RTUDataService::class, function ($mock) {
            $mock->shouldReceive('setDigitalOutput')
                ->once()
                ->andReturn(['success' => false, 'message' => 'Timeout'])
                ->shouldReceive('setDigitalOutput')
                ->once()
                ->andReturn([
                    'success' => true,
                    'message' => 'Digital output do1 set to ON',
                    'new_state' => true
                ]);
        });

        $result = $retryService->retryControlOperation($this->gateway, 'do1', true);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['retry_successful'] ?? false);
    }

    public function test_retry_service_exhausts_attempts_and_returns_error()
    {
        $retryService = app(RTURetryService::class);
        
        // Mock the data service to always fail
        $this->mock(RTUDataService::class, function ($mock) {
            $mock->shouldReceive('getSystemHealth')
                ->times(3) // MAX_DATA_RETRIES
                ->andThrow(new Exception('Persistent network error'));
        });

        $result = $retryService->retryDataCollection($this->gateway, 'system_health');

        $this->assertTrue($result['retry_exhausted'] ?? false);
        $this->assertEquals(3, $result['total_attempts']);
    }

    public function test_cached_fallback_data_is_used_when_available()
    {
        // Cache some fallback data
        $cachedData = [
            'uptime_hours' => 150,
            'cpu_load' => 35.0,
            'memory_usage' => 45.0,
            'status' => 'normal',
            'timestamp' => now()
        ];

        $errorHandler = app(RTUWidgetErrorHandler::class);
        $errorHandler->cacheSuccessfulData($this->gateway, 'system_health', $cachedData);

        // Mock service to throw error
        $this->mock(RTUDataService::class, function ($mock) {
            $mock->shouldReceive('getSystemHealth')
                ->andThrow(new Exception('Network error'));
        });

        $result = $errorHandler->handleDataCollectionError(
            $this->gateway, 
            'system_health', 
            new Exception('Network error')
        );

        $fallbackData = $result['fallback_data'];
        $this->assertEquals(150, $fallbackData['uptime_hours']);
        $this->assertEquals(35.0, $fallbackData['cpu_load']);
        $this->assertTrue($fallbackData['is_cached']);
    }

    public function test_error_handling_logs_appropriate_messages()
    {
        Log::shouldReceive('error')
            ->once()
            ->with('RTU data collection failed', Mockery::type('array'));

        $errorHandler = app(RTUWidgetErrorHandler::class);
        $errorHandler->handleDataCollectionError(
            $this->gateway,
            'system_health',
            new Exception('Test error for logging')
        );
    }

    public function test_widget_displays_troubleshooting_information()
    {
        $this->mock(RTUDataService::class, function ($mock) {
            $mock->shouldReceive('getSystemHealth')
                ->andReturn([
                    'status' => 'error',
                    'error_type' => 'timeout',
                    'message' => 'Connection to RTU gateway timed out',
                    'retry_available' => true,
                    'troubleshooting' => [
                        'Check RTU gateway network connectivity',
                        'Verify gateway IP address and port configuration',
                        'Ensure gateway is powered on and operational'
                    ],
                    'fallback_data' => [
                        'uptime_hours' => 100,
                        'cpu_load' => 50.0,
                        'is_cached' => false,
                        'fallback_source' => 'database'
                    ]
                ]);
            
            $mock->shouldReceive('getNetworkStatus')
                ->andReturn(['connection_status' => 'online']);
            
            $mock->shouldReceive('getIOStatus')
                ->andReturn(['digital_inputs' => []]);
        });

        $response = $this->actingAs($this->user)
            ->get(route('rtu.dashboard', $this->gateway));

        $response->assertStatus(200);
        $response->assertSee('Connection to RTU gateway timed out');
        $response->assertSee('Troubleshooting Steps');
        $response->assertSee('Check RTU gateway network connectivity');
    }

    public function test_concurrent_retry_operations_are_prevented()
    {
        $retryService = app(RTURetryService::class);
        
        // Start a retry operation
        Cache::put("rtu_retry_{$this->gateway->id}_system_health", 1, 300);

        $result = $retryService->retryDataCollection($this->gateway, 'system_health');

        $this->assertEquals('retry_in_progress', $result['status']);
        $this->assertStringContains('already in progress', $result['message']);
    }

    public function test_retry_status_tracking()
    {
        $retryService = app(RTURetryService::class);
        
        // Simulate active retries
        Cache::put("rtu_retry_{$this->gateway->id}_system_health", 2, 300);
        Cache::put("rtu_control_retry_{$this->gateway->id}_do1", 1, 180);

        $status = $retryService->getRetryStatus($this->gateway);

        $this->assertTrue($status['has_active_retries']);
        $this->assertEquals(2, $status['data_retries']['system_health']['attempt']);
        $this->assertEquals(1, $status['control_retries']['do1']['attempt']);
    }

    public function test_retry_cancellation()
    {
        $retryService = app(RTURetryService::class);
        
        // Set up some retry operations
        Cache::put("rtu_retry_{$this->gateway->id}_system_health", 1, 300);
        Cache::put("rtu_control_retry_{$this->gateway->id}_do1", 1, 180);

        $retryService->cancelRetries($this->gateway);

        $this->assertFalse(Cache::has("rtu_retry_{$this->gateway->id}_system_health"));
        $this->assertFalse(Cache::has("rtu_control_retry_{$this->gateway->id}_do1"));
    }
}