<?php

namespace Tests\Feature;

use App\Models\Gateway;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class RTUDashboardNavigationTest extends TestCase
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
            'port' => 502
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
    public function it_can_navigate_from_standard_to_rtu_dashboard()
    {
        // First visit standard dashboard
        $response = $this->actingAs($this->user)
            ->get(route('dashboard.gateway', $this->rtuGateway));

        $response->assertStatus(200);
        
        // Should contain link to RTU dashboard
        $response->assertSee(route('dashboard.rtu', $this->rtuGateway));

        // Now navigate to RTU dashboard
        $response = $this->actingAs($this->user)
            ->get(route('dashboard.rtu', $this->rtuGateway));

        $response->assertStatus(200);
        $response->assertViewIs('dashboard.rtu');
    }

    /** @test */
    public function it_can_navigate_from_rtu_to_standard_dashboard()
    {
        // First visit RTU dashboard
        $response = $this->actingAs($this->user)
            ->get(route('dashboard.rtu', $this->rtuGateway));

        $response->assertStatus(200);
        
        // Should contain link to standard dashboard
        $response->assertSee(route('dashboard.gateway', $this->rtuGateway));

        // Now navigate to standard dashboard
        $response = $this->actingAs($this->user)
            ->get(route('dashboard.gateway', $this->rtuGateway));

        $response->assertStatus(200);
        $response->assertViewIs('dashboard.gateway');
    }

    /** @test */
    public function it_shows_correct_navigation_breadcrumbs_on_rtu_dashboard()
    {
        $response = $this->actingAs($this->user)
            ->get(route('dashboard.rtu', $this->rtuGateway));

        $response->assertStatus(200);
        
        // Check breadcrumb structure
        $response->assertSee('Dashboard'); // Root
        $response->assertSee('Gateways'); // Section
        $response->assertSee($this->rtuGateway->name); // Gateway name
        $response->assertSee('RTU Dashboard'); // Current page
    }

    /** @test */
    public function it_shows_correct_navigation_breadcrumbs_on_standard_dashboard()
    {
        $response = $this->actingAs($this->user)
            ->get(route('dashboard.gateway', $this->rtuGateway));

        $response->assertStatus(200);
        
        // Check breadcrumb structure
        $response->assertSee('Dashboard'); // Root
        $response->assertSee('Gateways'); // Section
        $response->assertSee($this->rtuGateway->name); // Gateway name
        $response->assertSee('Standard View'); // Current page
    }

    /** @test */
    public function it_displays_gateway_selector_with_rtu_indicators()
    {
        $response = $this->actingAs($this->user)
            ->get(route('dashboard.rtu', $this->rtuGateway));

        $response->assertStatus(200);
        
        // Should show RTU badge for RTU gateways
        $response->assertSee('RTU');
        $response->assertSee($this->rtuGateway->name);
    }

    /** @test */
    public function it_shows_dashboard_type_indicators()
    {
        // Test RTU dashboard indicator
        $response = $this->actingAs($this->user)
            ->get(route('dashboard.rtu', $this->rtuGateway));

        $response->assertStatus(200);
        $response->assertSee('RTU Dashboard');
        $response->assertSee('Switch to Standard');

        // Test standard dashboard indicator
        $response = $this->actingAs($this->user)
            ->get(route('dashboard.gateway', $this->rtuGateway));

        $response->assertStatus(200);
        $response->assertSee('Standard Dashboard');
        $response->assertSee('Switch to RTU');
    }

    /** @test */
    public function it_does_not_show_rtu_navigation_for_non_rtu_gateways()
    {
        $response = $this->actingAs($this->user)
            ->get(route('dashboard.gateway', $this->standardGateway));

        $response->assertStatus(200);
        
        // Should not show RTU dashboard link for non-RTU gateways
        $response->assertDontSee(route('dashboard.rtu', $this->standardGateway));
        $response->assertDontSee('Switch to RTU');
    }

    /** @test */
    public function it_handles_navigation_with_proper_route_parameters()
    {
        // Test that gateway parameter is correctly passed
        $response = $this->actingAs($this->user)
            ->get(route('dashboard.rtu', ['gateway' => $this->rtuGateway->id]));

        $response->assertStatus(200);
        $response->assertViewHas('gateway');
        
        $gateway = $response->viewData('gateway');
        $this->assertEquals($this->rtuGateway->id, $gateway->id);
    }

    /** @test */
    public function it_maintains_gateway_context_across_navigation()
    {
        // Start on standard dashboard
        $response = $this->actingAs($this->user)
            ->get(route('dashboard.gateway', $this->rtuGateway));

        $response->assertStatus(200);
        $response->assertSee($this->rtuGateway->name);

        // Navigate to RTU dashboard
        $response = $this->actingAs($this->user)
            ->get(route('dashboard.rtu', $this->rtuGateway));

        $response->assertStatus(200);
        $response->assertSee($this->rtuGateway->name);
        
        // Gateway context should be maintained
        $gateway = $response->viewData('gateway');
        $this->assertEquals($this->rtuGateway->id, $gateway->id);
    }

    /** @test */
    public function it_shows_correct_active_navigation_state()
    {
        // Test RTU dashboard active state
        $response = $this->actingAs($this->user)
            ->get(route('dashboard.rtu', $this->rtuGateway));

        $response->assertStatus(200);
        
        // Should show RTU Dashboard as active
        $response->assertSee('border-indigo-500'); // Active border class
        $response->assertSee('RTU Dashboard');

        // Test standard dashboard active state
        $response = $this->actingAs($this->user)
            ->get(route('dashboard.gateway', $this->rtuGateway));

        $response->assertStatus(200);
        
        // Should show appropriate navigation state
        $response->assertSee('Standard Dashboard');
    }

    /** @test */
    public function it_includes_proper_navigation_links_in_layout()
    {
        $response = $this->actingAs($this->user)
            ->get(route('dashboard.rtu', $this->rtuGateway));

        $response->assertStatus(200);
        
        // Check for navigation links in RTU layout
        $response->assertSee('Standard Dashboard');
        $response->assertSee('Gateway View');
        $response->assertSee('RTU Dashboard');
    }

    /** @test */
    public function it_handles_navigation_with_different_gateway_statuses()
    {
        // Test with offline gateway
        $this->rtuGateway->update(['communication_status' => 'offline']);

        $response = $this->actingAs($this->user)
            ->get(route('dashboard.rtu', $this->rtuGateway));

        $response->assertStatus(200);
        $response->assertSee('Offline');

        // Navigation should still work
        $response->assertSee(route('dashboard.gateway', $this->rtuGateway));

        // Test with warning status
        $this->rtuGateway->update(['communication_status' => 'warning']);

        $response = $this->actingAs($this->user)
            ->get(route('dashboard.rtu', $this->rtuGateway));

        $response->assertStatus(200);
        $response->assertSee('Warning');
    }

    /** @test */
    public function it_provides_correct_navigation_urls()
    {
        $response = $this->actingAs($this->user)
            ->get(route('dashboard.rtu', $this->rtuGateway));

        $response->assertStatus(200);
        
        // Check that navigation URLs are correctly generated
        $expectedStandardUrl = route('dashboard.gateway', $this->rtuGateway);
        $expectedFilamentUrl = route('filament.admin.pages.dashboard');
        
        $response->assertSee($expectedStandardUrl);
        $response->assertSee($expectedFilamentUrl);
    }

    /** @test */
    public function it_shows_gateway_status_in_navigation()
    {
        $response = $this->actingAs($this->user)
            ->get(route('dashboard.rtu', $this->rtuGateway));

        $response->assertStatus(200);
        
        // Should show gateway status in selector
        $response->assertSee('Online'); // Communication status
        $response->assertSee($this->rtuGateway->ip_address); // IP address
    }

    /** @test */
    public function it_handles_multiple_gateways_in_selector()
    {
        // Create additional gateways
        $anotherRtuGateway = Gateway::factory()->create([
            'name' => 'Another RTU Gateway',
            'gateway_type' => 'teltonika_rut956',
            'communication_status' => 'online'
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('dashboard.rtu', $this->rtuGateway));

        $response->assertStatus(200);
        
        // Should show both gateways in selector
        $response->assertSee($this->rtuGateway->name);
        $response->assertSee($anotherRtuGateway->name);
        
        // Should show RTU badges for both
        $response->assertSee('RTU');
    }

    /** @test */
    public function it_preserves_navigation_state_on_refresh()
    {
        // Visit RTU dashboard
        $response = $this->actingAs($this->user)
            ->get(route('dashboard.rtu', $this->rtuGateway));

        $response->assertStatus(200);
        
        // Refresh should maintain the same view
        $response = $this->actingAs($this->user)
            ->get(route('dashboard.rtu', $this->rtuGateway));

        $response->assertStatus(200);
        $response->assertViewIs('dashboard.rtu');
        $response->assertSee('RTU Dashboard');
    }
}