<?php

namespace Tests\Unit;

use App\Models\Gateway;
use App\Models\RTUTrendPreference;
use App\Models\User;
use App\Services\RTUDashboardConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RTUDashboardConfigServiceTest extends TestCase
{
    use RefreshDatabase;

    protected RTUDashboardConfigService $configService;
    protected User $user;
    protected Gateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->configService = new RTUDashboardConfigService();
        $this->user = User::factory()->create();
        $this->gateway = Gateway::factory()->create([
            'gateway_type' => 'teltonika_rut956'
        ]);
    }

    public function test_get_trend_preferences_creates_default_when_none_exist()
    {
        $preferences = $this->configService->getTrendPreferences($this->user, $this->gateway);

        $this->assertInstanceOf(RTUTrendPreference::class, $preferences);
        $this->assertEquals($this->user->id, $preferences->user_id);
        $this->assertEquals($this->gateway->id, $preferences->gateway_id);
        $this->assertEquals(['signal_strength'], $preferences->selected_metrics);
        $this->assertEquals('24h', $preferences->time_range);
        $this->assertEquals('line', $preferences->chart_type);
    }

    public function test_get_trend_preferences_returns_existing_preferences()
    {
        $existingPreferences = RTUTrendPreference::create([
            'user_id' => $this->user->id,
            'gateway_id' => $this->gateway->id,
            'selected_metrics' => ['cpu_load', 'memory_usage'],
            'time_range' => '6h',
            'chart_type' => 'area',
        ]);

        $preferences = $this->configService->getTrendPreferences($this->user, $this->gateway);

        $this->assertEquals($existingPreferences->id, $preferences->id);
        $this->assertEquals(['cpu_load', 'memory_usage'], $preferences->selected_metrics);
        $this->assertEquals('6h', $preferences->time_range);
        $this->assertEquals('area', $preferences->chart_type);
    }

    public function test_update_trend_preferences_creates_new_preferences()
    {
        $data = [
            'selected_metrics' => ['signal_strength', 'cpu_load'],
            'time_range' => '1h',
            'chart_type' => 'bar',
        ];

        $preferences = $this->configService->updateTrendPreferences($this->user, $this->gateway, $data);

        $this->assertInstanceOf(RTUTrendPreference::class, $preferences);
        $this->assertEquals(['signal_strength', 'cpu_load'], $preferences->selected_metrics);
        $this->assertEquals('1h', $preferences->time_range);
        $this->assertEquals('bar', $preferences->chart_type);
    }

    public function test_update_trend_preferences_updates_existing_preferences()
    {
        $existingPreferences = RTUTrendPreference::create([
            'user_id' => $this->user->id,
            'gateway_id' => $this->gateway->id,
            'selected_metrics' => ['signal_strength'],
            'time_range' => '24h',
            'chart_type' => 'line',
        ]);

        $data = [
            'selected_metrics' => ['memory_usage', 'analog_input'],
            'time_range' => '7d',
            'chart_type' => 'area',
        ];

        $preferences = $this->configService->updateTrendPreferences($this->user, $this->gateway, $data);

        $this->assertEquals($existingPreferences->id, $preferences->id);
        $this->assertEquals(['memory_usage', 'analog_input'], $preferences->selected_metrics);
        $this->assertEquals('7d', $preferences->time_range);
        $this->assertEquals('area', $preferences->chart_type);
    }

    public function test_update_trend_preferences_validates_selected_metrics()
    {
        $data = [
            'selected_metrics' => ['invalid_metric'],
            'time_range' => '24h',
            'chart_type' => 'line',
        ];

        $this->expectException(ValidationException::class);
        $this->configService->updateTrendPreferences($this->user, $this->gateway, $data);
    }

    public function test_update_trend_preferences_validates_empty_metrics()
    {
        $data = [
            'selected_metrics' => [],
            'time_range' => '24h',
            'chart_type' => 'line',
        ];

        $this->expectException(ValidationException::class);
        $this->configService->updateTrendPreferences($this->user, $this->gateway, $data);
    }

    public function test_update_trend_preferences_validates_time_range()
    {
        $data = [
            'selected_metrics' => ['signal_strength'],
            'time_range' => 'invalid_range',
            'chart_type' => 'line',
        ];

        $this->expectException(ValidationException::class);
        $this->configService->updateTrendPreferences($this->user, $this->gateway, $data);
    }

    public function test_update_trend_preferences_validates_chart_type()
    {
        $data = [
            'selected_metrics' => ['signal_strength'],
            'time_range' => '24h',
            'chart_type' => 'invalid_type',
        ];

        $this->expectException(ValidationException::class);
        $this->configService->updateTrendPreferences($this->user, $this->gateway, $data);
    }

    public function test_create_default_preferences()
    {
        $preferences = $this->configService->createDefaultPreferences($this->user, $this->gateway);

        $this->assertInstanceOf(RTUTrendPreference::class, $preferences);
        $this->assertEquals($this->user->id, $preferences->user_id);
        $this->assertEquals($this->gateway->id, $preferences->gateway_id);
        $this->assertEquals(['signal_strength'], $preferences->selected_metrics);
        $this->assertEquals('24h', $preferences->time_range);
        $this->assertEquals('line', $preferences->chart_type);
        $this->assertTrue($preferences->exists);
    }

    public function test_get_dashboard_config()
    {
        $config = $this->configService->getDashboardConfig($this->user);

        $this->assertIsArray($config);
        $this->assertEquals('rtu', $config['dashboard_type']);
        $this->assertEquals($this->user->id, $config['user_id']);
        $this->assertArrayHasKey('available_metrics', $config);
        $this->assertArrayHasKey('available_time_ranges', $config);
        $this->assertArrayHasKey('available_chart_types', $config);
        $this->assertArrayHasKey('default_metrics', $config);
    }

    public function test_reset_to_defaults_with_existing_preferences()
    {
        $existingPreferences = RTUTrendPreference::create([
            'user_id' => $this->user->id,
            'gateway_id' => $this->gateway->id,
            'selected_metrics' => ['cpu_load', 'memory_usage'],
            'time_range' => '6h',
            'chart_type' => 'area',
        ]);

        $preferences = $this->configService->resetToDefaults($this->user, $this->gateway);

        $this->assertEquals($existingPreferences->id, $preferences->id);
        $this->assertEquals(['signal_strength'], $preferences->selected_metrics);
        $this->assertEquals('24h', $preferences->time_range);
        $this->assertEquals('line', $preferences->chart_type);
    }

    public function test_reset_to_defaults_without_existing_preferences()
    {
        $preferences = $this->configService->resetToDefaults($this->user, $this->gateway);

        $this->assertInstanceOf(RTUTrendPreference::class, $preferences);
        $this->assertEquals(['signal_strength'], $preferences->selected_metrics);
        $this->assertEquals('24h', $preferences->time_range);
        $this->assertEquals('line', $preferences->chart_type);
    }

    public function test_delete_preferences_with_existing_preferences()
    {
        RTUTrendPreference::create([
            'user_id' => $this->user->id,
            'gateway_id' => $this->gateway->id,
            'selected_metrics' => ['signal_strength'],
            'time_range' => '24h',
            'chart_type' => 'line',
        ]);

        $result = $this->configService->deletePreferences($this->user, $this->gateway);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('rtu_trend_preferences', [
            'user_id' => $this->user->id,
            'gateway_id' => $this->gateway->id,
        ]);
    }

    public function test_delete_preferences_without_existing_preferences()
    {
        $result = $this->configService->deletePreferences($this->user, $this->gateway);

        $this->assertFalse($result);
    }

    public function test_get_bulk_preferences()
    {
        $gateway2 = Gateway::factory()->create(['gateway_type' => 'teltonika_rut956']);
        $gateway3 = Gateway::factory()->create(['gateway_type' => 'teltonika_rut956']);

        // Create preferences for gateway1 only
        RTUTrendPreference::create([
            'user_id' => $this->user->id,
            'gateway_id' => $this->gateway->id,
            'selected_metrics' => ['signal_strength'],
            'time_range' => '24h',
            'chart_type' => 'line',
        ]);

        $gatewayIds = [$this->gateway->id, $gateway2->id, $gateway3->id];
        $preferences = $this->configService->getBulkPreferences($this->user, $gatewayIds);

        $this->assertCount(3, $preferences);
        $this->assertArrayHasKey($this->gateway->id, $preferences);
        $this->assertArrayHasKey($gateway2->id, $preferences);
        $this->assertArrayHasKey($gateway3->id, $preferences);

        // Check that existing preference is returned
        $this->assertEquals(['signal_strength'], $preferences[$this->gateway->id]->selected_metrics);

        // Check that default preferences are created for missing gateways
        $this->assertEquals(['signal_strength'], $preferences[$gateway2->id]->selected_metrics);
        $this->assertEquals(['signal_strength'], $preferences[$gateway3->id]->selected_metrics);
    }

    public function test_export_user_preferences()
    {
        RTUTrendPreference::create([
            'user_id' => $this->user->id,
            'gateway_id' => $this->gateway->id,
            'selected_metrics' => ['signal_strength', 'cpu_load'],
            'time_range' => '6h',
            'chart_type' => 'area',
        ]);

        $exported = $this->configService->exportUserPreferences($this->user);

        $this->assertIsArray($exported);
        $this->assertCount(1, $exported);
        $this->assertEquals($this->gateway->id, $exported[0]['gateway_id']);
        $this->assertEquals(['signal_strength', 'cpu_load'], $exported[0]['selected_metrics']);
        $this->assertEquals('6h', $exported[0]['time_range']);
        $this->assertEquals('area', $exported[0]['chart_type']);
    }

    public function test_import_user_preferences()
    {
        $gateway2 = Gateway::factory()->create(['gateway_type' => 'teltonika_rut956']);

        $preferencesData = [
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
            ],
        ];

        $result = $this->configService->importUserPreferences($this->user, $preferencesData);

        $this->assertCount(2, $result['imported']);
        $this->assertEmpty($result['errors']);

        // Verify preferences were created
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

    public function test_import_user_preferences_with_invalid_gateway()
    {
        $preferencesData = [
            [
                'gateway_id' => 99999, // Non-existent gateway
                'selected_metrics' => ['signal_strength'],
                'time_range' => '24h',
                'chart_type' => 'line',
            ],
        ];

        $result = $this->configService->importUserPreferences($this->user, $preferencesData);

        $this->assertEmpty($result['imported']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContains('Gateway not found', $result['errors'][0]);
    }

    public function test_import_user_preferences_with_invalid_data()
    {
        $preferencesData = [
            [
                'gateway_id' => $this->gateway->id,
                'selected_metrics' => ['invalid_metric'],
                'time_range' => '24h',
                'chart_type' => 'line',
            ],
        ];

        $result = $this->configService->importUserPreferences($this->user, $preferencesData);

        $this->assertEmpty($result['imported']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContains('Failed to import preference', $result['errors'][0]);
    }
}