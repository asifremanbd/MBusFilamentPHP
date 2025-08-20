<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Gateway;
use App\Services\RTUDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Mockery;

class RTUIOControlTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected Gateway $gateway;
    protected $mockRTUDataService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test user
        $this->user = User::factory()->create();
        
        // Create RTU gateway
        $this->gateway = Gateway::factory()->create([
            'gateway_type' => 'teltonika_rut956',
            'do1_status' => false,
            'do2_status' => true,
            'last_system_update' => now()
        ]);

        // Mock RTUDataService
        $this->mockRTUDataService = Mockery::mock(RTUDataService::class);
        $this->app->instance(RTUDataService::class, $this->mockRTUDataService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_successfully_toggles_digital_output_on()
    {
        Sanctum::actingAs($this->user);

        $this->mockRTUDataService
            ->shouldReceive('setDigitalOutput')
            ->once()
            ->with($this->gateway, 'do1', true)
            ->andReturn([
                'success' => true,
                'message' => 'Digital output do1 set to ON',
                'new_state' => true
            ]);

        $response = $this->postJson("/api/rtu/gateways/{$this->gateway->id}/digital-output/do1", [
            'state' => true
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Digital output do1 set to ON',
                'new_state' => true,
                'output' => 'do1'
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'new_state',
                'output',
                'timestamp'
            ]);
    }

    /** @test */
    public function it_successfully_toggles_digital_output_off()
    {
        Sanctum::actingAs($this->user);

        $this->mockRTUDataService
            ->shouldReceive('setDigitalOutput')
            ->once()
            ->with($this->gateway, 'do2', false)
            ->andReturn([
                'success' => true,
                'message' => 'Digital output do2 set to OFF',
                'new_state' => false
            ]);

        $response = $this->postJson("/api/rtu/gateways/{$this->gateway->id}/digital-output/do2", [
            'state' => false
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Digital output do2 set to OFF',
                'new_state' => false,
                'output' => 'do2'
            ]);
    }

    /** @test */
    public function it_handles_rtu_service_failure()
    {
        Sanctum::actingAs($this->user);

        $this->mockRTUDataService
            ->shouldReceive('setDigitalOutput')
            ->once()
            ->with($this->gateway, 'do1', true)
            ->andReturn([
                'success' => false,
                'message' => 'Failed to control digital output: Connection timeout',
                'error_type' => 'connection_timeout'
            ]);

        $response = $this->postJson("/api/rtu/gateways/{$this->gateway->id}/digital-output/do1", [
            'state' => true
        ]);

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
                'error' => 'Control operation failed',
                'message' => 'Failed to control digital output: Connection timeout',
                'error_type' => 'connection_timeout'
            ]);
    }

    /** @test */
    public function it_rejects_invalid_output_parameter()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/rtu/gateways/{$this->gateway->id}/digital-output/invalid_output", [
            'state' => true
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => 'Invalid output',
                'message' => 'Output must be either do1 or do2.'
            ]);
    }

    /** @test */
    public function it_validates_required_state_parameter()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/rtu/gateways/{$this->gateway->id}/digital-output/do1", [
            // Missing 'state' parameter
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => 'Validation failed',
                'message' => 'Invalid request data.'
            ])
            ->assertJsonValidationErrors(['state']);
    }

    /** @test */
    public function it_validates_state_parameter_type()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/rtu/gateways/{$this->gateway->id}/digital-output/do1", [
            'state' => 'invalid_boolean'
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => 'Validation failed',
                'message' => 'Invalid request data.'
            ])
            ->assertJsonValidationErrors(['state']);
    }

    /** @test */
    public function it_rejects_non_rtu_gateway()
    {
        Sanctum::actingAs($this->user);

        $nonRtuGateway = Gateway::factory()->create([
            'gateway_type' => 'generic'
        ]);

        $response = $this->postJson("/api/rtu/gateways/{$nonRtuGateway->id}/digital-output/do1", [
            'state' => true
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => 'Invalid gateway type',
                'message' => 'This gateway is not configured as an RTU device.'
            ]);
    }

    /** @test */
    public function it_requires_authentication()
    {
        $response = $this->postJson("/api/rtu/gateways/{$this->gateway->id}/digital-output/do1", [
            'state' => true
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function it_handles_gateway_not_found()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/rtu/gateways/999999/digital-output/do1", [
            'state' => true
        ]);

        $response->assertStatus(404);
    }

    /** @test */
    public function it_accepts_both_do1_and_do2_outputs()
    {
        Sanctum::actingAs($this->user);

        // Test DO1
        $this->mockRTUDataService
            ->shouldReceive('setDigitalOutput')
            ->once()
            ->with($this->gateway, 'do1', true)
            ->andReturn([
                'success' => true,
                'message' => 'Digital output do1 set to ON',
                'new_state' => true
            ]);

        $response1 = $this->postJson("/api/rtu/gateways/{$this->gateway->id}/digital-output/do1", [
            'state' => true
        ]);

        $response1->assertStatus(200);

        // Test DO2
        $this->mockRTUDataService
            ->shouldReceive('setDigitalOutput')
            ->once()
            ->with($this->gateway, 'do2', false)
            ->andReturn([
                'success' => true,
                'message' => 'Digital output do2 set to OFF',
                'new_state' => false
            ]);

        $response2 = $this->postJson("/api/rtu/gateways/{$this->gateway->id}/digital-output/do2", [
            'state' => false
        ]);

        $response2->assertStatus(200);
    }

    /** @test */
    public function it_handles_service_exceptions()
    {
        Sanctum::actingAs($this->user);

        $this->mockRTUDataService
            ->shouldReceive('setDigitalOutput')
            ->once()
            ->with($this->gateway, 'do1', true)
            ->andThrow(new \Exception('Unexpected service error'));

        $response = $this->postJson("/api/rtu/gateways/{$this->gateway->id}/digital-output/do1", [
            'state' => true
        ]);

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
                'error' => 'Internal server error',
                'message' => 'An error occurred while controlling the digital output. Please try again or contact support.'
            ]);
    }

    /** @test */
    public function it_logs_control_attempts()
    {
        Sanctum::actingAs($this->user);

        $this->mockRTUDataService
            ->shouldReceive('setDigitalOutput')
            ->once()
            ->with($this->gateway, 'do1', true)
            ->andReturn([
                'success' => true,
                'message' => 'Digital output do1 set to ON',
                'new_state' => true
            ]);

        // Capture log messages
        $this->expectsEvents(\Illuminate\Log\Events\MessageLogged::class);

        $response = $this->postJson("/api/rtu/gateways/{$this->gateway->id}/digital-output/do1", [
            'state' => true
        ]);

        $response->assertStatus(200);
    }

    /** @test */
    public function it_accepts_boolean_values_as_integers()
    {
        Sanctum::actingAs($this->user);

        $this->mockRTUDataService
            ->shouldReceive('setDigitalOutput')
            ->once()
            ->with($this->gateway, 'do1', true)
            ->andReturn([
                'success' => true,
                'message' => 'Digital output do1 set to ON',
                'new_state' => true
            ]);

        // Test with integer 1 (should be converted to true)
        $response = $this->postJson("/api/rtu/gateways/{$this->gateway->id}/digital-output/do1", [
            'state' => 1
        ]);

        $response->assertStatus(200);

        $this->mockRTUDataService
            ->shouldReceive('setDigitalOutput')
            ->once()
            ->with($this->gateway, 'do1', false)
            ->andReturn([
                'success' => true,
                'message' => 'Digital output do1 set to OFF',
                'new_state' => false
            ]);

        // Test with integer 0 (should be converted to false)
        $response = $this->postJson("/api/rtu/gateways/{$this->gateway->id}/digital-output/do1", [
            'state' => 0
        ]);

        $response->assertStatus(200);
    }

    /** @test */
    public function it_returns_proper_json_structure_on_success()
    {
        Sanctum::actingAs($this->user);

        $this->mockRTUDataService
            ->shouldReceive('setDigitalOutput')
            ->once()
            ->with($this->gateway, 'do1', true)
            ->andReturn([
                'success' => true,
                'message' => 'Digital output do1 set to ON',
                'new_state' => true
            ]);

        $response = $this->postJson("/api/rtu/gateways/{$this->gateway->id}/digital-output/do1", [
            'state' => true
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'new_state',
                'output',
                'timestamp'
            ])
            ->assertJson([
                'success' => true,
                'output' => 'do1',
                'new_state' => true
            ]);

        // Verify timestamp is a valid ISO string
        $data = $response->json();
        $this->assertNotNull($data['timestamp']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $data['timestamp']);
    }
}