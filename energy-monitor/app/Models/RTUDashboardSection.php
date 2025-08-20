<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RTUDashboardSection extends Model
{
    use HasFactory;

    protected $table = 'rtu_dashboard_sections';

    protected $fillable = [
        'user_id',
        'section_name',
        'is_collapsed',
        'display_order',
    ];

    protected $casts = [
        'is_collapsed' => 'boolean',
        'display_order' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get section state for a specific user and section
     */
    public static function getSectionState(int $userId, string $sectionName): array
    {
        $section = static::where('user_id', $userId)
            ->where('section_name', $sectionName)
            ->first();

        return [
            'is_collapsed' => $section?->is_collapsed ?? false,
            'display_order' => $section?->display_order ?? 0,
        ];
    }

    /**
     * Update or create section state
     */
    public static function updateSectionState(int $userId, string $sectionName, bool $isCollapsed, int $displayOrder = 0): void
    {
        static::updateOrCreate(
            [
                'user_id' => $userId,
                'section_name' => $sectionName,
            ],
            [
                'is_collapsed' => $isCollapsed,
                'display_order' => $displayOrder,
            ]
        );
    }

    /**
     * Get all section states for a user
     */
    public static function getUserSectionStates(int $userId): array
    {
        return static::where('user_id', $userId)
            ->orderBy('display_order')
            ->get()
            ->keyBy('section_name')
            ->map(fn($section) => [
                'is_collapsed' => $section->is_collapsed,
                'display_order' => $section->display_order,
            ])
            ->toArray();
    }
}
