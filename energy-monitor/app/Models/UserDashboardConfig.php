<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDashboardConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'dashboard_type',
        'widget_config',
        'layout_config'
    ];

    protected $casts = [
        'widget_config' => 'array',
        'layout_config' => 'array'
    ];

    /**
     * Get the user that owns the dashboard configuration
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get widget visibility setting
     */
    public function getWidgetVisibility(string $widgetId): bool
    {
        return $this->widget_config['visibility'][$widgetId] ?? true;
    }

    /**
     * Get widget position
     */
    public function getWidgetPosition(string $widgetId): array
    {
        return $this->layout_config['positions'][$widgetId] ?? ['row' => 0, 'col' => 0];
    }

    /**
     * Get widget size
     */
    public function getWidgetSize(string $widgetId): array
    {
        return $this->layout_config['sizes'][$widgetId] ?? ['width' => 12, 'height' => 4];
    }

    /**
     * Set widget visibility
     */
    public function setWidgetVisibility(string $widgetId, bool $visible): void
    {
        $config = $this->widget_config;
        $config['visibility'][$widgetId] = $visible;
        $this->widget_config = $config;
    }

    /**
     * Set widget position
     */
    public function setWidgetPosition(string $widgetId, array $position): void
    {
        $config = $this->layout_config;
        $config['positions'][$widgetId] = $position;
        $this->layout_config = $config;
    }

    /**
     * Set widget size
     */
    public function setWidgetSize(string $widgetId, array $size): void
    {
        $config = $this->layout_config;
        $config['sizes'][$widgetId] = $size;
        $this->layout_config = $config;
    }

    /**
     * Get all visible widgets
     */
    public function getVisibleWidgets(): array
    {
        $visibility = $this->widget_config['visibility'] ?? [];
        return array_keys(array_filter($visibility));
    }

    /**
     * Get widget configuration for a specific widget
     */
    public function getWidgetConfig(string $widgetId): array
    {
        return [
            'id' => $widgetId,
            'visible' => $this->getWidgetVisibility($widgetId),
            'position' => $this->getWidgetPosition($widgetId),
            'size' => $this->getWidgetSize($widgetId)
        ];
    }

    /**
     * Update multiple widget configurations at once
     */
    public function updateWidgetConfigs(array $updates): void
    {
        $widgetConfig = $this->widget_config;
        $layoutConfig = $this->layout_config;

        foreach ($updates as $widgetId => $config) {
            if (isset($config['visible'])) {
                $widgetConfig['visibility'][$widgetId] = $config['visible'];
            }
            
            if (isset($config['position'])) {
                $layoutConfig['positions'][$widgetId] = $config['position'];
            }
            
            if (isset($config['size'])) {
                $layoutConfig['sizes'][$widgetId] = $config['size'];
            }
        }

        $this->widget_config = $widgetConfig;
        $this->layout_config = $layoutConfig;
    }

    /**
     * Reset to default configuration
     */
    public function resetToDefaults(): void
    {
        if ($this->dashboard_type === 'global') {
            $this->widget_config = [
                'visibility' => [
                    'system-overview' => true,
                    'cross-gateway-alerts' => true,
                    'top-consuming-gateways' => true,
                    'system-health' => true
                ]
            ];
            
            $this->layout_config = [
                'positions' => [
                    'system-overview' => ['row' => 0, 'col' => 0],
                    'cross-gateway-alerts' => ['row' => 0, 'col' => 6],
                    'top-consuming-gateways' => ['row' => 1, 'col' => 0],
                    'system-health' => ['row' => 1, 'col' => 6]
                ],
                'sizes' => [
                    'system-overview' => ['width' => 6, 'height' => 4],
                    'cross-gateway-alerts' => ['width' => 6, 'height' => 4],
                    'top-consuming-gateways' => ['width' => 6, 'height' => 4],
                    'system-health' => ['width' => 6, 'height' => 4]
                ]
            ];
        } else {
            $this->widget_config = [
                'visibility' => [
                    'gateway-device-list' => true,
                    'real-time-readings' => true,
                    'gateway-stats' => true,
                    'gateway-alerts' => true
                ]
            ];
            
            $this->layout_config = [
                'positions' => [
                    'gateway-device-list' => ['row' => 0, 'col' => 0],
                    'real-time-readings' => ['row' => 0, 'col' => 8],
                    'gateway-stats' => ['row' => 1, 'col' => 0],
                    'gateway-alerts' => ['row' => 1, 'col' => 6]
                ],
                'sizes' => [
                    'gateway-device-list' => ['width' => 8, 'height' => 6],
                    'real-time-readings' => ['width' => 4, 'height' => 6],
                    'gateway-stats' => ['width' => 6, 'height' => 4],
                    'gateway-alerts' => ['width' => 6, 'height' => 4]
                ]
            ];
        }
    }

    /**
     * Scope to get configuration for specific dashboard type
     */
    public function scopeForDashboardType($query, string $dashboardType)
    {
        return $query->where('dashboard_type', $dashboardType);
    }

    /**
     * Scope to get configuration for specific user
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Get all hidden widgets
     */
    public function getHiddenWidgets(): array
    {
        $visibility = $this->widget_config['visibility'] ?? [];
        return array_keys(array_filter($visibility, function($visible) {
            return !$visible;
        }));
    }

    /**
     * Update widget layout (position and size)
     */
    public function updateWidgetLayout(string $widgetId, array $position, array $size): void
    {
        $this->setWidgetPosition($widgetId, $position);
        $this->setWidgetSize($widgetId, $size);
        $this->save();
    }
}