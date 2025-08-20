<?php

namespace Tests\Unit;

use App\Models\Gateway;
use App\Models\RTUTrendPreference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RTUTrendPreferenceTest extends TestCase
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
    }

    public function test_can_create_rtu_trend_preference()
    {
        $preference = RTUTrendPreference::create([
            'user_id' => $this->user->id,
            'gateway_id' => $this->gateway->id,
            'selected_metrics' => ['signal_strength', 'cpu_load'],
            'time_range' => '24h',
            'chart_type' => 'line',
        ]);

        $this->assertInstanceOf(RTUTrendPreference::class, $preference);
        $this->assertEquals($this->user->id, $preference->user_id);
        $this->assertEquals($this->gateway->id, $preference->gateway_id);
        $this->assertEquals(['signal_strength', 'cpu_load'], $preference->selected_metrics);
        $this->assertEquals('24h', $preference->time_range);
        $this->assertEquals('line', $preference->chart_type);
    }

    public function test_selected_metrics_are_cast_to_array()
    {
        $preference = RTUTrendPreference::create([
            'user_id' => $this->user->id,
            'gateway_id' => $this->gateway->id,
            'selected_metrics' => '["signal_strength", "cpu_load"]',
            'time_range' => '24h',
            'chart_type' => 'line',
        ]);

        $this->assertIsArray($preference->selected_metrics);
        $this->assertEquals(['signal_strength', 'cpu_load'], $preference->selected_metrics);
    }

    public function test_belongs_to_user()
    {
        $preference = RTUTrendPreference::create([
            'user_id' => $this->user->id,
            'gateway_id' => $this->gateway->id,
            'selected_metrics' => ['signal_strength'],
            'time_range' => '24h',
            'chart_type' => 'line',
        ]);

        $this->assertInstanceOf(User::class, $preference->user);
        $this->assertEquals($this->user->id, $preference->user->id);
    }

    public function test_belongs_to_gateway()
    {
        $preference = RTUTrendPreference::create([
            'user_id' => $this->user->id,
            'gateway_id' => $this->gateway->id,
            'selected_metrics' => ['signal_strength'],
            'time_range' => '24h',
            'chart_type' => 'line',
        ]);

        $this->assertInstanceOf(Gateway::class, $preference->gateway);
        $this->assertEquals($this->gateway->id, $preference->gateway->id);
    }

    public function test_get_default_metrics()
    {
        $defaultMetrics = RTUTrendPreference::getDefaultMetrics();
        
        $this->assertIsArray($defaultMetrics);
        $this->assertEquals(['signal_strength'], $defaultMetrics);
    }

    public function test_get_available_metrics()
    {
        $availableMetrics = RTUTrendPreference::getAvailableMetrics();
        
        $this->assertIsArray($availableMetrics);
        $this->assertArrayHasKey('signal_strength', $availableMetrics);
        $this->assertArrayHasKey('cpu_load', $availableMetrics);
        $this->assertArrayHasKey('memory_usage', $availableMetrics);
        $this->assertArrayHasKey('analog_input', $availableMetrics);
        
        $this->assertEquals('Signal Strength (RSSI)', $availableMetrics['signal_strength']);
        $this->assertEquals('CPU Load (%)', $availableMetrics['cpu_load']);
        $this->assertEquals('Memory Usage (%)', $availableMetrics['memory_usage']);
        $this->assertEquals('Analog Input (V)', $availableMetrics['analog_input']);
    }

    public function test_get_available_time_ranges()
    {
        $availableRanges = RTUTrendPreference::getAvailableTimeRanges();
        
        $this->assertIsArray($availableRanges);
        $this->assertArrayHasKey('1h', $availableRanges);
        $this->assertArrayHasKey('6h', $availableRanges);
        $this->assertArrayHasKey('24h', $availableRanges);
        $this->assertArrayHasKey('7d', $availableRanges);
        
        $this->assertEquals('1 Hour', $availableRanges['1h']);
        $this->assertEquals('6 Hours', $availableRanges['6h']);
        $this->assertEquals('24 Hours', $availableRanges['24h']);
        $this->assertEquals('7 Days', $availableRanges['7d']);
    }

    public function test_get_available_chart_types()
    {
        $availableTypes = RTUTrendPreference::getAvailableChartTypes();
        
        $this->assertIsArray($availableTypes);
        $this->assertArrayHasKey('line', $availableTypes);
        $this->assertArrayHasKey('area', $availableTypes);
        $this->assertArrayHasKey('bar', $availableTypes);
        
        $this->assertEquals('Line Chart', $availableTypes['line']);
        $this->assertEquals('Area Chart', $availableTypes['area']);
        $this->assertEquals('Bar Chart', $availableTypes['bar']);
    }

    public function test_validate_selected_metrics_with_valid_metrics()
    {
        $preference = RTUTrendPreference::create([
            'user_id' => $this->user->id,
            'gateway_id' => $this->gateway->id,
            'selected_metrics' => ['signal_strength', 'cpu_load'],
            'time_range' => '24h',
            'chart_type' => 'line',
        ]);

        $this->assertTrue($preference->validateSelectedMetrics());
    }

    public function test_validate_selected_metrics_with_invalid_metrics()
    {
        $preference = RTUTrendPreference::create([
            'user_id' => $this->user->id,
            'gateway_id' => $this->gateway->id,
            'selected_metrics' => ['invalid_metric'],
            'time_range' => '24h',
            'chart_type' => 'line',
        ]);

        $this->assertFalse($preference->validateSelectedMetrics());
    }

    public function test_validate_selected_metrics_with_empty_metrics()
    {
        $preference = RTUTrendPreference::create([
            'user_id' => $this->user->id,
            'gateway_id' => $this->gateway->id,
            'selected_metrics' => [],
            'time_range' => '24h',
            'chart_type' => 'line',
        ]);

        $this->assertFalse($preference->validateSelectedMetrics());
    }

    public function test_validate_selected_metrics_with_null_metrics()
    {
        $preference = new RTUTrendPreference([
            'user_id' => $this->user->id,
            'gateway_id' => $this->gateway->id,
            'selected_metrics' => null,
            'time_range' => '24h',
            'chart_type' => 'line',
        ]);

        $this->assertFalse($preference->validateSelectedMetrics());
    }

    public function test_validate_time_range_with_valid_range()
    {
        $preference = RTUTrendPreference::create([
            'user_id' => $this->user->id,
            'gateway_id' => $this->gateway->id,
            'selected_metrics' => ['signal_strength'],
            'time_range' => '24h',
            'chart_type' => 'line',
        ]);

        $this->assertTrue($preference->validateTimeRange());
    }

    public function test_validate_time_range_with_invalid_range()
    {
        $preference = RTUTrendPreference::create([
            'user_id' => $this->user->id,
            'gateway_id' => $this->gateway->id,
            'selected_metrics' => ['signal_strength'],
            'time_range' => 'invalid_range',
            'chart_type' => 'line',
        ]);

        $this->assertFalse($preference->validateTimeRange());
    }

    public function test_validate_chart_type_with_valid_type()
    {
        $preference = RTUTrendPreference::create([
            'user_id' => $this->user->id,
            'gateway_id' => $this->gateway->id,
            'selected_metrics' => ['signal_strength'],
            'time_range' => '24h',
            'chart_type' => 'line',
        ]);

        $this->assertTrue($preference->validateChartType());
    }

    public function test_validate_chart_type_with_invalid_type()
    {
        $preference = RTUTrendPreference::create([
            'user_id' => $this->user->id,
            'gateway_id' => $this->gateway->id,
            'selected_metrics' => ['signal_strength'],
            'time_range' => '24h',
            'chart_type' => 'invalid_type',
        ]);

        $this->assertFalse($preference->validateChartType());
    }

    public function test_unique_constraint_prevents_duplicate_user_gateway_preferences()
    {
        RTUTrendPreference::create([
            'user_id' => $this->user->id,
            'gateway_id' => $this->gateway->id,
            'selected_metrics' => ['signal_strength'],
            'time_range' => '24h',
            'chart_type' => 'line',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        
        RTUTrendPreference::create([
            'user_id' => $this->user->id,
            'gateway_id' => $this->gateway->id,
            'selected_metrics' => ['cpu_load'],
            'time_range' => '6h',
            'chart_type' => 'area',
        ]);
    }

    public function test_can_have_multiple_preferences_for_different_gateways()
    {
        $gateway2 = Gateway::factory()->create(['gateway_type' => 'teltonika_rut956']);

        $preference1 = RTUTrendPreference::create([
            'user_id' => $this->user->id,
            'gateway_id' => $this->gateway->id,
            'selected_metrics' => ['signal_strength'],
            'time_range' => '24h',
            'chart_type' => 'line',
        ]);

        $preference2 = RTUTrendPreference::create([
            'user_id' => $this->user->id,
            'gateway_id' => $gateway2->id,
            'selected_metrics' => ['cpu_load'],
            'time_range' => '6h',
            'chart_type' => 'area',
        ]);

        $this->assertNotEquals($preference1->id, $preference2->id);
        $this->assertEquals($this->gateway->id, $preference1->gateway_id);
        $this->assertEquals($gateway2->id, $preference2->gateway_id);
    }

    public function test_can_have_multiple_preferences_for_different_users()
    {
        $user2 = User::factory()->create();

        $preference1 = RTUTrendPreference::create([
            'user_id' => $this->user->id,
            'gateway_id' => $this->gateway->id,
            'selected_metrics' => ['signal_strength'],
            'time_range' => '24h',
            'chart_type' => 'line',
        ]);

        $preference2 = RTUTrendPreference::create([
            'user_id' => $user2->id,
            'gateway_id' => $this->gateway->id,
            'selected_metrics' => ['cpu_load'],
            'time_range' => '6h',
            'chart_type' => 'area',
        ]);

        $this->assertNotEquals($preference1->id, $preference2->id);
        $this->assertEquals($this->user->id, $preference1->user_id);
        $this->assertEquals($user2->id, $preference2->user_id);
    }
}