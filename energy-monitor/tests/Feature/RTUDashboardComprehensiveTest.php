<?php

namespace Tests\Feature;

use App\Models\Gateway;
use App\Models\User;
use App\Models\Alert;
use App\Models\Reading;
use App\Services\RTUDataService;
use App\Services\RTUAlertService;
use App\Services\RTUDashboardConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Mockery;

class RTUDashboardComprehensiveTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected Gateway $rtuGateway;
    protected Gateway $standardGateway;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->rtuGateway = Gateway::factory()->rtu()->create();
        $this->standardGateway = Gateway::factory()->create();
    }

    /** @test */
    public function it_displays_rtu_dashboard_for_authenticated_user()
    {
        $this->actingAs($this->user)
            ->get(route('dashboard.rtu', $this->rtuGateway))
            ->assertStatus(200)
            ->assertViewIs('dashboard.rtu')
            ->assertViewHas('gateway', $this->rtuGateway);
    }

    /** @test */
    public function it_redirects_non_rtu_gateways_to_standard_dashboard()
    {
        $this->actingAs($this->user)
            ->get(route('dashboard.rtu', $this->standardGateway))
            ->assertRedirect(route('dashboard.gateway', $this->standardGateway))
            ->assertSessionHas('warning');
    }

    /** @test */
    public function it_loads_all_required_rtu_data_components()
    {
        $mockRTUDataService = Mockery::mock(RTUDataService::class);
        $mockRTUAlertService = Mockery::mock(RTUAlertService::class);
        $mockConfigService = Mockery::mock(RTUDashboardConfigService::class);

        $mockRTUDataService->shouldReceive('getSystemHealth')
            ->with($this->rtuGateway)
            ->once()
            ->andReturn([
                'uptime_hours' => 168,
                'cpu_load' => 45.2,
                'memory_usage' => 67.8,
                'health_score' => 85,
                'status' => 'normal'
            ]);

        $mockRTUDataService->shouldReceive('getNetworkStatus')
            ->with($this->rtuGateway)
            ->once()
            ->andReturn([
                'wan_ip' => '192.168.1.100',
                'sim_iccid' => '89012345678901234567',
                'signal_quality' => ['rssi' => -65, 'status' => 'good']
            ]);

        $mockRTUDataService->shouldReceive('getIOStatus')
            ->with($this->rtuGateway)
            ->once()
            ->andReturn([
                'digital_inputs' => ['di1' => ['status' => true]],
                'digital_outputs' => ['do1' => ['status' => false]],
                'analog_input' => ['voltage' => 5.2]
            ]);

        $mockRTUDataService->shouldReceive('getTrendData')
            ->with($this->rtuGateway, '24h')
            ->once()
            ->andReturn(['has_data' => true, 'metrics' => []]);

        $mockRTUAlertService->shouldReceive('getGroupedAlerts')
            ->with($this->rtuGateway)
            ->once()
            ->andReturn([
                'critical_count' => 0,
                'warning_count' => 2,
                'has_alerts' => true
            ]);

        $mockConfigService->shouldReceive('getUserDashboardConfig')
            ->with($this->user, 'rtu')
            ->once()
            ->andReturn(['sections' => []]);

        $this->app->instance(RTUDataService::class, $mockRTUDataService);
        $this->app->instance(RTUAlertService::class, $mockRTUAlertService);
        $this->app->instance(RTUDashboardConfigService::class, $mockConfigService);

        $response = $this->actingAs($this->user)
            ->get(route('dashboard.rtu', $this->rtuGateway));

        $response->assertStatus(200)
            ->assertViewHas('systemHealth')
            ->assertViewHas('networkStatus')
            ->assertViewHas('ioStatus')
            ->assertViewHas('groupedAlerts')
            ->assertViewHas('trendData')
            ->assertViewHas('config');
    }

    /** @test */
    public function it_handles_digital_output_control_successfully()
    {
        $mockRTUDataService = Mockery::mock(RTUDataService::class);
        $mockRTUDataService->shouldReceive('setDigitalOutput')
            ->with($this->rtuGateway, 'do1', true)
            ->once()
            ->andReturn([
                'success' => true,
                'message' => 'Digital output do1 set to ON',
                'new_state' => true
            ]);

        $this->app->instance(RTUDataService::class, $mockRTUDataService);

        $response = $this->actingAs($this->user)
            ->postJson(route('api.rtu.digital-output', [
                'gateway' => $this->rtuGateway,
                'output' => 'do1'
            ]), ['state' => true]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Digital output do1 set to ON',
                'new_state' => true
            ]);
    }

    /** @test */
    public function it_handles_digital_output_control_failure()
    {
        $mockRTUDataService = Mockery::mock(RTUDataService::class);
        $mockRTUDataService->shouldReceive('setDigitalOutput')
            ->with($this->rtuGateway, 'do1', true)
            ->once()
            ->andReturn([
                'success' => false,
                'message' => 'Communication timeout with RTU gateway'
            ]);

        $this->app->instance(RTUDataService::class, $mockRTUDataService);

        $response = $this->actingAs($this->user)
            ->postJson(route('api.rtu.digital-output', [
                'gateway' => $this->rtuGateway,
                'output' => 'do1'
            ]), ['state' => true]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => false,
                'message' => 'Communication timeout with RTU gateway'
            ]);
    }

    /** @test */
    public function it_validates_digital_output_control_input()
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('api.rtu.digital-output', [
                'gateway' => $this->rtuGateway,
                'output' => 'do1'
            ]), ['state' => 'invalid']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['state']);
    }

    /** @test */
    public function it_requires_authentication_for_rtu_dashboard()
    {
        $this->get(route('dashboard.rtu', $this->rtuGateway))
            ->assertRedirect(route('login'));
    }

    /** @test */
    public function it_requires_authentication_for_digital_output_control()
    {
        $this->postJson(route('api.rtu.digital-output', [
            'gateway' => $this->rtuGateway,
            'output' => 'do1'
        ]), ['state' => true])
            ->assertStatus(401);
    }

    /** @test */
    public function it_handles_service_exceptions_gracefully()
    {
        $mockRTUDataService = Mockery::mock(RTUDataService::class);
        $mockRTUDataService->shouldReceive('getSystemHealth')
            ->with($this->rtuGateway)
            ->once()
            ->andThrow(new \Exception('RTU communication failed'));

        $mockRTUDataService->shouldReceive('getNetworkStatus')
            ->with($this->rtuGateway)
            ->once()
            ->andReturn(['connection_status' => 'unavailable']);

        $mockRTUDataService->shouldReceive('getIOStatus')
            ->with($this->rtuGateway)
            ->once()
            ->andReturn(['digital_inputs' => [], 'digital_outputs' => []]);

        $mockRTUDataService->shouldReceive('getTrendData')
            ->with($this->rtuGateway, '24h')
            ->once()
            ->andReturn(['has_data' => false]);

        $mockRTUAlertService = Mockery::mock(RTUAlertService::class);
        $mockRTUAlertService->shouldReceive('getGroupedAlerts')
            ->with($this->rtuGateway)
            ->once()
            ->andReturn(['has_alerts' => false]);

        $mockConfigService = Mockery::mock(RTUDashboardConfigService::class);
        $mockConfigService->shouldReceive('getUserDashboardConfig')
            ->with($this->user, 'rtu')
            ->once()
            ->andReturn(['sections' => []]);

        $this->app->instance(RTUDataService::class, $mockRTUDataService);
        $this->app->instance(RTUAlertService::class, $mockRTUAlertService);
        $this->app->instance(RTUDashboardConfigService::class, $mockConfigService);

        $response = $this->actingAs($this->user)
            ->get(route('dashboard.rtu', $this->rtuGateway));

        $response->assertStatus(200);
        // Should still render the dashboard even with service failures
    }

    /** @test */
    public function it_displays_correct_widget_data_structure()
    {
        $this->actingAs($this->user)
            ->get(route('dashboard.rtu', $this->rtuGateway))
            ->assertStatus(200)
            ->assertSee('RTU System Health')
            ->assertSee('Network Status')
            ->assertSee('I/O Monitoring')
            ->assertSee('Active Alerts')
            ->assertSee('Trend Analysis');
    }

    /** @test */
    public function it_handles_concurrent_dashboard_access()
    {
        $users = User::factory()->count(5)->create();
        $responses = [];

        foreach ($users as $user) {
            $responses[] = $this->actingAs($user)
                ->get(route('dashboard.rtu', $this->rtuGateway));
        }

        foreach ($responses as $response) {
            $response->assertStatus(200);
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}