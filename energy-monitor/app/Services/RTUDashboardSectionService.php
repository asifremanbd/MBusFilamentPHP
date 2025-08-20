<?php

namespace App\Services;

use App\Models\RTUDashboardSection;
use App\Models\User;

class RTUDashboardSectionService
{
    /**
     * Default section configuration
     */
    private const DEFAULT_SECTIONS = [
        'system_health' => [
            'name' => 'System Health',
            'icon' => 'heroicon-o-cpu-chip',
            'display_order' => 1,
            'is_collapsed' => false,
        ],
        'network_status' => [
            'name' => 'Network Status',
            'icon' => 'heroicon-o-signal',
            'display_order' => 2,
            'is_collapsed' => false,
        ],
        'io_monitoring' => [
            'name' => 'I/O Monitoring',
            'icon' => 'heroicon-o-bolt',
            'display_order' => 3,
            'is_collapsed' => false,
        ],
        'alerts' => [
            'name' => 'Alerts',
            'icon' => 'heroicon-o-exclamation-triangle',
            'display_order' => 4,
            'is_collapsed' => false,
        ],
        'trends' => [
            'name' => 'Trends',
            'icon' => 'heroicon-o-chart-bar',
            'display_order' => 5,
            'is_collapsed' => false,
        ],
    ];

    /**
     * Get section configuration for a user
     */
    public function getSectionConfiguration(User $user): array
    {
        $userStates = RTUDashboardSection::getUserSectionStates($user->id);
        $sections = [];

        foreach (self::DEFAULT_SECTIONS as $key => $defaultConfig) {
            $userState = $userStates[$key] ?? [];
            
            $sections[$key] = [
                'name' => $defaultConfig['name'],
                'icon' => $defaultConfig['icon'],
                'display_order' => $userState['display_order'] ?? $defaultConfig['display_order'],
                'is_collapsed' => $userState['is_collapsed'] ?? $defaultConfig['is_collapsed'],
            ];
        }

        // Sort by display order
        uasort($sections, fn($a, $b) => $a['display_order'] <=> $b['display_order']);

        return $sections;
    }

    /**
     * Update section state
     */
    public function updateSectionState(User $user, string $sectionName, bool $isCollapsed, ?int $displayOrder = null): bool
    {
        if (!array_key_exists($sectionName, self::DEFAULT_SECTIONS)) {
            return false;
        }

        $currentOrder = $displayOrder ?? RTUDashboardSection::getSectionState($user->id, $sectionName)['display_order'];
        
        RTUDashboardSection::updateSectionState(
            $user->id,
            $sectionName,
            $isCollapsed,
            $currentOrder
        );

        return true;
    }

    /**
     * Reset sections to default state
     */
    public function resetToDefaults(User $user): void
    {
        RTUDashboardSection::where('user_id', $user->id)->delete();
    }

    /**
     * Get section icons mapping
     */
    public function getSectionIcons(): array
    {
        return [
            'cpu' => 'heroicon-o-cpu-chip',
            'memory' => 'heroicon-o-memory',
            'sim' => 'heroicon-o-device-phone-mobile',
            'input' => 'heroicon-o-arrow-down-on-square',
            'output' => 'heroicon-o-arrow-up-on-square',
            'signal' => 'heroicon-o-signal',
            'network' => 'heroicon-o-globe-alt',
            'alert' => 'heroicon-o-exclamation-triangle',
            'chart' => 'heroicon-o-chart-bar',
            'system' => 'heroicon-o-cog-6-tooth',
        ];
    }

    /**
     * Initialize default sections for new user
     */
    public function initializeUserSections(User $user): void
    {
        foreach (self::DEFAULT_SECTIONS as $key => $config) {
            RTUDashboardSection::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'section_name' => $key,
                ],
                [
                    'is_collapsed' => $config['is_collapsed'],
                    'display_order' => $config['display_order'],
                ]
            );
        }
    }
}