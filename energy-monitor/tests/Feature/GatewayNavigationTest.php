<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Gateway;
use App\Models\Device;
use App\Models\UserGatewayAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class GatewayNavigationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected Gateway $gateway1;
    protected Gateway $gateway2;
    protected Gateway $unauthorizedGateway;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user
        $this->user = User::factory()->create([
            'role' => 'operator'
        ]);

        // Create test gateways
        $this->gateway1 = Gateway::factory()->create([
            'name' => 'Test Gateway 1',
            'ip_address' => '192.168.1.100',
            'port' => 502,
            'communication_status' => 'online'
        ]);

        $this->gateway2 = Gateway::factory()->create([
            'name' => 'Test Gateway 2',
            'ip_address' => '192.168.1.101',
            'port' => 502,
            'communication_status' => 'warning'
        ]);

        $this->unauthorizedGateway = Gateway::factory()->create([
            'name' => 'Unauthorized Gateway',
            'ip_address' => '192.168.1.102',
            'port' => 502,
            'communication_status' => 'offline'
        ]);

        // Assign gateways to user
        UserGatewayAssignment::create([
            'user_id' => $this->user->id,
            'gateway_id' => $this->gateway1->id
        ]);

        UserGatewayAssignment::create([
            'user_id' => $this->user->id,
            'gateway_id' => $this->gateway2->id
        ]);

        // Create devices for gateways
        Device::factory()->count(3)->create([
            'gateway_id' => $this->gateway1->id
        ]);

        Device::factory()->count(2)->create([
            'gateway_id' => $this->gateway2->id
        ]);
    }

    /** @test */
    public function user_can_access_global_dashboard()
    {
        $response = $this->actingAs($this->user)
            ->get(route('dashboard.global'));

        $response->assertStatus(200);
        $response->assertViewIs('dashboard.global');
        $response->assertSee('Global Dashboard');
    }

    /** @test */
    public function user_can_access_authorized_gateway_dashboard()
    {
        $response = $this->actingAs($this->user)
            ->get(route('dashboard.gateway', ['gateway' => $this->gateway1->id]));

        $response->assertStatus(200);
        $response->assertViewIs('dashboard.gateway');
        $response->assertSee($this->gateway1->name);
    }

    /** @test */
    public function user_cannot_access_unauthorized_gateway_dashboard()
    {
        $response = $this->actingAs($this->user)
            ->get(route('dashboard.gateway', ['gateway' => $this->unauthorizedGateway->id]));

        $response->assertStatus(403);
    }

    /** @test */
    public function api_returns_only_authorized_gateways()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/dashboard/gateways');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'gateways' => [
                '*' => [
                    'id',
                    'name',
                    'status',
                    'location',
                    'device_count',
                    'last_communication',
                    'ip_address',
                    'port'
                ]
            ]
        ]);

        $gateways = $response->json('gateways');
        $gatewayIds = collect($gateways)->pluck('id')->toArray();

        $this->assertContains($this->gateway1->id, $gatewayIds);
        $this->assertContains($this->gateway2->id, $gatewayIds);
        $this->assertNotContains($this->unauthorizedGateway->id, $gatewayIds);
    }

    /** @test */
    public function gateway_selector_shows_correct_device_counts()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/dashboard/gateways');

        $response->assertStatus(200);
        
        $gateways = collect($response->json('gateways'));
        
        $gateway1Data = $gateways->firstWhere('id', $this->gateway1->id);
        $gateway2Data = $gateways->firstWhere('id', $this->gateway2->id);

        $this->assertEquals(3, $gateway1Data['device_count']);
        $this->assertEquals(2, $gateway2Data['device_count']);
    }

    /** @test */
    public function gateway_selector_includes_status_information()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/dashboard/gateways');

        $response->assertStatus(200);
        
        $gateways = collect($response->json('gateways'));
        
        $gateway1Data = $gateways->firstWhere('id', $this->gateway1->id);
        $gateway2Data = $gateways->firstWhere('id', $this->gateway2->id);

        $this->assertEquals('online', $gateway1Data['status']);
        $this->assertEquals('warning', $gateway2Data['status']);
    }

    /** @test */
    public function dashboard_redirect_works_correctly()
    {
        $response = $this->actingAs($this->user)
            ->get('/dashboard');

        $response->assertRedirect(route('dashboard.global'));
    }

    /** @test */
    public function gateway_dashboard_shows_gateway_information()
    {
        $response = $this->actingAs($this->user)
            ->get(route('dashboard.gateway', ['gateway' => $this->gateway1->id]));

        $response->assertStatus(200);
        $response->assertSee($this->gateway1->name);
        $response->assertSee($this->gateway1->ip_address);
        $response->assertSee((string) $this->gateway1->port);
        $response->assertSee('3'); // Device count
    }

    /** @test */
    public function breadcrumb_navigation_works_correctly()
    {
        $response = $this->actingAs($this->user)
            ->get(route('dashboard.gateway', ['gateway' => $this->gateway1->id]));

        $response->assertStatus(200);
        $response->assertSee('dashboard-breadcrumbs');
    }

    /** @test */
    public function gateway_selector_component_is_included()
    {
        $response = $this->actingAs($this->user)
            ->get(route('dashboard.global'));

        $response->assertStatus(200);
        $response->assertSee('gateway-selector');
    }

    /** @test */
    public function gateway_sidebar_is_included_in_gateway_dashboard()
    {
        $response = $this->actingAs($this->user)
            ->get(route('dashboard.gateway', ['gateway' => $this->gateway1->id]));

        $response->assertStatus(200);
        $response->assertSee('gateway-sidebar');
    }

    /** @test */
    public function unauthenticated_user_cannot_access_dashboard()
    {
        $response = $this->get(route('dashboard.global'));
        $response->assertRedirect('/login');

        $response = $this->get(route('dashboard.gateway', ['gateway' => $this->gateway1->id]));
        $response->assertRedirect('/login');
    }

    /** @test */
    public function api_requires_authentication()
    {
        $response = $this->getJson('/api/dashboard/gateways');
        $response->assertStatus(401);
    }

    /** @test */
    public function gateway_navigation_preserves_url_parameters()
    {
        // Test that gateway ID is properly included in URL
        $response = $this->actingAs($this->user)
            ->get(route('dashboard.gateway', ['gateway' => $this->gateway1->id]));

        $response->assertStatus(200);
        
        // Check that the gateway ID is available in the view
        $response->assertViewHas('gateway');
        $gateway = $response->viewData('gateway');
        $this->assertEquals($this->gateway1->id, $gateway->id);
    }

    /** @test */
    public function keyboard_shortcuts_data_is_available()
    {
        $response = $this->actingAs($this->user)
            ->get(route('dashboard.global'));

        $response->assertStatus(200);
        // Check that the gateway selector JavaScript is included
        $response->assertSee('gateway-selector.js');
    }

    /** @test */
    public function gateway_selector_handles_empty_gateway_list()
    {
        // Remove all gateway assignments
        UserGatewayAssignment::where('user_id', $this->user->id)->delete();

        $response = $this->actingAs($this->user)
            ->getJson('/api/dashboard/gateways');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'gateways' => []
        ]);
    }

    /** @test */
    public function gateway_dashboard_handles_nonexistent_gateway()
    {
        $response = $this->actingAs($this->user)
            ->get(route('dashboard.gateway', ['gateway' => 99999]));

        $response->assertStatus(404);
    }

    /** @test */
    public function gateway_selector_api_handles_errors_gracefully()
    {
        // Temporarily break the database connection to test error handling
        $originalConnection = config('database.default');
        config(['database.default' => 'invalid_connection']);

        $response = $this->actingAs($this->user)
            ->getJson('/api/dashboard/gateways');

        // Restore connection
        config(['database.default' => $originalConnection]);

        $response->assertStatus(500);
        $response->assertJsonStructure([
            'error',
            'message'
        ]);
    }
}
"