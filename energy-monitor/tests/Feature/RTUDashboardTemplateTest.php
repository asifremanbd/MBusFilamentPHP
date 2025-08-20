<?php

namespace Tests\Feature;

use App\Models\Gateway;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class RTUDashboardTemplateTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected Gateway $rtuGateway;
    protected Gateway $standardGateway;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user
        $this->user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Create RTU gateway
        $this->rtuGateway = Gateway::factory()->create([
            'name' => 'Test RTU Gateway',
            'gateway_type' => 'teltonika_rut956',
            'communication_status' => 'online',
            'ip_address' => '192.168.1.100',
            'port' => 502,
            'cpu_load' => 45.5,
            'memory_usage' => 67.2,
            'uptime_hours' => 168,
            'wan_ip' => '203.0.113.1',
            'sim_iccid' => '8901234567890123456',
            'sim_apn' => 'internet.provider.com',
            'sim_operator' => 'Test Operator',
            'rssi' => -75,
            'rsrp' => -105,
            'rsrq' => -12,
            'sinr' => 8,
            'di1_status' => true,
            'di2_status' => false,
            'do1_status' => false,
            'do2_status' => true,
            'analog_input_voltage' => 5.25,
            'last_system_update' => now()->subMinutes(5)
        ]);

        // Create standard gateway
        $this->standardGateway = Gateway::factory()->create([
            'name' => 'Test Standard Gateway',
            'gateway_type' => 'generic',
            'communication_status' => 'online',
            'ip_address' => '192.168.1.101',
            'port' => 502
        ]);
    }

    /** @test */
    public function it_renders_rtu_dashboard_template_successfully()
    {
        $response = $this->actingAs($this->user)
            ->get(route('dashboard.rtu', $this->rtuGateway));

        $response->assertStatus(200);
        $response->assertViewIs('dashboard.rtu');
        $response->assertViewHas('gateway', $this->rtuGateway);
    }

    /** @test */
    public function it_displays_gateway_information_in_header()
    {
        $response = $this->actingAs($this->user)
            ->get(route('dashboard.rtu', $this->rtuGateway));

        $response->assertStatus(200);
        $response->assertSee($this->rtuGateway->name);
        $response->assertSee('RTU Gateway Dashboard');
        $response->assertSee('RTU Gateway'); // Badge
    }

    /** @test */
    public function it_shows_communication_status_indicator()
    {
        $response = $this->actingAs($this->user)
            ->get(route('dashboard.rtu', $this->rtuGateway));

        $response->assertStatus(200);
        $response->assertSee('Communication');
        $response->assertSee('Online');
    }

    /** @test */
    public function it_displays_quick_status_overview_cards()
    {
        $response = $this->actingAs($this->user)
            ->get(route('dashboard.rtu', $this->rtuGateway));

        $response->assertStatus(200);
        
        // Check for status cards
        $response->assertSee('Communication');
        $response->assertSee('System Health');
        $response->assertSee('Active Alerts');
        $response->assertSee('Last Update');
    }

    /** @test */
    public function it_includes_navigation_controls()
    {
        $response = $this->actingAs($this->user)
            ->get(route('dashboard.rtu', $this->rtuGateway));

        $response->assertStatus(200);
        
        // Check for navigation elements
        $response->assertSee('Standard View');
        $response->assertSee('Refresh');
        $response->assertSee('Auto-refresh');
    }

    /** @test */
    public function it_displays_breadcrumb_navigation()
    {
        $response = $this->actingAs($this->user)
            ->get(route('dashboard.rtu', $this->rtuGateway));

        $response->assertStatus(200);
        
        // Check breadcrumb elements
        $response->assertSee('Dashboard');
        $response->assertSee('Gateways');
        $response->assertSee($this->rtuGateway->name);
        $response->assertSee('RTU Dashboard');
    }

    /** @test */
    public function it_includes_livewire_widgets()
    {
        $response = $this->actingAs($this->user)
            ->get(route('dashboard.rtu', $this->rtuGateway));

        $response->assertStatus(200);
        
        // Check for Livewire widget components
        $response->assertSee('rtu-system-health-widget');
        $response->assertSee('rtu-network-status-widget');
        $response->assertSee('rtu-io-monitoring-widget');
        $response->assertSee('rtu-alerts-widget');
        $response->assertSee('rtu-trend-widget');
    }

    /** @test */
    public function it_includes_javascript_functionality()
    {
        $response = $this->actingAs($this->user)
            ->get(route('dashboard.rtu', $this->rtuGateway));

        $response->assertStatus(200);
        
        // Check for JavaScript functions
        $response->assertSee('refreshRTUDashboard');
        $response->assertSee('toggleAutoRefresh');
        $response->assertSee('initializeRTUDashboard');
    }

    /** @test */
    public function it_redirects_non_rtu_gateway_to_error_page()
    {
        $response = $this->actingAs($this->user)
            ->get(route('dashboard.rtu', $this->standardGateway));

        $response->assertStatus(400);
        $response->assertViewIs('dashboard.error');
        $response->assertSee('Invalid Gateway Type');
    }

    /** @test */
    public function it_requires_authentication()
    {
        $response = $this->get(route('dashboard.rtu', $this->rtuGateway));

        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function it_handles_unauthorized_access()
    {
        // Create user without permissions
        $unauthorizedUser = User::factory()->create([
            'name' => 'Unauthorized User',
            'email' => 'unauthorized@example.com',
        ]);

        // Mock authorization failure
        $this->mock(\Illuminate\Contracts\Auth\Access\Gate::class)
            ->shouldReceive('authorize')
            ->with('view', $this->rtuGateway)
            ->andThrow(new \Illuminate\Auth\Access\AuthorizationException());

        $response = $this->actingAs($unauthorizedUser)
            ->get(route('dashboard.rtu', $this->rtuGateway));

        $response->assertStatus(403);
        $response->assertViewIs('dashboard.unauthorized');
    }

    /** @test */
    public function it_displays_responsive_design_classes()
    {
        $response = $this->actingAs($this->user)
            ->get(route('dashboard.rtu', $this->rtuGateway));

        $response->assertStatus(200);
        
        // Check for responsive grid classes
        $response->assertSee('grid-cols-1');
        $response->assertSee('md:grid-cols-2');
        $response->assertSee('lg:grid-cols-4');
    }

    /** @test */
    public function it_includes_loading_overlay()
    {
        $response = $this->actingAs($this->user)
            ->get(route('dashboard.rtu', $this->rtuGateway));

        $response->assertStatus(200);
        $response->assertSee('rtu-loading-overlay');
        $response->assertSee('Refreshing RTU Dashboard');
    }

    /** @test */
    public function it_passes_correct_data_to_view()
    {
        $response = $this->actingAs($this->user)
            ->get(route('dashboard.rtu', $this->rtuGateway));

        $response->assertStatus(200);
        $response->assertViewHas([
            'gateway',
            'systemHealth',
            'networkStatus',
            'ioStatus',
            'groupedAlerts',
            'trendData',
            'config',
            'user',
            'dashboard_type'
        ]);

        $viewData = $response->viewData('gateway');
        $this->assertEquals($this->rtuGateway->id, $viewData->id);
        
        $dashboardType = $response->viewData('dashboard_type');
        $this->assertEquals('rtu', $dashboardType);
    }

    /** @test */
    public function it_uses_correct_layout()
    {
        $response = $this->actingAs($this->user)
            ->get(route('dashboard.rtu', $this->rtuGateway));

        $response->assertStatus(200);
        
        // Check that RTU dashboard layout is used
        $response->assertSee('RTU Dashboard layout loaded');
        $response->assertSee('rtu-sections.js');
        $response->assertSee('rtu-widgets.js');
    }

    /** @test */
    public function it_handles_missing_gateway_gracefully()
    {
        $response = $this->actingAs($this->user)
            ->get('/dashboard/rtu/999999'); // Non-existent gateway ID

        $response->assertStatus(404);
    }

    /** @test */
    public function it_displays_gateway_context_indicators()
    {
        $response = $this->actingAs($this->user)
            ->get(route('dashboard.rtu', $this->rtuGateway));

        $response->assertStatus(200);
        
        // Check for gateway context in navigation
        $response->assertSee($this->rtuGateway->name);
        $response->assertSee('Teltonika RUT956');
    }

    /** @test */
    public function it_includes_proper_meta_tags()
    {
        $response = $this->actingAs($this->user)
            ->get(route('dashboard.rtu', $this->rtuGateway));

        $response->assertStatus(200);
        $response->assertSee('<meta name="csrf-token"', false);
        $response->assertSee('RTU Dashboard', false);
    }

    /** @test */
    public function navigation_links_work_correctly()
    {
        // Test RTU to Standard navigation
        $response = $this->actingAs($this->user)
            ->get(route('dashboard.rtu', $this->rtuGateway));

        $response->assertStatus(200);
        $response->assertSee(route('dashboard.gateway', $this->rtuGateway));

        // Test Standard to RTU navigation (when viewing standard dashboard)
        $response = $this->actingAs($this->user)
            ->get(route('dashboard.gateway', $this->rtuGateway));

        $response->assertStatus(200);
        // Should include link to RTU dashboard if gateway supports it
        if ($this->rtuGateway->isRTUGateway()) {
            $response->assertSee(route('dashboard.rtu', $this->rtuGateway));
        }
    }

    /** @test */
    public function it_handles_offline_gateway_status()
    {
        $this->rtuGateway->update(['communication_status' => 'offline']);

        $response = $this->actingAs($this->user)
            ->get(route('dashboard.rtu', $this->rtuGateway));

        $response->assertStatus(200);
        $response->assertSee('Offline');
    }

    /** @test */
    public function it_displays_warning_status_correctly()
    {
        $this->rtuGateway->update(['communication_status' => 'warning']);

        $response = $this->actingAs($this->user)
            ->get(route('dashboard.rtu', $this->rtuGateway));

        $response->assertStatus(200);
        $response->assertSee('Warning');
    }
}