<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Filament\Widgets\RTUTrendWidget;
use App\Models\Gateway;
use App\Models\User;
use App\Services\RTUDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class RTUTrendWidgetIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Gateway $rtuGateway;
    protected Gateway $nonRtuGateway;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test user
        $this->user = User::factory()->create([
            'role' => 'admin'
        ]);

        // Create test gateways
        $this->rtuGateway = Gateway::factory()->create([
            'gateway_type' => 'teltonika_rut956',
            'rssi' => -75,
            'cpu_load' => 45.5,
            'memory_usage' => 62.3,
            'analog_input_voltage' => 7.25,
            'last_system_update' => now()
        ]);

        $this->nonRtuGateway = Gateway::factory()->create([
            'gateway_type' => 'generic'
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_renders_widget_with_rtu_gateway_data()
    {
        // Mock RTUDataService to return trend data
        $mockTrendData = [
            'has_data' => true,
            'available_metrics' => ['signal_strength', 'cpu_load', 'memory_usage'],
            'metrics' => [
                'signal_strength' => [
                    ['timestamp' => now()->subHours(2), 'value' => -75, 'unit' => 'dBm'],
                    ['timestamp' => now()->subHour(), 'value' => -73, 'unit' => 'dBm'],
                    ['timestamp' => now(), 'value' => -71, 'unit' => 'dBm']
                ],
                'cpu_load' => [
                    ['timestamp' => now()->subHours(2), 'value' => 45.5, 'unit' => '%'],
                    ['timestamp' => now()->subHour(), 'value' => 48.2, 'unit' => '%'],
                    ['timestamp' => now(), 'value' => 52.1, 'unit' => '%']
                ]
            ],
            'start_time' => now()->subDay(),
            'end_time' => now()
        ];

        $mockService = Mockery::mock(RTUDataService::class);
        $mockService->shouldReceive('getTrendData')
            ->with($this->rtuGateway, '24h')
            ->andReturn($mockTrendData);

        $this->app->instance(RTUDataService::class, $mockService);

        $widget = new RTUTrendWidget();
        $widget->mount($this->rtuGateway, ['signal_strength']);
        
        $data = $widget->getData();

        $this->assertTrue($data['has_data']);
        $this->assertEquals(['signal_strength', 'cpu_load', 'memory_usage'], $data['available_metrics']);
        $this->assertEquals(['signal_strength'], $data['selected_metrics']);
        $this->assertNotEmpty($data['chart_data']);
        $this->assertEquals('24h', $data['time_range']);
    }

    /** @test */
    public function it_handles_no_data_scenario_gracefully()
    {
        // Mock RTUDataService to return no data
        $mockService = Mockery::mock(RTUDataService::class);
        $mockService->shouldReceive('getTrendData')
            ->with($this->rtuGateway, '24h')
            ->andReturn([
                'has_data' => false,
                'message' => 'No data available for selected period',
                'available_metrics' => []
            ]);

        $this->app->instance(RTUDataService::class, $mockService);

        $widget = new RTUTrendWidget();
        $widget->mount($this->rtuGateway);
        
        $data = $widget->getData();

        $this->assertFalse($data['has_data']);
        $this->assertEquals('No data available for selected period', $data['message']);
        $this->assertEmpty($data['available_metrics']);
        $this->assertEmpty($data['chart_data']);
    }

    /** @test */
    public function it_returns_error_for_non_rtu_gateway()
    {
        $widget = new RTUTrendWidget();
        $widget->mount($this->nonRtuGateway);
        
        $data = $widget->getData();

        $this->assertFalse($data['has_data']);
        $this->assertEquals('Invalid or non-RTU gateway', $data['error']);
        $this->assertEmpty($data['available_metrics']);
        $this->assertEmpty($data['chart_data']);
    }

    /** @test */
    public function it_determines_when_widget_should_be_hidden()
    {
        // Mock service to return no data and no available metrics
        $mockService = Mockery::mock(RTUDataService::class);
        $mockService->shouldReceive('getTrendData')
            ->andReturn([
                'has_data' => false,
                'available_metrics' => []
            ]);

        $this->app->instance(RTUDataService::class, $mockService);

        $widget = new RTUTrendWidget();
        $widget->mount($this->rtuGateway);
        
        $this->assertTrue($widget->shouldHide());

        // Mock service to return available metrics but no data
        $mockService = Mockery::mock(RTUDataService::class);
        $mockService->shouldReceive('getTrendData')
            ->andReturn([
                'has_data' => false,
                'available_metrics' => ['signal_strength']
            ]);

        $this->app->instance(RTUDataService::class, $mockService);

        $widget = new RTUTrendWidget();
        $widget->mount($this->rtuGateway);
        
        $this->assertFalse($widget->shouldHide());
    }

    /** @test */
    public function it_handles_different_time_ranges()
    {
        $timeRanges = ['1h', '6h', '24h', '7d', '30d'];

        foreach ($timeRanges as $timeRange) {
            // Mock RTUDataService for each time range
            $mockService = Mockery::mock(RTUDataService::class);
            $mockService->shouldReceive('getTrendData')
                ->with($this->rtuGateway, $timeRange)
                ->andReturn([
                    'has_data' => true,
                    'available_metrics' => ['signal_strength'],
                    'metrics' => [
                        'signal_strength' => [
                            ['timestamp' => now(), 'value' => -75, 'unit' => 'dBm']
                        ]
                    ],
                    'start_time' => now()->sub($timeRange === '1h' ? '1 hour' : $timeRange),
                    'end_time' => now()
                ]);

            $this->app->instance(RTUDataService::class, $mockService);

            $widget = new RTUTrendWidget();
            $widget->mount($this->rtuGateway, [], $timeRange);
            
            $data = $widget->getData();

            $this->assertTrue($data['has_data']);
            $this->assertEquals($timeRange, $data['time_range']);
        }
    }

    /** @test */
    public function it_handles_multiple_selected_metrics()
    {
        // Mock RTUDataService to return multiple metrics
        $mockTrendData = [
            'has_data' => true,
            'available_metrics' => ['signal_strength', 'cpu_load', 'memory_usage', 'analog_input'],
            'metrics' => [
                'signal_strength' => [
                    ['timestamp' => now(), 'value' => -75, 'unit' => 'dBm']
                ],
                'cpu_load' => [
                    ['timestamp' => now(), 'value' => 45.5, 'unit' => '%']
                ],
                'memory_usage' => [
                    ['timestamp' => now(), 'value' => 62.3, 'unit' => '%']
                ],
                'analog_input' => [
                    ['timestamp' => now(), 'value' => 7.25, 'unit' => 'V']
                ]
            ],
            'start_time' => now()->subDay(),
            'end_time' => now()
        ];

        $mockService = Mockery::mock(RTUDataService::class);
        $mockService->shouldReceive('getTrendData')
            ->with($this->rtuGateway, '24h')
            ->andReturn($mockTrendData);

        $this->app->instance(RTUDataService::class, $mockService);

        $selectedMetrics = ['signal_strength', 'cpu_load', 'memory_usage'];
        $widget = new RTUTrendWidget();
        $widget->mount($this->rtuGateway, $selectedMetrics);
        
        $data = $widget->getData();

        $this->assertTrue($data['has_data']);
        $this->assertEquals($selectedMetrics, $data['selected_metrics']);
        $this->assertCount(3, $data['chart_data']['series']);
        $this->assertTrue($data['chart_data']['has_multiple_metrics']);
    }

    /** @test */
    public function it_falls_back_to_default_metrics_when_none_selected()
    {
        // Mock RTUDataService to return available metrics
        $mockTrendData = [
            'has_data' => true,
            'available_metrics' => ['cpu_load', 'memory_usage', 'analog_input'], // No signal_strength
            'metrics' => [
                'cpu_load' => [
                    ['timestamp' => now(), 'value' => 45.5, 'unit' => '%']
                ],
                'memory_usage' => [
                    ['timestamp' => now(), 'value' => 62.3, 'unit' => '%']
                ]
            ],
            'start_time' => now()->subDay(),
            'end_time' => now()
        ];

        $mockService = Mockery::mock(RTUDataService::class);
        $mockService->shouldReceive('getTrendData')
            ->with($this->rtuGateway, '24h')
            ->andReturn($mockTrendData);

        $this->app->instance(RTUDataService::class, $mockService);

        $widget = new RTUTrendWidget();
        $widget->mount($this->rtuGateway, []); // No metrics selected
        
        $data = $widget->getData();

        $this->assertTrue($data['has_data']);
        // Should default to first available metric (cpu_load)
        $this->assertEquals(['cpu_load'], $data['selected_metrics']);
        $this->assertCount(1, $data['chart_data']['series']);
        $this->assertFalse($data['chart_data']['has_multiple_metrics']);
    }

    /** @test */
    public function it_handles_service_errors_gracefully()
    {
        // Mock RTUDataService to return error
        $mockService = Mockery::mock(RTUDataService::class);
        $mockService->shouldReceive('getTrendData')
            ->with($this->rtuGateway, '24h')
            ->andReturn([
                'has_data' => false,
                'message' => 'Failed to retrieve trend data: Connection timeout',
                'available_metrics' => [],
                'error' => 'Trend data collection failed'
            ]);

        $this->app->instance(RTUDataService::class, $mockService);

        $widget = new RTUTrendWidget();
        $widget->mount($this->rtuGateway);
        
        $data = $widget->getData();

        $this->assertFalse($data['has_data']);
        $this->assertStringContains('Failed to retrieve trend data', $data['message']);
        $this->assertEmpty($data['available_metrics']);
        $this->assertEmpty($data['chart_data']);
    }
}