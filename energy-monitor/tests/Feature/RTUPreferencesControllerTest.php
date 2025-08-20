<?php

namespace Tests\Feature;

use App\Models\Gateway;
use App\Models\RTUTrendPreference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RTUPreferencesControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Gateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->gateway = Gateway::factory()->create([
            'gateway_type' => 'teltonika_rut956'
        ]);
        
        Sanctum::actingAs($this->user);
    }

    public function test_get_trend_preferences_returns_existing_preferences()
    {
        $preference = RTUTrendPreference::create([
            'user_id' => $this->user->id,
            'gateway_id' => $this->gateway->id,
            'selected_metrics' => ['signal_strength', 'cpu_load'],
            'time_range' => '6h',
            'chart_type' => 'area',
        ]);

        $response = $this->getJson("/api/rtu/gateway/{$this->gateway->id}/preferences");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'preferences' => [
                    'id',
                    'user_id',
                    'gateway_id',
                    'selected_metrics',
                    'time_range',
                    'chart_type',
                    'created_at',
                    'updated_at'
                ],
                'config' => [
                    'dashboard_type',
                    'user_id',
                    'available_metrics',
                    'available_time_ranges',
                    'available_chart_types',
                    'default_metrics'
                ]
            ])
            ->assertJson([
                'preferences' => [
                    'selected_metrics' => ['signal_strength', 'cpu_load'],
                    'time_range' => '6h',
                    'chart_type' => 'area',
                ]
            ]);
    }

    public function test_get_trend_preferences_creates_default_when_none_exist()
    {
        $response = $this->getJson("/api/rtu/gateway/{$this->gateway->id}/preferences");

        $response->assertStatus(200)
            ->assertJson([
                'preferences' => [
                    'selected_metrics' => ['signal_strength'],
                    'time_range' => '24h',
                    'chart_type' => 'line',
                ]
            ]);

        $this->assertDatabaseHas('rtu_trend_preferences', [
            'user_id' => $this->user->id,
            'gateway_id' => $this->gateway->id,
        ]);
    }

    public function test_update_trend_preferences_with_valid_data()
    {
        $data = [
            'selected_metrics' => ['signal_strength', 'cpu_load'],
            'time_range' => '6h',
            'chart_type' => 'area',
        ];

        $response = $this->postJson("/api/rtu/gateway/{$this->gateway->id}/preferences", $data);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Preferences updated successfully',
                'preferences' => [
                    'selected_metrics' => ['signal_strength', 'cpu_load'],
                    'time_range' => '6h',
                    'chart_type' => 'area',
                ]
            ]);

        $this->assertDatabaseHas('rtu_trend_preferences', [
            'user_id' => $this->user->id,
            'gateway_id' => $this->gateway->id,
            'time_range' => '6h',
            'chart_type' => 'area',
        ]);
    }

    public function test_update_trend_preferences_with_invalid_metrics()
    {
        $data = [
            'selected_metrics' => ['invalid_metric'],
            'time_range' => '24h',
            'chart_type' => 'line',
        ];

        $response = $this->postJson("/api/rtu/gateway/{$this->gateway->id}/preferences", $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['selected_metrics.0']);
    }

    public function test_update_trend_preferences_with_empty_metrics()
    {
        $data = [
            'selected_metrics' => [],
            'time_range' => '24h',
            'chart_type' => 'line',
        ];

        $response = $this->postJson("/api/rtu/gateway/{$this->gateway->id}/preferences", $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['selected_metrics']);
    }

    public function test_update_trend_preferences_with_invalid_time_range()
    {
        $data = [
            'selected_metrics' => ['signal_strength'],
            'time_range' => 'invalid_range',
            'chart_type' => 'line',
        ];

        $response = $this->postJson("/api/rtu/gateway/{$this->gateway->id}/preferences", $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['time_range']);
    }

    public function test_update_trend_preferences_with_invalid_chart_type()
    {
        $data = [
            'selected_metrics' => ['signal_strength'],
            'time_range' => '24h',
            'chart_type' => 'invalid_type',
        ];

        $response = $this->postJson("/api/rtu/gateway/{$this->gateway->id}/preferences", $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['chart_type']);
    }

    public function test_reset_to_defaults()
    {
        // Create custom preferences
        RTUTrendPreference::create([
            'user_id' => $this->user->id,
            'gateway_id' => $this->gateway->id,
            'selected_metrics' => ['cpu_load', 'memory_usage'],
            'time_range' => '6h',
            'chart_type' => 'area',
        ]);

        $response = $this->postJson("/api/rtu/gateway/{$this->gateway->id}/preferences/reset");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Preferences reset to defaults',
                'preferences' => [
                    'selected_metrics' => ['signal_strength'],
                    'time_range' => '24h',
                    'chart_type' => 'line',
                ]
            ]);

        $this->assertDatabaseHas('rtu_trend_preferences', [
            'user_id' => $this->user->id,
            'gateway_id' => $this->gateway->id,
            'time_range' => '24h',
            'chart_type' => 'line',
        ]);
    }

    public function test_get_config_options()
    {
        $response = $this->getJson('/api/rtu/preferences/config');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'dashboard_type',
                'user_id',
                'available_metrics',
                'available_time_ranges',
                'available_chart_types',
                'default_metrics'
            ])
            ->assertJson([
                'dashboard_type' => 'rtu',
                'user_id' => $this->user->id,
                'default_metrics' => ['signal_strength']
            ]);
    }

    public function test_get_bulk_preferences()
    {
        $gateway2 = Gateway::factory()->create(['gateway_type' => 'teltonika_rut956']);
        
        // Create preference for first gateway
        RTUTrendPreference::create([
            'user_id' => $this->user->id,
            'gateway_id' => $this->gateway->id,
            'selected_metrics' => ['signal_strength'],
            'time_range' => '24h',
            'chart_type' => 'line',
        ]);

        $data = [
            'gateway_ids' => [$this->gateway->id, $gateway2->id]
        ];

        $response = $this->postJson('/api/rtu/preferences/bulk', $data);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'preferences' => [
                    $this->gateway->id => [
                        'id',
                        'user_id',
                        'gateway_id',
                        'selected_metrics',
                        'time_range',
                        'chart_type'
                    ],
                    $gateway2->id => [
                        'id',
                        'user_id',
                        'gateway_id',
                        'selected_metrics',
                        'time_range',
                        'chart_type'
                    ]
                ]
            ]);
    }

    public function test_export_preferences()
    {
        RTUTrendPreference::create([
            'user_id' => $this->user->id,
            'gateway_id' => $this->gateway->id,
            'selected_metrics' => ['signal_strength', 'cpu_load'],
            'time_range' => '6h',
            'chart_type' => 'area',
        ]);

        $response = $this->getJson('/api/rtu/preferences/export');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'preferences' => [
                    '*' => [
                        'gateway_id',
                        'gateway_name',
                        'selected_metrics',
                        'time_range',
                        'chart_type',
                        'created_at',
                        'updated_at'
                    ]
                ],
                'exported_at',
                'user_id'
            ])
            ->assertJson([
                'user_id' => $this->user->id,
                'preferences' => [
                    [
                        'gateway_id' => $this->gateway->id,
                        'selected_metrics' => ['signal_strength', 'cpu_load'],
                        'time_range' => '6h',
                        'chart_type' => 'area',
                    ]
                ]
            ]);
    }

    public function test_import_preferences()
    {
        $gateway2 = Gateway::factory()->create(['gateway_type' => 'teltonika_rut956']);

        $data = [
            'preferences' => [
                [
                    'gateway_id' => $this->gateway->id,
                    'selected_metrics' => ['signal_strength', 'cpu_load'],
                    'time_range' => '6h',
                    'chart_type' => 'area',
                ],
                [
                    'gateway_id' => $gateway2->id,
                    'selected_metrics' => ['memory_usage'],
                    'time_range' => '1h',
                    'chart_type' => 'bar',
                ]
            ]
        ];

        $response = $this->postJson('/api/rtu/preferences/import', $data);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Preferences imported successfully',
                'imported_count' => 2,
                'errors' => []
            ]);

        $this->assertDatabaseHas('rtu_trend_preferences', [
            'user_id' => $this->user->id,
            'gateway_id' => $this->gateway->id,
            'time_range' => '6h',
            'chart_type' => 'area',
        ]);

        $this->assertDatabaseHas('rtu_trend_preferences', [
            'user_id' => $this->user->id,
            'gateway_id' => $gateway2->id,
            'time_range' => '1h',
            'chart_type' => 'bar',
        ]);
    }

    public function test_delete_preferences()
    {
        RTUTrendPreference::create([
            'user_id' => $this->user->id,
            'gateway_id' => $this->gateway->id,
            'selected_metrics' => ['signal_strength'],
            'time_range' => '24h',
            'chart_type' => 'line',
        ]);

        $response = $this->deleteJson("/api/rtu/gateway/{$this->gateway->id}/preferences");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Preferences deleted successfully'
            ]);

        $this->assertDatabaseMissing('rtu_trend_preferences', [
            'user_id' => $this->user->id,
            'gateway_id' => $this->gateway->id,
        ]);
    }

    public function test_delete_preferences_when_none_exist()
    {
        $response = $this->deleteJson("/api/rtu/gateway/{$this->gateway->id}/preferences");

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'No preferences found to delete'
            ]);
    }

    public function test_unauthorized_access_returns_401()
    {
        Sanctum::actingAs(null);

        $response = $this->getJson("/api/rtu/gateway/{$this->gateway->id}/preferences");

        $response->assertStatus(401);
    }

    public function test_access_to_unauthorized_gateway_returns_403()
    {
        $otherUser = User::factory()->create();
        $otherGateway = Gateway::factory()->create([
            'gateway_type' => 'teltonika_rut956'
        ]);

        // Assuming there's some authorization logic that prevents access to other user's gateways
        // This test might need to be adjusted based on your actual authorization implementation

        $response = $this->getJson("/api/rtu/gateway/{$otherGateway->id}/preferences");

        // The exact status code depends on your authorization implementation
        // It could be 403 (Forbidden) or 404 (Not Found) depending on your policy
        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_bulk_preferences_with_invalid_gateway_ids()
    {
        $data = [
            'gateway_ids' => [99999] // Non-existent gateway
        ];

        $response = $this->postJson('/api/rtu/preferences/bulk', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['gateway_ids.0']);
    }

    public function test_import_preferences_with_invalid_gateway_id()
    {
        $data = [
            'preferences' => [
                [
                    'gateway_id' => 99999, // Non-existent gateway
                    'selected_metrics' => ['signal_strength'],
                    'time_range' => '24h',
                    'chart_type' => 'line',
                ]
            ]
        ];

        $response = $this->postJson('/api/rtu/preferences/import', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['preferences.0.gateway_id']);
    }

    public function test_import_preferences_with_missing_required_fields()
    {
        $data = [
            'preferences' => [
                [
                    'gateway_id' => $this->gateway->id,
                    // Missing required fields
                ]
            ]
        ];

        $response = $this->postJson('/api/rtu/preferences/import', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'preferences.0.selected_metrics',
                'preferences.0.time_range',
                'preferences.0.chart_type'
            ]);
    }
}