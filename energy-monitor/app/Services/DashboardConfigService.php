<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserDashboardConfig;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class DashboardConfigService
{
    /**
     * Get or create dashboard configuration for a user
     */
    public function getUserDashboardConfig(User $user, string $dashboardType): UserDashboardConfig
    {
        return UserDashboardConfig::firstOrCreate([
            'user_id' => $user->id,
            'dashboard_type' => $dashboardType
        ], [
            'widget_config' => $this->getDefaultWidgetConfig($dashboardType),
            'layout_config' => $this->getDefaultLayoutConfig($dashboardType)
        ]);
    }

    /**
     * Update widget visibility for a user's dashboard
     */
    public function updateWidgetVisibility(User $user, string $dashboardType, string $widgetId, bool $visible): void
    {
        $this->validateWidgetId($widgetId, $dashboardType);
        
        $config = $this->getUserDashboardConfig($user, $dashboardType);
        $config->setWidgetVisibility($widgetId, $visible);
    }

    /**
     * Update widget layout (position and size) for multiple widgets
     */
    public function updateWidgetLayout(User $user, string $dashboardType, array $layoutUpdates): void
    {
        $this->validateLayoutUpdates($layoutUpdates, $dashboardType);
        
        $config = $this->getUserDashboardConfig($user, $dashboardType);
        
        foreach ($layoutUpdates as $widgetId => $layout) {
            if (isset($layout['position']) && isset($layout['size'])) {
                $config->updateWidgetLayout($widgetId, $layout['position'], $layout['size']);
            } elseif (isset($layout['position'])) {
                $config->setWidgetPosition($widgetId, $layout['position']);
            } elseif (isset($layout['size'])) {
                $config->setWidgetSize($widgetId, $layout['size']);
            }
        }
    }

    /**
     * Update single widget layout
     */
    public function updateSingleWidgetLayout(User $user, string $dashboardType, string $widgetId, array $position, array $size): void
    {
        $this->validateWidgetId($widgetId, $dashboardType);
        $this->validatePosition($position);
        $this->validateSize($size);
        
        $config = $this->getUserDashboardConfig($user, $dashboardType);
        $config->updateWidgetLayout($widgetId, $position, $size);
    }

    /**
     * Reset dashboard configuration to defaults
     */
    public function resetDashboardConfig(User $user, string $dashboardType): UserDashboardConfig
    {
        $config = $this->getUserDashboardConfig($user, $dashboardType);
        
        $config->update([
            'widget_config' => $this->getDefaultWidgetConfig($dashboardType),
            'layout_config' => $this->getDefaultLayoutConfig($dashboardType)
        ]);
        
        return $config->fresh();
    }

    /**
     * Get default widget configuration for dashboard type
     */
    public function getDefaultWidgetConfig(string $dashboardType): array
    {
        return match($dashboardType) {
            'global' => [
                'visibility' => [
                    'system-overview' => true,
                    'cross-gateway-alerts' => true,
                    'top-consuming-gateways' => true,
                    'system-health' => true,
                ]
            ],
            'gateway' => [
                'visibility' => [
                    'gateway-device-list' => true,
                    'real-time-readings' => true,
                    'gateway-stats' => true,
                    'gateway-alerts' => true,
                ]
            ],
            'rtu' => [
                'visibility' => [
                    'rtu-system-health' => true,
                    'rtu-network-status' => true,
                    'rtu-io-monitoring' => true,
                    'rtu-alerts' => true,
                    'rtu-trend-visualization' => true,
                ],
                'sections' => [
                    'system-health' => true,
                    'network-status' => true,
                    'io-monitoring' => true,
                ]
            ],
            default => [
                'visibility' => []
            ]
        };
    }

    /**
     * Get default layout configuration for dashboard type
     */
    public function getDefaultLayoutConfig(string $dashboardType): array
    {
        return match($dashboardType) {
            'global' => [
                'positions' => [
                    'system-overview' => ['row' => 0, 'col' => 0],
                    'cross-gateway-alerts' => ['row' => 0, 'col' => 6],
                    'top-consuming-gateways' => ['row' => 1, 'col' => 0],
                    'system-health' => ['row' => 1, 'col' => 6],
                ],
                'sizes' => [
                    'system-overview' => ['width' => 6, 'height' => 4],
                    'cross-gateway-alerts' => ['width' => 6, 'height' => 4],
                    'top-consuming-gateways' => ['width' => 6, 'height' => 6],
                    'system-health' => ['width' => 6, 'height' => 6],
                ]
            ],
            'gateway' => [
                'positions' => [
                    'gateway-device-list' => ['row' => 0, 'col' => 0],
                    'real-time-readings' => ['row' => 0, 'col' => 8],
                    'gateway-stats' => ['row' => 1, 'col' => 0],
                    'gateway-alerts' => ['row' => 1, 'col' => 6],
                ],
                'sizes' => [
                    'gateway-device-list' => ['width' => 8, 'height' => 6],
                    'real-time-readings' => ['width' => 4, 'height' => 6],
                    'gateway-stats' => ['width' => 6, 'height' => 4],
                    'gateway-alerts' => ['width' => 6, 'height' => 4],
                ]
            ],
            'rtu' => [
                'positions' => [
                    'rtu-system-health' => ['row' => 0, 'col' => 0],
                    'rtu-network-status' => ['row' => 0, 'col' => 4],
                    'rtu-io-monitoring' => ['row' => 0, 'col' => 8],
                    'rtu-alerts' => ['row' => 1, 'col' => 0],
                    'rtu-trend-visualization' => ['row' => 1, 'col' => 6],
                ],
                'sizes' => [
                    'rtu-system-health' => ['width' => 4, 'height' => 4],
                    'rtu-network-status' => ['width' => 4, 'height' => 4],
                    'rtu-io-monitoring' => ['width' => 4, 'height' => 4],
                    'rtu-alerts' => ['width' => 6, 'height' => 6],
                    'rtu-trend-visualization' => ['width' => 6, 'height' => 6],
                ],
                'sections' => [
                    'system-health' => ['collapsed' => false],
                    'network-status' => ['collapsed' => false],
                    'io-monitoring' => ['collapsed' => false],
                ]
            ],
            default => [
                'positions' => [],
                'sizes' => []
            ]
        };
    }

    /**
     * Get available widgets for dashboard type
     */
    public function getAvailableWidgets(string $dashboardType): array
    {
        return match($dashboardType) {
            'global' => [
                'system-overview' => [
                    'name' => 'System Overview',
                    'description' => 'Overall system statistics and energy consumption',
                    'category' => 'overview'
                ],
                'cross-gateway-alerts' => [
                    'name' => 'Cross-Gateway Alerts',
                    'description' => 'Alerts from all authorized gateways',
                    'category' => 'alerts'
                ],
                'top-consuming-gateways' => [
                    'name' => 'Top Consuming Gateways',
                    'description' => 'Gateways with highest energy consumption',
                    'category' => 'analytics'
                ],
                'system-health' => [
                    'name' => 'System Health',
                    'description' => 'Overall system health indicators',
                    'category' => 'monitoring'
                ],
            ],
            'gateway' => [
                'gateway-device-list' => [
                    'name' => 'Device List',
                    'description' => 'List of devices in this gateway',
                    'category' => 'devices'
                ],
                'real-time-readings' => [
                    'name' => 'Real-time Readings',
                    'description' => 'Live readings from gateway devices',
                    'category' => 'monitoring'
                ],
                'gateway-stats' => [
                    'name' => 'Gateway Statistics',
                    'description' => 'Gateway communication and performance stats',
                    'category' => 'overview'
                ],
                'gateway-alerts' => [
                    'name' => 'Gateway Alerts',
                    'description' => 'Alerts specific to this gateway',
                    'category' => 'alerts'
                ],
            ],
            'rtu' => [
                'rtu-system-health' => [
                    'name' => 'RTU System Health',
                    'description' => 'Router uptime, CPU load, and memory usage monitoring',
                    'category' => 'system'
                ],
                'rtu-network-status' => [
                    'name' => 'RTU Network Status',
                    'description' => 'WAN IP, SIM details, and signal quality metrics',
                    'category' => 'network'
                ],
                'rtu-io-monitoring' => [
                    'name' => 'RTU I/O Monitoring',
                    'description' => 'Digital inputs/outputs and analog input monitoring with control',
                    'category' => 'io'
                ],
                'rtu-alerts' => [
                    'name' => 'RTU Alerts',
                    'description' => 'Grouped and filtered RTU-specific alerts',
                    'category' => 'alerts'
                ],
                'rtu-trend-visualization' => [
                    'name' => 'RTU Trend Visualization',
                    'description' => 'Multi-metric trend charts with selectable parameters',
                    'category' => 'analytics'
                ],
            ],
            default => []
        };
    }

    /**
     * Validate widget ID for dashboard type
     */
    private function validateWidgetId(string $widgetId, string $dashboardType): void
    {
        $availableWidgets = array_keys($this->getAvailableWidgets($dashboardType));
        
        if (!in_array($widgetId, $availableWidgets)) {
            throw new ValidationException(
                Validator::make([], [])
                    ->errors()
                    ->add('widget_id', "Widget '{$widgetId}' is not available for dashboard type '{$dashboardType}'")
            );
        }
    }

    /**
     * Validate layout updates array
     */
    private function validateLayoutUpdates(array $layoutUpdates, string $dashboardType): void
    {
        foreach ($layoutUpdates as $widgetId => $layout) {
            $this->validateWidgetId($widgetId, $dashboardType);
            
            if (isset($layout['position'])) {
                $this->validatePosition($layout['position']);
            }
            
            if (isset($layout['size'])) {
                $this->validateSize($layout['size']);
            }
        }
    }

    /**
     * Validate position array
     */
    private function validatePosition(array $position): void
    {
        $validator = Validator::make($position, [
            'row' => 'required|integer|min:0|max:20',
            'col' => 'required|integer|min:0|max:12',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * Validate size array
     */
    private function validateSize(array $size): void
    {
        $validator = Validator::make($size, [
            'width' => 'required|integer|min:1|max:12',
            'height' => 'required|integer|min:1|max:20',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * Get dashboard configuration summary for user
     */
    public function getDashboardSummary(User $user): array
    {
        $globalConfig = $this->getUserDashboardConfig($user, 'global');
        $gatewayConfig = $this->getUserDashboardConfig($user, 'gateway');

        return [
            'global' => [
                'visible_widgets' => count($globalConfig->getVisibleWidgets()),
                'hidden_widgets' => count($globalConfig->getHiddenWidgets()),
                'last_updated' => $globalConfig->updated_at,
            ],
            'gateway' => [
                'visible_widgets' => count($gatewayConfig->getVisibleWidgets()),
                'hidden_widgets' => count($gatewayConfig->getHiddenWidgets()),
                'last_updated' => $gatewayConfig->updated_at,
            ],
        ];
    }

    /**
     * Export dashboard configuration
     */
    public function exportDashboardConfig(User $user, string $dashboardType): array
    {
        $config = $this->getUserDashboardConfig($user, $dashboardType);
        
        return [
            'dashboard_type' => $dashboardType,
            'user_id' => $user->id,
            'widget_config' => $config->widget_config,
            'layout_config' => $config->layout_config,
            'exported_at' => now()->toISOString(),
        ];
    }

    /**
     * Import dashboard configuration
     */
    public function importDashboardConfig(User $user, array $configData): UserDashboardConfig
    {
        $validator = Validator::make($configData, [
            'dashboard_type' => 'required|in:global,gateway',
            'widget_config' => 'required|array',
            'layout_config' => 'required|array',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $config = $this->getUserDashboardConfig($user, $configData['dashboard_type']);
        
        // Validate imported widgets exist for this dashboard type
        $availableWidgets = array_keys($this->getAvailableWidgets($configData['dashboard_type']));
        $importedWidgets = array_keys($configData['widget_config']['visibility'] ?? []);
        
        foreach ($importedWidgets as $widgetId) {
            if (!in_array($widgetId, $availableWidgets)) {
                unset($configData['widget_config']['visibility'][$widgetId]);
                unset($configData['layout_config']['positions'][$widgetId]);
                unset($configData['layout_config']['sizes'][$widgetId]);
            }
        }

        $config->update([
            'widget_config' => $configData['widget_config'],
            'layout_config' => $configData['layout_config'],
        ]);

        return $config->fresh();
    }
}