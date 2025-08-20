<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Gateway;
use App\Models\User;
use App\Services\RTUDataService;
use App\Services\RTURetryService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class RTUCommunicationFailureTest extends TestCase
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
            'fixed_ip' => '192.168.1.100',
            'uptime_hours' => 72,
            'cpu_load' => 25.5,
            'memory_usage' => 45.2,
            'wan_ip' => '10.0.0.50',
            'sim_iccid' => '89012345678901234567',
            'rssi' => -75,
            'di1_status' => true,
            'di2_status' => false,
            'do1_status' => false,
            'do2_status' => true,
            'analog_input_voltage' => 6.75,
            'communication_status' => 'online',
            'last_system_update' => now()->subMinutes(2)
        ]);
    }

    public function test_handles_complete_gateway_offline_scenario()
    {
        // Simulate complete gateway offline
        $this->mock(RTUDataService::class, function ($mock) {
            $mock->shouldReceive('getSystemHealth')
                ->andThrow(new Exception('Connection refused - gateway offline'));
            
            $mock->shouldReceive('getNetworkStatus')
                ->andThrow(new Exception('Connection refused - gateway offline'));
            
            $mock->shouldReceive('getIOStatus')
                ->andThrow(new Exception('Connection refused - gateway offline'));
        });

        $response = $this->actingAs($this->user)
            ->get(route('rtu.dashboard', $this->gateway));

        $response->assertStatus(200);
        
        // Should show cached/fallback data for all widgets
        $response->assertSee('gateway offline');
        $response->assertSee('Cached Data');
        $response->assertSee('72'); // Cached uptime hours
        $response->assertSee('25.5'); // Cached CPU load
        $response->assertSee('192.168.1.100'); // Should show some IP (WAN or fixed)
        $response->assertSee('6.75'); // Cached analog voltage
    }

    public function test_handles_partial_service_failure()
    {
        // Simulate only system health failing, others working
        $this->mock(RTUDataService::class, function ($mock) {
            $mock->shouldReceive('getSystemHealth')
                ->andThrow(new Exception('System API endpoint not found'));
            
            $mock->shouldReceive('getNetworkStatus')
                ->andReturn([
                    'wan_ip' => '10.0.0.51',
                    'sim_iccid' => '89012345678901234567',
                    'connection_status' => 'online',
                    'signal_quality' => ['rssi' => -70, 'status' => 'good']
                ]);
            
            $mock->shouldReceive('getIOStatus')
                ->andReturn([
                    'digital_inputs' => [
                        'di1' => ['status' => true, 'label' => 'Digital Input 1'],
                        'di2' => ['status' => false, 'label' => 'Digital Input 2']
                    ],
                    'digital_outputs' => [
                        'do1' => ['status' => false, 'label' => 'Digital Output 1', 'controllable' => true],
                        'do2' => ['status' => true, 'label' => 'Digital Output 2', 'controllable' => true]
                    ],
                    'analog_input' => ['voltage' => 7.25, 'unit' => 'V']
                ]);
        });

        $response = $this->actingAs($this->user)
            ->get(route('rtu.dashboard', $this->gateway));

        $response->assertStatus(200);
        
        // Should show error for system health but working data for others
        $response->assertSee('System API endpoint not found');
        $response->assertSee('10.0.0.51'); // Working network data
        $response->assertSee('7.25'); // Working I/O data
    }

    public function test_handles_intermittent_connection_issues()
    {
        $retryService = app(RTURetryService::class);
        
        // Simulate intermittent failure that succeeds on retry
        $this->mock(RTUDataService::class, function ($mock) {
            $mock->shouldReceive('getSystemHealth')
                ->once()
                ->andThrow(new Exception('Temporary network glitch'))
                ->shouldReceive('getSystemHealth')
                ->once()
                ->andReturn([
                    'uptime_hours' => 73,
                    'cpu_load' => 26.0,
                    'memory_usage' => 46.0,
                    'health_score' => 85,
                    'status' => 'normal',
                    'last_updated' => now()
                ]);
        });

        // First call should fail and return error
        $firstResult = $retryService->retryDataCollection($this->gateway, 'system_health', 1);
        
        // Should eventually succeed on retry
        $this->assertTrue($firstResult['retry_successful'] ?? false);
        $this->assertEquals(73, $firstResult['uptime_hours']);
    }

    public function test_handles_authentication_expiry_scenario()
    {
        $this->mock(RTUDataService::class, function ($mock) {
            $mock->shouldReceive('getSystemHealth')
                ->andThrow(new Exception('Authentication token expired'));
            
            $mock->shouldReceive('getNetworkStatus')
                ->andThrow(new Exception('Unauthorized access'));
            
            $mock->shouldReceive('getIOStatus')
                ->andThrow(new Exception('Authentication required'));
        });

        $response = $this->actingAs($this->user)
            ->get(route('rtu.dashboard', $this->gateway));

        $response->assertStatus(200);
        
        // Should show authentication-related error messages
        $response->assertSee('Authentication');
        $response->assertSee('credentials');
        
        // Should not show retry buttons for auth errors
        $response->assertDontSee('Retry');
    }

    public function test_handles_firmware_update_scenario()
    {
        // Simulate gateway being temporarily unavailable due to firmware update
        $this->mock(RTUDataService::class, function ($mock) {
            $mock->shouldReceive('getSystemHealth')
                ->andThrow(new Exception('Device is updating firmware'));
            
            $mock->shouldReceive('getNetworkStatus')
                ->andThrow(new Exception('Service temporarily unavailable'));
            
            $mock->shouldReceive('getIOStatus')
                ->andThrow(new Exception('I/O services disabled during update'));
        });

        $response = $this->actingAs($this->user)
            ->get(route('rtu.dashboard', $this->gateway));

        $response->assertStatus(200);
        
        // Should show appropriate messaging for maintenance
        $response->assertSee('updating');
        $response->assertSee('temporarily unavailable');
        
        // Should show cached data with appropriate indicators
        $response->assertSee('Cached Data');
    }

    public function test_handles_network_segmentation_scenario()
    {
        // Simulate network connectivity issues
        $this->mock(RTUDataService::class, function ($mock) {
            $mock->shouldReceive('getSystemHealth')
                ->andThrow(new Exception('Network unreachable'));
            
            $mock->shouldReceive('getNetworkStatus')
                ->andThrow(new Exception('Host unreachable'));
            
            $mock->shouldReceive('getIOStatus')
                ->andThrow(new Exception('Connection timed out'));
        });

        $response = $this->actingAs($this->user)
            ->get(route('rtu.dashboard', $this->gateway));

        $response->assertStatus(200);
        
        // Should show network-related troubleshooting
        $response->assertSee('unreachable');
        $response->assertSee('network connectivity');
        $response->assertSee('Troubleshooting Steps');
    }

    public function test_handles_hardware_malfunction_scenario()
    {
        // Test I/O control during hardware issues
        $this->mock(RTUDataService::class, function ($mock) {
            $mock->shouldReceive('setDigitalOutput')
                ->andReturn([
                    'success' => false,
                    'error_type' => 'hardware_failure',
                    'message' => 'I/O module hardware malfunction detected',
                    'retry_suggested' => false,
                    'troubleshooting_steps' => [
                        'Check I/O module status on RTU gateway',
                        'Verify physical connections to I/O terminals',
                        'Contact maintenance team for hardware inspection'
                    ],
                    'support_contact' => [
                        'type' => 'maintenance',
                        'urgency' => 'high'
                    ],
                    'fallback_action' => 'Manual control may be available directly on the RTU gateway device'
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
        
        $responseData = $response->json();
        $this->assertStringContains('hardware malfunction', $responseData['message']);
        $this->assertFalse($responseData['retry_suggested']);
        $this->assertEquals('high', $responseData['support_contact']['urgency']);
        $this->assertStringContains('Manual control', $responseData['fallback_action']);
    }

    public function test_handles_power_cycle_recovery_scenario()
    {
        $retryService = app(RTURetryService::class);
        
        // Simulate gateway coming back online after power cycle
        $this->mock(RTUDataService::class, function ($mock) {
            // First attempts fail (gateway offline)
            $mock->shouldReceive('getSystemHealth')
                ->twice()
                ->andThrow(new Exception('Connection refused'))
                // Third attempt succeeds (gateway back online)
                ->shouldReceive('getSystemHealth')
                ->once()
                ->andReturn([
                    'uptime_hours' => 0, // Recently restarted
                    'cpu_load' => 15.0,  // Low after restart
                    'memory_usage' => 25.0, // Low after restart
                    'health_score' => 90,
                    'status' => 'warning', // Warning due to recent restart
                    'last_updated' => now()
                ]);
        });

        $result = $retryService->retryDataCollection($this->gateway, 'system_health');
        
        // Should eventually succeed and show restart indicators
        $this->assertTrue($result['retry_successful'] ?? false);
        $this->assertEquals(0, $result['uptime_hours']); // Recently restarted
        $this->assertEquals('warning', $result['status']); // Warning status
    }

    public function test_handles_configuration_change_scenario()
    {
        // Simulate gateway configuration changes affecting API endpoints
        $this->mock(RTUDataService::class, function ($mock) {
            $mock->shouldReceive('getSystemHealth')
                ->andThrow(new Exception('API endpoint not found - configuration may have changed'));
            
            $mock->shouldReceive('getNetworkStatus')
                ->andReturn([
                    'wan_ip' => '10.0.0.52', // New IP after config change
                    'sim_iccid' => '89012345678901234567',
                    'connection_status' => 'online'
                ]);
            
            $mock->shouldReceive('getIOStatus')
                ->andReturn([
                    'digital_inputs' => [
                        'di1' => ['status' => false, 'label' => 'Digital Input 1'],
                        'di2' => ['status' => true, 'label' => 'Digital Input 2']
                    ]
                ]);
        });

        $response = $this->actingAs($this->user)
            ->get(route('rtu.dashboard', $this->gateway));

        $response->assertStatus(200);
        
        // Should show mixed results - some working, some failing
        $response->assertSee('configuration may have changed');
        $response->assertSee('10.0.0.52'); // New working network data
        $response->assertSee('Digital Input'); // Working I/O data
    }

    public function test_handles_concurrent_user_access_during_failures()
    {
        // Create multiple users
        $user2 = User::factory()->create();
        
        // Simulate failure scenario
        $this->mock(RTUDataService::class, function ($mock) {
            $mock->shouldReceive('getSystemHealth')
                ->andThrow(new Exception('Gateway overloaded'));
            
            $mock->shouldReceive('getNetworkStatus')
                ->andReturn(['connection_status' => 'degraded']);
            
            $mock->shouldReceive('getIOStatus')
                ->andReturn(['digital_inputs' => []]);
        });

        // Both users should be able to access dashboard with fallback data
        $response1 = $this->actingAs($this->user)
            ->get(route('rtu.dashboard', $this->gateway));
        
        $response2 = $this->actingAs($user2)
            ->get(route('rtu.dashboard', $this->gateway));

        $response1->assertStatus(200);
        $response2->assertStatus(200);
        
        // Both should see the same error and fallback data
        $response1->assertSee('Gateway overloaded');
        $response2->assertSee('Gateway overloaded');
    }

    public function test_handles_data_corruption_scenario()
    {
        // Simulate receiving corrupted/invalid data from gateway
        $this->mock(RTUDataService::class, function ($mock) {
            $mock->shouldReceive('getSystemHealth')
                ->andThrow(new Exception('Invalid response format - data may be corrupted'));
            
            $mock->shouldReceive('getNetworkStatus')
                ->andThrow(new Exception('Malformed JSON response'));
            
            $mock->shouldReceive('getIOStatus')
                ->andThrow(new Exception('Unexpected data format'));
        });

        $response = $this->actingAs($this->user)
            ->get(route('rtu.dashboard', $this->gateway));

        $response->assertStatus(200);
        
        // Should show data corruption indicators and fallback to cached data
        $response->assertSee('corrupted');
        $response->assertSee('Invalid response');
        $response->assertSee('Cached Data');
        
        // Should show troubleshooting for data issues
        $response->assertSee('Troubleshooting Steps');
    }

    public function test_recovery_after_extended_outage()
    {
        // Simulate extended outage followed by recovery
        $this->gateway->update([
            'last_system_update' => now()->subHours(2), // Old data
            'communication_status' => 'offline'
        ]);

        $this->mock(RTUDataService::class, function ($mock) {
            $mock->shouldReceive('getSystemHealth')
                ->andReturn([
                    'uptime_hours' => 1, // Recently recovered
                    'cpu_load' => 20.0,
                    'memory_usage' => 30.0,
                    'health_score' => 85,
                    'status' => 'normal',
                    'last_updated' => now() // Fresh data
                ]);
            
            $mock->shouldReceive('getNetworkStatus')
                ->andReturn([
                    'wan_ip' => '10.0.0.53',
                    'connection_status' => 'online'
                ]);
            
            $mock->shouldReceive('getIOStatus')
                ->andReturn([
                    'digital_inputs' => [
                        'di1' => ['status' => true, 'label' => 'Digital Input 1']
                    ]
                ]);
        });

        $response = $this->actingAs($this->user)
            ->get(route('rtu.dashboard', $this->gateway));

        $response->assertStatus(200);
        
        // Should show recovery indicators
        $response->assertSee('1'); // Low uptime indicating recent recovery
        $response->assertSee('online');
        $response->assertDontSee('Cached Data'); // Should have fresh data
        $response->assertDontSee('error'); // No errors after recovery
    }
}