<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Filament\Widgets\RTUAlertsWidget;

class RTUAlertsWidgetBasicTest extends TestCase
{
    /** @test */
    public function it_gets_status_summary_correctly()
    {
        $widget = new RTUAlertsWidget();

        // Test critical alerts
        $summary = $widget->getStatusSummary(2, 0);
        $this->assertEquals('2 Critical Alerts', $summary);

        // Test single critical alert
        $summary = $widget->getStatusSummary(1, 0);
        $this->assertEquals('1 Critical Alert', $summary);

        // Test warning alerts
        $summary = $widget->getStatusSummary(0, 3);
        $this->assertEquals('3 Warnings', $summary);

        // Test single warning
        $summary = $widget->getStatusSummary(0, 1);
        $this->assertEquals('1 Warning', $summary);

        // Test no alerts
        $summary = $widget->getStatusSummary(0, 0);
        $this->assertEquals('All Systems OK', $summary);
    }

    /** @test */
    public function it_returns_severity_options()
    {
        $widget = new RTUAlertsWidget();
        $severityOptions = $widget->getSeverityOptions();

        $expectedOptions = [
            ['value' => 'critical', 'label' => 'Critical'],
            ['value' => 'warning', 'label' => 'Warning'],
            ['value' => 'info', 'label' => 'Info']
        ];

        $this->assertEquals($expectedOptions, $severityOptions);
    }

    /** @test */
    public function it_returns_time_range_options()
    {
        $widget = new RTUAlertsWidget();
        $timeRangeOptions = $widget->getTimeRangeOptions();

        $expectedOptions = [
            ['value' => 'last_hour', 'label' => 'Last Hour'],
            ['value' => 'last_day', 'label' => 'Last Day'],
            ['value' => 'last_week', 'label' => 'Last Week'],
            ['value' => 'custom', 'label' => 'Custom Range']
        ];

        $this->assertEquals($expectedOptions, $timeRangeOptions);
    }

    /** @test */
    public function it_creates_correct_device_status_indicator_for_critical_alerts()
    {
        $widget = new RTUAlertsWidget();
        
        $alertsData = [
            'critical_count' => 2,
            'warning_count' => 1,
            'info_count' => 0
        ];

        $deviceStatus = $widget->getDeviceStatusIndicator($alertsData);

        $this->assertEquals('critical', $deviceStatus['status']);
        $this->assertEquals('2 Critical Alerts', $deviceStatus['text']);
        $this->assertEquals('danger', $deviceStatus['color']);
        $this->assertEquals('heroicon-o-exclamation-triangle', $deviceStatus['icon']);
    }

    /** @test */
    public function it_creates_correct_device_status_indicator_for_warning_alerts()
    {
        $widget = new RTUAlertsWidget();
        
        $alertsData = [
            'critical_count' => 0,
            'warning_count' => 3,
            'info_count' => 1
        ];

        $deviceStatus = $widget->getDeviceStatusIndicator($alertsData);

        $this->assertEquals('warning', $deviceStatus['status']);
        $this->assertEquals('3 Warnings', $deviceStatus['text']);
        $this->assertEquals('warning', $deviceStatus['color']);
        $this->assertEquals('heroicon-o-exclamation-circle', $deviceStatus['icon']);
    }

    /** @test */
    public function it_creates_correct_device_status_indicator_for_no_alerts()
    {
        $widget = new RTUAlertsWidget();
        
        $alertsData = [
            'critical_count' => 0,
            'warning_count' => 0,
            'info_count' => 0
        ];

        $deviceStatus = $widget->getDeviceStatusIndicator($alertsData);

        $this->assertEquals('ok', $deviceStatus['status']);
        $this->assertEquals('All Systems OK', $deviceStatus['text']);
        $this->assertEquals('success', $deviceStatus['color']);
        $this->assertEquals('heroicon-o-check-circle', $deviceStatus['icon']);
    }

    /** @test */
    public function it_prioritizes_critical_over_warning_in_device_status()
    {
        $widget = new RTUAlertsWidget();
        
        $alertsData = [
            'critical_count' => 1,
            'warning_count' => 5,
            'info_count' => 2
        ];

        $deviceStatus = $widget->getDeviceStatusIndicator($alertsData);

        $this->assertEquals('critical', $deviceStatus['status']);
        $this->assertEquals('1 Critical Alert', $deviceStatus['text']);
    }
}