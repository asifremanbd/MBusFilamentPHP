<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Filament\Widgets\RTUSystemHealthWidget;
use App\Models\Gateway;
use App\Services\RTUDataService;
use Mockery;
use Carbon\Carbon;

class RTUSystemHealthWidgetTest extends TestCase
{
    protected RTUSystemHealthWidget $widget;

    protected function setUp(): void
    {
        parent::setUp();
        $this->widget = new RTUSystemHealthWidget();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }



    /** @test */
    public function it_correctly_determines_cpu_status_thresholds()
    {
        // Test normal CPU load
        $this->assertEquals('normal', $this->widget->getCPUStatus(45.0));
        $this->assertEquals('normal', $this->widget->getCPUStatus(79.9));

        // Test warning CPU load
        $this->assertEquals('warning', $this->widget->getCPUStatus(80.0));
        $this->assertEquals('warning', $this->widget->getCPUStatus(85.0));
        $this->assertEquals('warning', $this->widget->getCPUStatus(94.9));

        // Test critical CPU load
        $this->assertEquals('critical', $this->widget->getCPUStatus(95.0));
        $this->assertEquals('critical', $this->widget->getCPUStatus(100.0));

        // Test null CPU load
        $this->assertEquals('unknown', $this->widget->getCPUStatus(null));
    }

    /** @test */
    public function it_correctly_determines_memory_status_thresholds()
    {
        // Test normal memory usage
        $this->assertEquals('normal', $this->widget->getMemoryStatus(50.0));
        $this->assertEquals('normal', $this->widget->getMemoryStatus(84.9));

        // Test warning memory usage
        $this->assertEquals('warning', $this->widget->getMemoryStatus(85.0));
        $this->assertEquals('warning', $this->widget->getMemoryStatus(90.0));
        $this->assertEquals('warning', $this->widget->getMemoryStatus(94.9));

        // Test critical memory usage
        $this->assertEquals('critical', $this->widget->getMemoryStatus(95.0));
        $this->assertEquals('critical', $this->widget->getMemoryStatus(100.0));

        // Test null memory usage
        $this->assertEquals('unknown', $this->widget->getMemoryStatus(null));
    }

    /** @test */
    public function it_correctly_determines_uptime_status()
    {
        // Test online status
        $this->assertEquals('online', $this->widget->getUptimeStatus(24));
        $this->assertEquals('online', $this->widget->getUptimeStatus(168));

        // Test warning status (recently restarted)
        $this->assertEquals('warning', $this->widget->getUptimeStatus(0));

        // Test offline status
        $this->assertEquals('offline', $this->widget->getUptimeStatus(-1));

        // Test unknown status
        $this->assertEquals('unknown', $this->widget->getUptimeStatus(null));
    }

    /** @test */
    public function it_formats_uptime_correctly()
    {
        // Test null uptime
        $this->assertEquals('Data Unavailable', $this->widget->formatUptime(null));

        // Test recently restarted
        $this->assertEquals('Recently Restarted', $this->widget->formatUptime(0));
        
        // Test offline
        $this->assertEquals('Offline', $this->widget->formatUptime(-1));

        // Test hours only
        $this->assertEquals('1 hours', $this->widget->formatUptime(1));
        $this->assertEquals('12 hours', $this->widget->formatUptime(12));
        $this->assertEquals('23 hours', $this->widget->formatUptime(23));

        // Test days only
        $this->assertEquals('1 days', $this->widget->formatUptime(24));
        $this->assertEquals('2 days', $this->widget->formatUptime(48));

        // Test days and hours
        $this->assertEquals('1 days, 1 hours', $this->widget->formatUptime(25));
        $this->assertEquals('3 days, 5 hours', $this->widget->formatUptime(77));
        $this->assertEquals('7 days, 12 hours', $this->widget->formatUptime(180));
    }

    /** @test */
    public function it_formats_percentage_correctly()
    {
        // Test valid percentages
        $this->assertEquals('45.5%', $this->widget->formatPercentage(45.5));
        $this->assertEquals('0.0%', $this->widget->formatPercentage(0.0));
        $this->assertEquals('100.0%', $this->widget->formatPercentage(100.0));
        $this->assertEquals('67.2%', $this->widget->formatPercentage(67.23456));

        // Test null percentage
        $this->assertEquals('Data Unavailable', $this->widget->formatPercentage(null));
    }

    /** @test */
    public function it_returns_correct_status_classes()
    {
        $this->assertEquals('text-red-600 bg-red-50 border-red-200', $this->widget->getStatusClass('critical'));
        $this->assertEquals('text-yellow-600 bg-yellow-50 border-yellow-200', $this->widget->getStatusClass('warning'));
        $this->assertEquals('text-green-600 bg-green-50 border-green-200', $this->widget->getStatusClass('normal'));
        $this->assertEquals('text-green-600 bg-green-50 border-green-200', $this->widget->getStatusClass('online'));
        $this->assertEquals('text-gray-600 bg-gray-50 border-gray-200', $this->widget->getStatusClass('offline'));
        $this->assertEquals('text-gray-500 bg-gray-100 border-gray-300', $this->widget->getStatusClass('unknown'));
        $this->assertEquals('text-gray-500 bg-gray-100 border-gray-300', $this->widget->getStatusClass('unavailable'));
        $this->assertEquals('text-gray-500 bg-gray-100 border-gray-300', $this->widget->getStatusClass('invalid'));
    }

    /** @test */
    public function it_returns_correct_status_icons()
    {
        $this->assertEquals('heroicon-o-exclamation-triangle', $this->widget->getStatusIcon('critical'));
        $this->assertEquals('heroicon-o-exclamation-circle', $this->widget->getStatusIcon('warning'));
        $this->assertEquals('heroicon-o-check-circle', $this->widget->getStatusIcon('normal'));
        $this->assertEquals('heroicon-o-check-circle', $this->widget->getStatusIcon('online'));
        $this->assertEquals('heroicon-o-x-circle', $this->widget->getStatusIcon('offline'));
        $this->assertEquals('heroicon-o-question-mark-circle', $this->widget->getStatusIcon('unknown'));
        $this->assertEquals('heroicon-o-question-mark-circle', $this->widget->getStatusIcon('unavailable'));
        $this->assertEquals('heroicon-o-question-mark-circle', $this->widget->getStatusIcon('invalid'));
    }

    /** @test */
    public function it_returns_correct_health_score_colors()
    {
        // Test excellent health (80-100)
        $this->assertEquals('text-green-600', $this->widget->getHealthScoreColor(100));
        $this->assertEquals('text-green-600', $this->widget->getHealthScoreColor(85));
        $this->assertEquals('text-green-600', $this->widget->getHealthScoreColor(80));

        // Test good health (60-79)
        $this->assertEquals('text-yellow-600', $this->widget->getHealthScoreColor(79));
        $this->assertEquals('text-yellow-600', $this->widget->getHealthScoreColor(65));
        $this->assertEquals('text-yellow-600', $this->widget->getHealthScoreColor(60));

        // Test fair health (30-59)
        $this->assertEquals('text-orange-600', $this->widget->getHealthScoreColor(59));
        $this->assertEquals('text-orange-600', $this->widget->getHealthScoreColor(45));
        $this->assertEquals('text-orange-600', $this->widget->getHealthScoreColor(30));

        // Test poor health (0-29)
        $this->assertEquals('text-red-600', $this->widget->getHealthScoreColor(29));
        $this->assertEquals('text-red-600', $this->widget->getHealthScoreColor(15));
        $this->assertEquals('text-red-600', $this->widget->getHealthScoreColor(0));
    }


}