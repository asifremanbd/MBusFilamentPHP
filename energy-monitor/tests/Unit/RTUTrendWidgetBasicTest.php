<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Filament\Widgets\RTUTrendWidget;

class RTUTrendWidgetBasicTest extends TestCase
{
    protected RTUTrendWidget $widget;

    protected function setUp(): void
    {
        parent::setUp();
        $this->widget = new RTUTrendWidget();
    }

    /** @test */
    public function it_returns_correct_metric_configurations()
    {
        $configs = $this->widget->getMetricConfigurations();

        $this->assertArrayHasKey('signal_strength', $configs);
        $this->assertArrayHasKey('cpu_load', $configs);
        $this->assertArrayHasKey('memory_usage', $configs);
        $this->assertArrayHasKey('analog_input', $configs);

        // Test signal_strength config
        $signalConfig = $configs['signal_strength'];
        $this->assertEquals('Signal Strength', $signalConfig['label']);
        $this->assertEquals('dBm', $signalConfig['unit']);
        $this->assertEquals('#10B981', $signalConfig['color']);
        $this->assertEquals(-120, $signalConfig['min']);
        $this->assertEquals(-30, $signalConfig['max']);

        // Test cpu_load config
        $cpuConfig = $configs['cpu_load'];
        $this->assertEquals('CPU Load', $cpuConfig['label']);
        $this->assertEquals('%', $cpuConfig['unit']);
        $this->assertEquals(0, $cpuConfig['min']);
        $this->assertEquals(100, $cpuConfig['max']);
    }

    /** @test */
    public function it_gets_individual_metric_configuration()
    {
        $signalConfig = $this->widget->getMetricConfiguration('signal_strength');
        $this->assertEquals('Signal Strength', $signalConfig['label']);
        $this->assertEquals('dBm', $signalConfig['unit']);

        // Test unknown metric
        $unknownConfig = $this->widget->getMetricConfiguration('unknown_metric');
        $this->assertEquals('Unknown metric', $unknownConfig['label']);
        $this->assertEquals('', $unknownConfig['unit']);
        $this->assertEquals('#6B7280', $unknownConfig['color']);
    }

    /** @test */
    public function it_returns_correct_chart_options()
    {
        $options = $this->widget->getChartOptions();

        $this->assertArrayHasKey('chart', $options);
        $this->assertArrayHasKey('stroke', $options);
        $this->assertArrayHasKey('markers', $options);
        $this->assertArrayHasKey('grid', $options);
        $this->assertArrayHasKey('legend', $options);
        $this->assertArrayHasKey('tooltip', $options);
        $this->assertArrayHasKey('xaxis', $options);

        $this->assertEquals('line', $options['chart']['type']);
        $this->assertEquals(350, $options['chart']['height']);
        $this->assertEquals('smooth', $options['stroke']['curve']);
        $this->assertEquals('datetime', $options['xaxis']['type']);
    }

    /** @test */
    public function it_determines_default_metrics_correctly()
    {
        $availableMetrics = ['cpu_load', 'memory_usage', 'analog_input'];
        
        // Test with no selected metrics - should return signal_strength if available
        $result = $this->widget->determineMetricsToShow($availableMetrics);
        $this->assertEquals(['cpu_load'], $result); // First in priority after signal_strength

        // Test with signal_strength available
        $availableMetrics = ['signal_strength', 'cpu_load', 'memory_usage'];
        $result = $this->widget->determineMetricsToShow($availableMetrics);
        $this->assertEquals(['signal_strength'], $result);

        // Test with empty available metrics
        $result = $this->widget->determineMetricsToShow([]);
        $this->assertEquals([], $result);
    }

    /** @test */
    public function it_filters_selected_metrics_by_availability()
    {
        $this->widget->selectedMetrics = ['signal_strength', 'cpu_load', 'non_existent_metric'];
        
        $availableMetrics = ['signal_strength', 'memory_usage'];
        $result = $this->widget->determineMetricsToShow($availableMetrics);
        
        // Should only include signal_strength (available and selected)
        $this->assertEquals(['signal_strength'], $result);
    }

    /** @test */
    public function it_falls_back_to_defaults_when_no_selected_metrics_are_available()
    {
        $this->widget->selectedMetrics = ['non_existent_metric1', 'non_existent_metric2'];
        
        $availableMetrics = ['cpu_load', 'memory_usage'];
        $result = $this->widget->determineMetricsToShow($availableMetrics);
        
        // Should fall back to first available default metric
        $this->assertEquals(['cpu_load'], $result);
    }

