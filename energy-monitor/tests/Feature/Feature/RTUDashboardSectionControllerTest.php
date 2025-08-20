<?php

namespace Tests\Feature\Feature;

use App\Models\RTUDashboardSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RTUDashboardSectionControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_get_sections_configuration(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/rtu/sections');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'sections' => [
                    'system_health' => [
                        'name',
                        'icon',
                        'display_order',
                        'is_collapsed',
                    ],
                    'network_status',
                    'io_monitoring',
                    'alerts',
                    'trends',
                ]
            ]);

        $this->assertTrue($response->json('success'));
    }

    public function test_can_update_section_state(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/rtu/sections/update', [
            'section_name' => 'system_health',
            'is_collapsed' => true,
            'display_order' => 2,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Section state updated successfully',
            ]);

        // Verify the section was created/updated in database
        $section = RTUDashboardSection::where('user_id', $this->user->id)
            ->where('section_name', 'system_health')
            ->first();

        $this->assertNotNull($section);
        $this->assertTrue($section->is_collapsed);
        $this->assertEquals(2, $section->display_order);
    }

    public function test_update_section_state_validates_input(): void
    {
        Sanctum::actingAs($this->user);

        // Test missing section_name
        $response = $this->postJson('/api/rtu/sections/update', [
            'is_collapsed' => true,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['section_name']);

        // Test invalid is_collapsed type
        $response = $this->postJson('/api/rtu/sections/update', [
            'section_name' => 'system_health',
            'is_collapsed' => 'invalid',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['is_collapsed']);

        // Test invalid display_order
        $response = $this->postJson('/api/rtu/sections/update', [
            'section_name' => 'system_health',
            'is_collapsed' => true,
            'display_order' => -1,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['display_order']);
    }

    public function test_update_section_state_rejects_invalid_section_names(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/rtu/sections/update', [
            'section_name' => 'invalid_section',
            'is_collapsed' => true,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid section name',
            ]);
    }

    public function test_can_reset_sections_to_defaults(): void
    {
        Sanctum::actingAs($this->user);

        // Create some user preferences first
        RTUDashboardSection::create([
            'user_id' => $this->user->id,
            'section_name' => 'system_health',
            'is_collapsed' => true,
            'display_order' => 5,
        ]);

        $response = $this->postJson('/api/rtu/sections/reset');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Sections reset to defaults',
            ]);

        // Verify sections were deleted
        $sections = RTUDashboardSection::where('user_id', $this->user->id)->get();
        $this->assertCount(0, $sections);
    }

    public function test_requires_authentication_for_all_endpoints(): void
    {
        // Test without authentication
        $response = $this->getJson('/api/rtu/sections');
        $response->assertStatus(401);

        $response = $this->postJson('/api/rtu/sections/update', [
            'section_name' => 'system_health',
            'is_collapsed' => true,
        ]);
        $response->assertStatus(401);

        $response = $this->postJson('/api/rtu/sections/reset');
        $response->assertStatus(401);
    }

    public function test_sections_are_user_specific(): void
    {
        $otherUser = User::factory()->create();

        // Create section for first user
        RTUDashboardSection::create([
            'user_id' => $this->user->id,
            'section_name' => 'system_health',
            'is_collapsed' => true,
            'display_order' => 1,
        ]);

        // Create section for second user
        RTUDashboardSection::create([
            'user_id' => $otherUser->id,
            'section_name' => 'system_health',
            'is_collapsed' => false,
            'display_order' => 2,
        ]);

        // Test first user sees their own configuration
        Sanctum::actingAs($this->user);
        $response = $this->getJson('/api/rtu/sections');
        $response->assertStatus(200);
        $sections = $response->json('sections');
        $this->assertTrue($sections['system_health']['is_collapsed']);

        // Test second user sees their own configuration
        Sanctum::actingAs($otherUser);
        $response = $this->getJson('/api/rtu/sections');
        $response->assertStatus(200);
        $sections = $response->json('sections');
        $this->assertFalse($sections['system_health']['is_collapsed']);
    }

    public function test_update_without_display_order_preserves_existing_order(): void
    {
        Sanctum::actingAs($this->user);

        // Create initial section with display order
        RTUDashboardSection::create([
            'user_id' => $this->user->id,
            'section_name' => 'system_health',
            'is_collapsed' => false,
            'display_order' => 3,
        ]);

        // Update without specifying display_order
        $response = $this->postJson('/api/rtu/sections/update', [
            'section_name' => 'system_health',
            'is_collapsed' => true,
        ]);

        $response->assertStatus(200);

        // Verify display_order was preserved
        $section = RTUDashboardSection::where('user_id', $this->user->id)
            ->where('section_name', 'system_health')
            ->first();

        $this->assertTrue($section->is_collapsed);
        $this->assertEquals(3, $section->display_order);
    }
}
