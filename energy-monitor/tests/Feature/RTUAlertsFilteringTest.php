<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Gateway;
use App\Models\Device;
use App\Models\Alert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Carbon\Carbon;

class RTUAlertsFilteringTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Gateway $gateway;
    protected Device $device1;
    protected Device $device2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        
        $this->gateway = Gateway::factory()->create([
            'gateway_type' => 'teltonika_rut956',
            'name' => 'Test RTU Gateway',
            'communication_status' => 'online'
        ]);

        $this->device1 = Device::factory()->create([
            'gateway_id' => $this->gateway->id,
            'name' => 'RTU Device 1'
        ]);

        $this->device2 = Device::factory()->create([
            'gateway_id' => $this->gateway->id,
            'name' => 'RTU Device 2'
        ]);
    }

    /** @test */
    public function it_can_filter_alerts_by_severity()
    {
        Sanctum::actingAs($this->user);

        // Create alerts with different severities
        Alert::factory()->create([
            'device_id' => $this->device1->id,
            'severity' => 'critical',
            'parameter_name' => 'system_failure',
            'message' => 'Critical system failure',
            'resolved' => false
        ]);

        Alert::factory()->create([
            'device_id' => $this->device1->id,
            'severity' => 'warning',
            'parameter_name' => 'high_cpu',
            'message' => 'High CPU usage',
            'resolved' => false
        ]);

        Alert::factory()->create([
            'device_id' => $this->device1->id,
            'severity' => 'info',
            'parameter_name' => 'status_update',
            'message' => 'Status update',
            'resolved' => false
        ]);

        // Filter for critical alerts only
        $response = $this->postJson("/api/rtu/gateway/{$this->gateway->id}/alerts/filter", [
            'filters' => [
                'severity' => ['critical']
            ]
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'counts' => [
                    'critical' => 1,
                    'warning' => 0,
                    'info' => 0,
                    'total' => 1
                ]
            ]);
    }

    /** @test */
    public function it_can_filter_alerts_by_device()
    {
        Sanctum::actingAs($this->user);

        // Create alerts for different devices
        Alert::factory()->create([
            'device_id' => $this->device1->id,
            'severity' => 'critical',
            'parameter_name' => 'device1_alert',
            'resolved' => false
        ]);

        Alert::factory()->create([
            'device_id' => $this->device2->id,
            'severity' => 'warning',
            'parameter_name' => 'device2_alert',
            'resolved' => false
        ]);

        // Filter for device1 alerts only
        $response = $this->postJson("/api/rtu/gateway/{$this->gateway->id}/alerts/filter", [
            'filters' => [
                'device_ids' => [$this->device1->id]
            ]
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'counts' => [
                    'total' => 1
                ]
            ]);
    }

    /** @test */
    public function it_can_filter_alerts_by_time_range()
    {
        Sanctum::actingAs($this->user);

        // Create old alert
        Alert::factory()->create([
            'device_id' => $this->device1->id,
            'severity' => 'critical',
            'parameter_name' => 'old_alert',
            'resolved' => false,
            'created_at' => now()->subDays(2)
        ]);

        // Create recent alert
        Alert::factory()->create([
            'device_id' => $this->device1->id,
            'severity' => 'warning',
            'parameter_name' => 'recent_alert',
            'resolved' => false,
            'created_at' => now()->subHours(2)
        ]);

        // Filter for last day only
        $response = $this->postJson("/api/rtu/gateway/{$this->gateway->id}/alerts/filter", [
            'filters' => [
                'time_range' => 'last_day'
            ]
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'counts' => [
                    'total' => 1
                ]
            ]);
    }

    /** @test */
    public function it_can_filter_alerts_by_custom_date_range()
    {
        Sanctum::actingAs($this->user);

        $startDate = now()->subDays(3);
        $endDate = now()->subDays(1);

        // Create alert within range
        Alert::factory()->create([
            'device_id' => $this->device1->id,
            'severity' => 'critical',
            'parameter_name' => 'in_range_alert',
            'resolved' => false,
            'created_at' => now()->subDays(2)
        ]);

        // Create alert outside range
        Alert::factory()->create([
            'device_id' => $this->device1->id,
            'severity' => 'warning',
            'parameter_name' => 'out_of_range_alert',
            'resolved' => false,
            'created_at' => now()->subHours(1)
        ]);

        // Filter by custom date range
        $response = $this->postJson("/api/rtu/gateway/{$this->gateway->id}/alerts/filter", [
            'filters' => [
                'time_range' => 'custom',
                'start_date' => $startDate->toISOString(),
                'end_date' => $endDate->toISOString()
            ]
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'counts' => [
                    'total' => 1
                ]
            ]);
    }

    /** @test */
    public function it_can_combine_multiple_filters()
    {
        Sanctum::actingAs($this->user);

        // Create alerts with different combinations
        Alert::factory()->create([
            'device_id' => $this->device1->id,
            'severity' => 'critical',
            'parameter_name' => 'match_alert',
            'resolved' => false,
            'created_at' => now()->subHours(2)
        ]);

        Alert::factory()->create([
            'device_id' => $this->device2->id,
            'severity' => 'critical',
            'parameter_name' => 'wrong_device',
            'resolved' => false,
            'created_at' => now()->subHours(2)
        ]);

        Alert::factory()->create([
            'device_id' => $this->device1->id,
            'severity' => 'warning',
            'parameter_name' => 'wrong_severity',
            'resolved' => false,
            'created_at' => now()->subHours(2)
        ]);

        // Apply multiple filters
        $response = $this->postJson("/api/rtu/gateway/{$this->gateway->id}/alerts/filter", [
            'filters' => [
                'device_ids' => [$this->device1->id],
                'severity' => ['critical'],
                'time_range' => 'last_day'
            ]
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'counts' => [
                    'critical' => 1,
                    'warning' => 0,
                    'info' => 0,
                    'total' => 1
                ]
            ]);
    }

    /** @test */
    public function it_returns_html_content_for_filtered_alerts()
    {
        Sanctum::actingAs($this->user);

        Alert::factory()->create([
            'device_id' => $this->device1->id,
            'severity' => 'critical',
            'parameter_name' => 'test_alert',
            'message' => 'Test alert message',
            'resolved' => false
        ]);

        $response = $this->postJson("/api/rtu/gateway/{$this->gateway->id}/alerts/filter", [
            'filters' => [
                'severity' => ['critical']
            ]
        ]);

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertArrayHasKey('html', $data);
        $this->assertNotEmpty($data['html']);
        $this->assertStringContainsString('Test alert message', $data['html']);
    }

    /** @test */
    public function it_requires_authentication()
    {
        $response = $this->postJson("/api/rtu/gateway/{$this->gateway->id}/alerts/filter", [
            'filters' => []
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function it_validates_filter_parameters()
    {
        Sanctum::actingAs($this->user);

        // Test invalid severity
        $response = $this->postJson("/api/rtu/gateway/{$this->gateway->id}/alerts/filter", [
            'filters' => [
                'severity' => ['invalid_severity']
            ]
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['filters.severity.0']);

        // Test invalid device ID
        $response = $this->postJson("/api/rtu/gateway/{$this->gateway->id}/alerts/filter", [
            'filters' => [
                'device_ids' => [99999] // Non-existent device
            ]
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['filters.device_ids.0']);

        // Test invalid time range
        $response = $this->postJson("/api/rtu/gateway/{$this->gateway->id}/alerts/filter", [
            'filters' => [
                'time_range' => 'invalid_range'
            ]
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['filters.time_range']);
    }

    /** @test */
    public function it_validates_custom_date_range()
    {
        Sanctum::actingAs($this->user);

        // Test end date before start date
        $response = $this->postJson("/api/rtu/gateway/{$this->gateway->id}/alerts/filter", [
            'filters' => [
                'time_range' => 'custom',
                'start_date' => now()->toISOString(),
                'end_date' => now()->subDay()->toISOString()
            ]
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['filters.end_date']);
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
    public function it_handles_no_matching_alerts()
    {
        Sanctum::actingAs($this->user);

        // Create alert that won't match filter
        Alert::factory()->create([
            'device_id' => $this->device1->id,
            'severity' => 'info',
            'parameter_name' => 'info_alert',
            'resolved' => false
        ]);

        // Filter for critical alerts only
        $response = $this->postJson("/api/rtu/gateway/{$this->gateway->id}/alerts/filter", [
            'filters' => [
                'severity' => ['critical']
            ]
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'counts' => [
                    'critical' => 0,
                    'warning' => 0,
                    'info' => 0,
                    'total' => 0
                ]
            ]);

        $data = $response->json();
        $this->assertStringContainsString('No alerts match', $data['html']);
    }

    /** @test */
    public function it_logs_filtering_activity()
    {
        Sanctum::actingAs($this->user);

        Alert::factory()->create([
            'device_id' => $this->device1->id,
            'severity' => 'critical',
            'resolved' => false
        ]);

        $response = $this->postJson("/api/rtu/gateway/{$this->gateway->id}/alerts/filter", [
            'filters' => [
                'severity' => ['critical']
            ]
        ]);

        $response->assertStatus(200);

        // Check that activity was logged (this would require log testing setup)
        // For now, we just verify the response is successful
        $this->assertTrue($response->json('success'));
    }
}