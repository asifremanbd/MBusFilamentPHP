<?php

namespace Tests\Feature;

use App\Models\Gateway;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RTUDashboardBasicTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function rtu_dashboard_route_exists()
    {
        $user = User::factory()->create();
        $gateway = Gateway::factory()->create([
            'gateway_type' => 'teltonika_rut956'
        ]);

        $response = $this->actingAs($user)
            ->get(route('dashboard.rtu', $gateway));

        // Should not be 404 (route exists)
        $this->assertNotEquals(404, $response->status());
    }

    /** @test */
    public function rtu_dashboard_requires_authentication()
    {
        $gateway = Gateway::factory()->create([
            'gateway_type' => 'teltonika_rut956'
        ]);

        $response = $this->get(route('dashboard.rtu', $gateway));

        $response->assertRedirect();
    }

    /** @test */
    public function rtu_dashboard_rejects_non_rtu_gateways()
    {
        $user = User::factory()->create();
        $gateway = Gateway::factory()->create([
            'gateway_type' => 'generic'
        ]);

        $response = $this->actingAs($user)
            ->get(route('dashboard.rtu', $gateway));

        $response->assertStatus(400);
    }
}