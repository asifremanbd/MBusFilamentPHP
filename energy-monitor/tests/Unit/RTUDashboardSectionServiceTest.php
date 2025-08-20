<?php

namespace Tests\Unit;

use App\Services\RTUDashboardSectionService;
use PHPUnit\Framework\TestCase;

class RTUDashboardSectionServiceTest extends TestCase
{
    private RTUDashboardSectionService $sectionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sectionService = new RTUDashboardSectionService();
    }

    public function test_section_icons_mapping_is_available(): void
    {
        $icons = $this->sectionService->getSectionIcons();

        $this->assertIsArray($icons);
        $this->assertArrayHasKey('cpu', $icons);
        $this->assertArrayHasKey('memory', $icons);
        $this->assertArrayHasKey('sim', $icons);
        $this->assertArrayHasKey('signal', $icons);
        $this->assertEquals('heroicon-o-cpu-chip', $icons['cpu']);
        $this->assertEquals('heroicon-o-memory', $icons['memory']);
        $this->assertEquals('heroicon-o-device-phone-mobile', $icons['sim']);
        $this->assertEquals('heroicon-o-signal', $icons['signal']);
    }

    public function test_default_sections_are_defined(): void
    {
        // Use reflection to access private constant
        $reflection = new \ReflectionClass(RTUDashboardSectionService::class);
        $defaultSections = $reflection->getConstant('DEFAULT_SECTIONS');

        $this->assertIsArray($defaultSections);
        $this->assertArrayHasKey('system_health', $defaultSections);
        $this->assertArrayHasKey('network_status', $defaultSections);
        $this->assertArrayHasKey('io_monitoring', $defaultSections);
        $this->assertArrayHasKey('alerts', $defaultSections);
        $this->assertArrayHasKey('trends', $defaultSections);

        // Check structure of each section
        foreach ($defaultSections as $key => $section) {
            $this->assertArrayHasKey('name', $section);
            $this->assertArrayHasKey('icon', $section);
            $this->assertArrayHasKey('display_order', $section);
            $this->assertArrayHasKey('is_collapsed', $section);
            
            $this->assertIsString($section['name']);
            $this->assertIsString($section['icon']);
            $this->assertIsInt($section['display_order']);
            $this->assertIsBool($section['is_collapsed']);
        }
    }

    public function test_default_sections_have_correct_names(): void
    {
        $reflection = new \ReflectionClass(RTUDashboardSectionService::class);
        $defaultSections = $reflection->getConstant('DEFAULT_SECTIONS');

        $this->assertEquals('System Health', $defaultSections['system_health']['name']);
        $this->assertEquals('Network Status', $defaultSections['network_status']['name']);
        $this->assertEquals('I/O Monitoring', $defaultSections['io_monitoring']['name']);
        $this->assertEquals('Alerts', $defaultSections['alerts']['name']);
        $this->assertEquals('Trends', $defaultSections['trends']['name']);
    }

    public function test_default_sections_have_correct_icons(): void
    {
        $reflection = new \ReflectionClass(RTUDashboardSectionService::class);
        $defaultSections = $reflection->getConstant('DEFAULT_SECTIONS');

        $this->assertEquals('heroicon-o-cpu-chip', $defaultSections['system_health']['icon']);
        $this->assertEquals('heroicon-o-signal', $defaultSections['network_status']['icon']);
        $this->assertEquals('heroicon-o-bolt', $defaultSections['io_monitoring']['icon']);
        $this->assertEquals('heroicon-o-exclamation-triangle', $defaultSections['alerts']['icon']);
        $this->assertEquals('heroicon-o-chart-bar', $defaultSections['trends']['icon']);
    }

    public function test_default_sections_have_sequential_display_order(): void
    {
        $reflection = new \ReflectionClass(RTUDashboardSectionService::class);
        $defaultSections = $reflection->getConstant('DEFAULT_SECTIONS');

        $displayOrders = array_column($defaultSections, 'display_order');
        sort($displayOrders);

        $this->assertEquals([1, 2, 3, 4, 5], $displayOrders);
    }

    public function test_default_sections_are_not_collapsed_by_default(): void
    {
        $reflection = new \ReflectionClass(RTUDashboardSectionService::class);
        $defaultSections = $reflection->getConstant('DEFAULT_SECTIONS');

        foreach ($defaultSections as $section) {
            $this->assertFalse($section['is_collapsed'], 'All sections should be expanded by default');
        }
    }
}