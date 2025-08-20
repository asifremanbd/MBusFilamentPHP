<?php

namespace Tests\Feature;

use App\Models\Gateway;
use App\Models\User;
use App\Services\RTUDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Mocks\RTUGatewayMock;

class RTUTeltonikaIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Gateway $teltonikaGateway;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->teltonikaGateway = Gateway::factory()->create([
            'gateway_type' => 'teltonika_rut956',
            'fixed_ip' => '192.168.1.100',
            'name' => 'Test Teltonika RUT956'
        ]);
    }

    /** @test */
    public function it_simulates_teltonika_rut956_system_info_api_call()
    {
        // Mock Teltonika API response
        Http::fake([
            'http://192.168.1.100/cgi-bin/luci/rpc/sys' => Http::response(
                RTUGatewayMock::getTeltonikaRUT956Response('system_info'), 200
            )
        ]);

        $rtuDataService = app(RTUDataService::class);
        $systemHealth = $rtuDataService->getSystemHealth($this->teltonikaGateway);

        $this->assertArrayHasKey('uptime_hours', $systemHealth);
        $this->assertArrayHasKey('cpu_load', $systemHealth);
        $this->assertArrayHasKey('memory_usage', $systemHealth);
        $this->assertArrayHasKey('health_score', $systemHealth);
        
        // Verify data format matches expected Teltonika response
        $this->assertIsNumeric($systemHealth['uptime_hours']);
        $this->assertIsNumeric($systemHealth['cpu_load']);
        $this->assertIsNumeric($systemHealth['memory_usage']);
    }

    /** @test */
    public function it_simulates_teltonika_rut956_network_info_api_call()
    {
        Http::fake([
            'http://192.168.1.100/cgi-bin/luci/rpc/network' => Http::response(
                RTUGatewayMock::getTeltonikaRUT956Response('network_info'), 200
            )
        ]);

        $rtuDataService = app(RTUDataService::class);
        $networkStatus = $rtuDataService->getNetworkStatus($this->teltonikaGateway);

        $this->assertArrayHasKey('wan_ip', $networkStatus);
        $this->assertArrayHasKey('sim_iccid', $networkStatus);
        $this->assertArrayHasKey('sim_apn', $networkStatus);
        $this->assertArrayHasKey('sim_operator', $networkStatus);
        $this->assertArrayHasKey('signal_quality', $networkStatus);
        
        // Verify signal quality structure
        $signalQuality = $networkStatus['signal_quality'];
        $this->assertArrayHasKey('rssi', $signalQuality);
        $this->assertArrayHasKey('rsrp', $signalQuality);
        $this->assertArrayHasKey('rsrq', $signalQuality);
        $this->assertArrayHasKey('sinr', $signalQuality);
        $this->assertArrayHasKey('status', $signalQuality);
    }

    /** @test */
    public function it_simulates_teltonika_rut956_io_status_api_call()
    {
        Http::fake([
            'http://192.168.1.100/cgi-bin/luci/rpc/io' => Http::response(
                RTUGatewayMock::getTeltonikaRUT956Response('io_status'), 200
            )
        ]);

        $rtuDataService = app(RTUDataService::class);
        $ioStatus = $rtuDataService->getIOStatus($this->teltonikaGateway);

        $this->assertArrayHasKey('digital_inputs', $ioStatus);
        $this->assertArrayHasKey('digital_outputs', $ioStatus);
        $this->assertArrayHasKey('analog_input', $ioStatus);
        
        // Verify digital inputs structure
        $digitalInputs = $ioStatus['digital_inputs'];
        $this->assertArrayHasKey('di1', $digitalInputs);
        $this->assertArrayHasKey('di2', $digitalInputs);
        
        // Verify digital outputs structure
        $digitalOutputs = $ioStatus['digital_outputs'];
        $this->assertArrayHasKey('do1', $digitalOutputs);
        $this->assertArrayHasKey('do2', $digitalOutputs);
        $this->assertTrue($digitalOutputs['do1']['controllable']);
        
        // Verify analog input structure
        $analogInput = $ioStatus['analog_input'];
        $this->assertArrayHasKey('voltage', $analogInput);
        $this->assertArrayHasKey('unit', $analogInput);
        $this->assertEquals('V', $analogInput['unit']);
    }

    /** @test */
    public function it_simulates_teltonika_rut956_digital_output_control()
    {
        Http::fake([
            'http://192.168.1.100/cgi-bin/luci/rpc/io/set_output' => Http::response(
                RTUGatewayMock::getTeltonikaRUT956Response('set_digital_output', [
                    'output' => 'do1',
                    'state' => true
                ]), 200
            )
        ]);

        $rtuDataService = app(RTUDataService::class);
        $result = $rtuDataService->setDigitalOutput($this->teltonikaGateway, 'do1', true);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('new_state', $result);
        $this->assertTrue($result['new_state']);
    }

    /** @test */
    public function it_handles_teltonika_rut956_authentication_failure()
    {
        Http::fake([
            'http://192.168.1.100/cgi-bin/luci/rpc/sys' => Http::response([
                'error' => 'Authentication required'
            ], 401)
        ]);

        $rtuDataService = app(RTUDataService::class);
        $systemHealth = $rtuDataService->getSystemHealth($this->teltonikaGateway);

        // Should return unavailable data structure on auth failure
        $this->assertEquals('unavailable', $systemHealth['status']);
        $this->assertNull($systemHealth['uptime_hours']);
        $this->assertNull($systemHealth['cpu_load']);
        $this->assertNull($systemHealth['memory_usage']);
    }

    /** @test */
    public function it_handles_teltonika_rut956_network_timeout()
    {
        Http::fake([
            'http://192.168.1.100/cgi-bin/luci/rpc/network' => Http::response([], 408) // Timeout
        ]);

        $rtuDataService = app(RTUDataService::class);
        $networkStatus = $rtuDataService->getNetworkStatus($this->teltonikaGateway);

        // Should return unavailable data structure on timeout
        $this->assertEquals('unavailable', $networkStatus['connection_status']);
        $this->assertEquals('Data unavailable', $networkStatus['wan_ip']);
        $this->assertEquals('unknown', $networkStatus['signal_quality']['status']);
    }

    /** @test */
    public function it_simulates_teltonika_rut956_firmware_version_detection()
    {
        Http::fake([
            'http://192.168.1.100/cgi-bin/luci/rpc/sys' => Http::response([
                'firmware_version' => 'RUT9_R_00.07.06.1',
                'model' => 'RUT956',
                'uptime' => 604800,
                'cpu_load' => 42.5,
                'memory_usage' => 68.3
            ], 200)
        ]);

        $rtuDataService = app(RTUDataService::class);
        $systemHealth = $rtuDataService->getSystemHealth($this->teltonikaGateway);

        // Verify firmware version is properly handled
        $this->assertEquals('normal', $systemHealth['status']);
        $this->assertGreaterThan(0, $systemHealth['uptime_hours']);
    }

    /** @test */
    public function it_simulates_teltonika_rut956_signal_quality_variations()
    {
        $signalScenarios = [
            ['rssi' => -50, 'expected_status' => 'excellent'],
            ['rssi' => -75, 'expected_status' => 'good'],
            ['rssi' => -90, 'expected_status' => 'fair'],
            ['rssi' => -110, 'expected_status' => 'poor']
        ];

        foreach ($signalScenarios as $scenario) {
            Http::fake([
                'http://192.168.1.100/cgi-bin/luci/rpc/network' => Http::response([
                    'wan_ip' => '10.0.0.100',
                    'sim_iccid' => '89012345678901234567',
                    'apn' => 'internet',
                    'operator' => 'Test Mobile',
                    'rssi' => $scenario['rssi'],
                    'rsrp' => -97,
                    'rsrq' => -12,
                    'sinr' => 13,
                    'connection_status' => 'connected'
                ], 200)
            ]);

            $rtuDataService = app(RTUDataService::class);
            $networkStatus = $rtuDataService->getNetworkStatus($this->teltonikaGateway);

            $this->assertEquals(
                $scenario['expected_status'], 
                $networkStatus['signal_quality']['status'],
                "RSSI {$scenario['rssi']} should result in {$scenario['expected_status']} status"
            );
        }
    }

    /** @test */
    public function it_simulates_teltonika_rut956_io_module_failure()
    {
        Http::fake([
            'http://192.168.1.100/cgi-bin/luci/rpc/io' => Http::response([
                'error' => 'I/O module not responding'
            ], 503)
        ]);

        $rtuDataService = app(RTUDataService::class);
        $ioStatus = $rtuDataService->getIOStatus($this->teltonikaGateway);

        // Should return unavailable I/O status
        $this->assertNull($ioStatus['digital_inputs']['di1']['status']);
        $this->assertNull($ioStatus['digital_outputs']['do1']['status']);
        $this->assertFalse($ioStatus['digital_outputs']['do1']['controllable']);
        $this->assertNull($ioStatus['analog_input']['voltage']);
    }

    /** @test */
    public function it_simulates_teltonika_rut956_complete_dashboard_integration()
    {
        // Mock all Teltonika API endpoints
        Http::fake([
            'http://192.168.1.100/cgi-bin/luci/rpc/sys' => Http::response(
                RTUGatewayMock::getTeltonikaRUT956Response('system_info'), 200
            ),
            'http://192.168.1.100/cgi-bin/luci/rpc/network' => Http::response(
                RTUGatewayMock::getTeltonikaRUT956Response('network_info'), 200
            ),
            'http://192.168.1.100/cgi-bin/luci/rpc/io' => Http::response(
                RTUGatewayMock::getTeltonikaRUT956Response('io_status'), 200
            )
        ]);

        // Test complete RTU dashboard integration
        $response = $this->actingAs($this->user)
            ->get(route('dashboard.rtu', $this->teltonikaGateway));

        $response->assertStatus(200)
            ->assertViewIs('dashboard.rtu')
            ->assertViewHas('gateway', $this->teltonikaGateway)
            ->assertSee('RTU System Health')
            ->assertSee('Network Status')
            ->assertSee('I/O Monitoring');

        // Verify HTTP requests were made to Teltonika endpoints
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '192.168.1.100');
        });
    }

    /** @test */
    public function it_simulates_teltonika_rut956_data_persistence()
    {
        Http::fake([
            'http://192.168.1.100/cgi-bin/luci/rpc/sys' => Http::response(
                RTUGatewayMock::getTeltonikaRUT956Response('system_info'), 200
            )
        ]);

        $rtuDataService = app(RTUDataService::class);
        
        // First call should update gateway model
        $systemHealth = $rtuDataService->getSystemHealth($this->teltonikaGateway);
        
        // Refresh gateway from database
        $this->teltonikaGateway->refresh();
        
        // Verify data was persisted to gateway model
        $this->assertNotNull($this->teltonikaGateway->cpu_load);
        $this->assertNotNull($this->teltonikaGateway->memory_usage);
        $this->assertNotNull($this->teltonikaGateway->uptime_hours);
        $this->assertNotNull($this->teltonikaGateway->last_system_update);
        $this->assertEquals('online', $this->teltonikaGateway->communication_status);
    }

    /** @test */
    public function it_simulates_teltonika_rut956_error_recovery()
    {
        // First request fails
        Http::fake([
            'http://192.168.1.100/cgi-bin/luci/rpc/sys' => Http::response([], 500)
        ]);

        $rtuDataService = app(RTUDataService::class);
        $systemHealth1 = $rtuDataService->getSystemHealth($this->teltonikaGateway);
        
        $this->assertEquals('unavailable', $systemHealth1['status']);

        // Second request succeeds
        Http::fake([
            'http://192.168.1.100/cgi-bin/luci/rpc/sys' => Http::response(
                RTUGatewayMock::getTeltonikaRUT956Response('system_info'), 200
            )
        ]);

        $systemHealth2 = $rtuDataService->getSystemHealth($this->teltonikaGateway);
        
        $this->assertEquals('normal', $systemHealth2['status']);
        $this->assertNotNull($systemHealth2['uptime_hours']);
    }
}