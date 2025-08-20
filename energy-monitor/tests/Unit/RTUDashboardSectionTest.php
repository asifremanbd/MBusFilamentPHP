<?php

namespace Tests\Unit;

use App\Models\RTUDashboardSection;
use App\Models\User;
use App\Services\RTUDashboardSectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RTUDashboardSectionTest extends TestCase
{
    use RefreshDatabase;

    private RTUDashboardSectionService $sectionService;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sectionService = new RTUDashboardSectionService();
        $this->user = User::factory()->create();
    }

    public function test_can_get_section_state_for_user(): void
    {
        // Create a section state
        RTUDashboardSection::create([
            'user_id' => $this->user->id,
            'section_name' => 'system_health',
            'is_collapsed' => true,
            'display_order' => 1,
        ]);

        $state = RTUDashboardSection::getSectionState($this->user->id, 'system_health');

        $this->assertTrue($state['is_collapsed']);
        $this->assertEquals(1, $state['display_order']);
    }

    public function test_returns_default_state_for_non_existent_section(): void
    {
        $state = RTUDashboardSection::getSectionState($this->user->id, 'non_existent');

        $this->assertFalse($state['is_collapsed']);
        $this->assertEquals(0, $state['display_order']);
    }

    public function test_can_update_section_state(): void
    {
        RTUDashboardSection::updateSectionState($this->user->id, 'system_health', true, 2);

        $section = RTUDashboardSection::where('user_id', $this->user->id)
            ->where('section_name', 'system_health')
            ->first();

        $this->assertNotNull($section);
        $this->assertTrue($section->is_collapsed);
        $this->assertEquals(2, $section->display_order);
    }

    public function test_can_get_all_user_section_states(): void
    {
        // Create multiple sections
        RTUDashboardSection::create([
            'user_id' => $this->user->id,
            'section_name' => 'system_health',
            'is_collapsed' => true,
            'display_order' => 1,
        ]);

        RTUDashboardSection::create([
            'user_id' => $this->user->id,
            'section_name' => 'network_status',
            'is_collapsed' => false,
            'display_order' => 2,
        ]);

        $states = RTUDashboardSection::getUserSectionStates($this->user->id);

        $this->assertCount(2, $states);
        $this->assertTrue($states['system_health']['is_collapsed']);
        $this->assertFalse($states['network_status']['is_collapsed']);
    }

    public function test_service_returns_default_configuration(): void
    {
        $config = $this->sectionService->getSectionConfiguration($this->user);

        $this->assertArrayHasKey('system_health', $config);
        $this->assertArrayHasKey('network_status', $config);
        $this->assertArrayHasKey('io_monitoring', $config);
        $this->assertArrayHasKey('alerts', $config);
        $this->assertArrayHasKey('trends', $config);

        // Check default values
        $this->assertEquals('System Health', $config['system_health']['name']);
        $this->assertEquals('heroicon-o-cpu-chip', $config['system_health']['icon']);
        $this->assertFalse($config['system_health']['is_collapsed']);
    }

    public function test_service_merges_user_preferences_with_defaults(): void
    {
        // Create user preference
        RTUDashboardSection::create([
            'user_id' => $this->user->id,
            'section_name' => 'system_health',
            'is_collapsed' => true,
            'display_order' => 5,
        ]);

        $config = $this->sectionService->getSectionConfiguration($this->user);

        $this->assertTrue($config['system_health']['is_collapsed']);
        $this->assertEquals(5, $config['system_health']['display_order']);
        $this->assertEquals('System Health', $config['system_health']['name']); // Still uses default name
    }

    public function test_service_can_update_section_state(): void
    {
        $result = $this->sectionService->updateSectionState($this->user, 'system_health', true);

        $this->assertTrue($result);

        $section = RTUDashboardSection::where('user_id', $this->user->id)
            ->where('section_name', 'system_health')
            ->first();

        $this->assertNotNull($section);
        $this->assertTrue($section->is_collapsed);
    }

    public function test_service_rejects_invalid_section_names(): void
    {
        $result = $this->sectionService->updateSectionState($this->user, 'invalid_section', true);

        $this->assertFalse($result);

        $section = RTUDashboardSection::where('user_id', $this->user->id)
            ->where('section_name', 'invalid_section')
            ->first();

        $this->assertNull($section);
    }

    public function test_service_can_reset_to_defaults(): void
    {
        // Create some user preferences
        RTUDashboardSection::create([
            'user_id' => $this->user->id,
            'section_name' => 'system_health',
            'is_collapsed' => true,
            'display_order' => 5,
        ]);

        $this->sectionService->resetToDefaults($this->user);

        $sections = RTUDashboardSection::where('user_id', $this->user->id)->get();
        $this->assertCount(0, $sections);
    }

    public function test_service_can_initialize_user_sections(): void
    {
        $this->sectionService->initializeUserSections($this->user);

        $sections = RTUDashboardSection::where('user_id', $this->user->id)->get();
        $this->assertCount(5, $sections); // Should create all 5 default sections

        $systemHealthSection = $sections->where('section_name', 'system_health')->first();
        $this->assertNotNull($systemHealthSection);
        $this->assertFalse($systemHealthSection->is_collapsed);
        $this->assertEquals(1, $systemHealthSection->display_order);
    }

    public function test_sections_are_sorted_by_display_order(): void
    {
        // Create sections with custom display orders
        RTUDashboardSection::create([
            'user_id' => $this->user->id,
            'section_name' => 'system_health',
            'is_collapsed' => false,
            'display_order' => 3,
        ]);

        RTUDashboardSection::create([
            'user_id' => $this->user->id,
            'section_name' => 'network_status',
            'is_collapsed' => false,
            'display_order' => 1,
        ]);

        $config = $this->sectionService->getSectionConfiguration($this->user);
        $keys = array_keys($config);

        // network_status should come first due to lower display_order
        $this->assertEquals('network_status', $keys[0]);
    }

    public function test_section_icons_mapping_is_available(): void
    {
        $icons = $this->sectionService->getSectionIcons();

        $this->assertArrayHasKey('cpu', $icons);
        $this->assertArrayHasKey('memory', $icons);
        $this->assertArrayHasKey('sim', $icons);
        $this->assertArrayHasKey('signal', $icons);
        $this->assertEquals('heroicon-o-cpu-chip', $icons['cpu']);
    }
}