    /** @test */
    public function it_prepares_chart_data_correctly()
    {
        $metricsData = [
            'signal_strength' => [
                ['timestamp' => '2024-01-01 10:00:00', 'value' => -75],
                ['timestamp' => '2024-01-01 11:00:00', 'value' => -73]
            ],
            'cpu_load' => [
                ['timestamp' => '2024-01-01 10:00:00', 'value' => 45.5],
                ['timestamp' => '2024-01-01 11:00:00', 'value' => 48.2]
            ]
        ];

        $metricsToShow = ['signal_strength', 'cpu_load'];
        $chartData = $this->widget->prepareChartData($metricsData, $metricsToShow);

        $this->assertCount(2, $chartData['series']);
        $this->assertTrue($chartData['has_multiple_metrics']);
        
        // Check first series (signal_strength)
        $signalSeries = $chartData['series'][0];
        $this->assertEquals('Signal Strength', $signalSeries['name']);
        $this->assertEquals('#10B981', $signalSeries['color']);
        $this->assertCount(2, $signalSeries['data']);
        
        // Check second series (cpu_load)
        $cpuSeries = $chartData['series'][1];
        $this->assertEquals('CPU Load', $cpuSeries['name']);
        $this->assertEquals('#F59E0B', $cpuSeries['color']);
        $this->assertCount(2, $cpuSeries['data']);
    }

    /** @test */
    public function it_handles_empty_metric_data_in_chart_preparation()
    {
        $metricsData = [
            'signal_strength' => [],
            'cpu_load' => [
                ['timestamp' => '2024-01-01 10:00:00', 'value' => 45.5]
            ]
        ];

        $metricsToShow = ['signal_strength', 'cpu_load'];
        $chartData = $this->widget->prepareChartData($metricsData, $metricsToShow);

        // Should only include cpu_load series (signal_strength is empty)
        $this->assertCount(1, $chartData['series']);
        $this->assertEquals('CPU Load', $chartData['series'][0]['name']);
        $this->assertFalse($chartData['has_multiple_metrics']);
    }

    /** @test */
    public function it_updates_selected_metrics_correctly()
    {
        $newMetrics = ['cpu_load', 'memory_usage'];
        $this->widget->updateSelectedMetrics($newMetrics);
        
        $this->assertEquals($newMetrics, $this->widget->selectedMetrics);
    }

    /** @test */
    public function it_updates_time_range_correctly()
    {
        $newTimeRange = '7d';
        $this->widget->updateTimeRange($newTimeRange);
        
        $this->assertEquals($newTimeRange, $this->widget->timeRange);
    }

    /** @test */
    public function it_returns_correct_time_range_options()
    {
        $options = $this->widget->getTimeRangeOptions();

        $expectedOptions = [
            '1h' => '1 Hour',
            '6h' => '6 Hours',
            '24h' => '24 Hours',
            '7d' => '7 Days',
            '30d' => '30 Days'
        ];

        $this->assertEquals($expectedOptions, $options);
    }

    /** @test */
    public function it_returns_correct_metric_status_classes()
    {
        // Test signal strength
        $this->assertEquals('text-green-600', $this->widget->getMetricStatusClass('signal_strength', -65));
        $this->assertEquals('text-yellow-600', $this->widget->getMetricStatusClass('signal_strength', -80));
        $this->assertEquals('text-red-600', $this->widget->getMetricStatusClass('signal_strength', -95));

        // Test CPU load
        $this->assertEquals('text-green-600', $this->widget->getMetricStatusClass('cpu_load', 50));
        $this->assertEquals('text-yellow-600', $this->widget->getMetricStatusClass('cpu_load', 70));
        $this->assertEquals('text-red-600', $this->widget->getMetricStatusClass('cpu_load', 85));

        // Test memory usage
        $this->assertEquals('text-green-600', $this->widget->getMetricStatusClass('memory_usage', 60));
        $this->assertEquals('text-yellow-600', $this->widget->getMetricStatusClass('memory_usage', 80));
        $this->assertEquals('text-red-600', $this->widget->getMetricStatusClass('memory_usage', 90));

        // Test analog input
        $this->assertEquals('text-blue-600', $this->widget->getMetricStatusClass('analog_input', 5.5));

        // Test unknown metric
        $this->assertEquals('text-gray-600', $this->widget->getMetricStatusClass('unknown_metric', 100));
    }

    /** @test */
    public function it_formats_metric_values_correctly()
    {
        // Test null value
        $this->assertEquals('N/A', $this->widget->formatMetricValue('signal_strength', null));

        // Test signal strength
        $this->assertEquals('-75 dBm', $this->widget->formatMetricValue('signal_strength', -75.3));

        // Test CPU load
        $this->assertEquals('45.5%', $this->widget->formatMetricValue('cpu_load', 45.52));

        // Test memory usage
        $this->assertEquals('62.3%', $this->widget->formatMetricValue('memory_usage', 62.34));

        // Test analog input
        $this->assertEquals('7.25 V', $this->widget->formatMetricValue('analog_input', 7.254));

        // Test unknown metric
        $this->assertEquals('123.46 ', $this->widget->formatMetricValue('unknown_metric', 123.456));
    }

    /** @test */
    public function it_handles_different_time_ranges_in_mount()
    {
        $this->widget->mount(null, ['signal_strength'], '7d');
        
        $this->assertEquals(['signal_strength'], $this->widget->selectedMetrics);
        $this->assertEquals('7d', $this->widget->timeRange);
    }

    /** @test */
    public function it_handles_empty_selected_metrics_in_mount()
    {
        $this->widget->mount(null, [], '1h');
        
        $this->assertEquals([], $this->widget->selectedMetrics);
        $this->assertEquals('1h', $this->widget->timeRange);
    }
}