<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Gateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class RTUAlertsAPITest extends TestCase
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
            'communication_status' => 'online'
        ]);
    }

    /** @test */
    public function it_requires_authentication_for_alert_filtering()
    {
        $response = $this->postJson("/api/rtu/gateway/{$this->gateway->id}/alerts/filter", [
            'filters' => []
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function it_rejects_non_rtu_gateways()
    {
        Sanctum::actingAs($this->user);

        $nonRtuGateway = Gateway::factory()->create([
            'gateway_type' => 'generic',
            'name' => 'Non-RTU Gateway'
        ]);

        $response = $this->postJson("/api/rtu/gateway/{$nonRtuGateway->id}/alerts/filter", [
            'filters' => []
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => 'Invalid gateway type'
            ]);
    }

    /** @test */
    public function it_validates_filter_parameters()
    {
        Sanctum::actingAs($this->user);

        // Test missing filters
        $response = $this->postJson("/api/rtu/gateway/{$this->gateway->id}/alerts/filter", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['filters']);

        // Test invalid severity
        $response = $this->postJson("/api/rtu/gateway/{$this->gateway->id}/alerts/filter", [
            'filters' => [
                'severity' => ['invalid_severity']
            ]
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['filters.severity.0']);
    }

    /** @test */
    public function it_returns_success_response_for_valid_request()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/rtu/gateway/{$this->gateway->id}/alerts/filter", [
            'filters' => [
                'severity' => ['critical', 'warning']
            ]
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'html',
                'counts' => [
                    'critical',
                    'warning',
                    'info',
                    'total'
                ],
                'filters_applied',
                'timestamp'
            ]);
    }

    /** @test */
    public function it_returns_html_content()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/rtu/gateway/{$this->gateway->id}/alerts/filter", [
            'filters' => []
        ]);

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertArrayHasKey('html', $data);
        $this->assertNotEmpty($data['html']);
        $this->assertTrue($data['success']);
    }
}