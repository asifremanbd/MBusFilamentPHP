<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Models\User;
use App\Models\UserDashboardConfig;
use App\Services\DashboardConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\CreatesApplication;

class DashboardConfigServiceTest extends TestCase
{
    use CreatesApplication, RefreshDatabase;

    protected DashboardConfigService $configService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createApplication();
        $this->configService = new DashboardConfigService();
    }

    public function test_get_user_dashboard_config_creates_new_config()
    {
        $user = User::factory()->create();

        $config = $this->configService->getUserDashboardConfig($user, 'global');

        $this->assertInstanceOf(UserDashboardConfig::class, $config);
        $this->assertEquals($user->id, $config->user_id);
        $this->assertEquals('global', $config->dashboard_type);
        $this->assertIsArray($config->widget_config);
        $this->assertIsArray($config->layout_config);
    }

    public function test_get_user_dashboard_config_returns_existing_config()
    {
        $user = User::factory()->create();
        
        $existingConfig = UserDashboardConfig::create([
            'user_id' => $user->id,
            'dashboard_type' => 'global',
            'widget_config' => ['test' => 'data'],
            'layout_config' => ['test' => 'layout'],
        ]);

        $config = $this->configService->getUserDashboardConfig($user, 'global');

        $this->assertEquals($existingConfig->id, $config->id);
        $this->assertEquals(['test' => 'data'], $config->widget_config);
    }

    public function test_update_widget_visibility()
    {
        $user = User::factory()->create();

        $this->configService->updateWidgetVisibility($user, 'global', 'system-overview', false);

        $config = $this->configService->getUserDashboardConfig($user, 'global');
        $this->assertFalse($config->getWidgetVisibility('system-overview'));
    }

    public function test_update_widget_visibility_with_invalid_widget_throws_exception()
    {
        $user = User::factory()->create();

        $this->expectException(ValidationException::class);
        $this->configService->updateWidgetVisibility($user, 'global', 'invalid-widget', false);
    }

    public function test_update_widget_layout()
    {
        $user = User::factory()->create();

        $layoutUpdates = [
            'system-overview' => [
                'position' => ['row' => 1, 'col' => 2],
                'size' => ['width' => 8, 'height' => 6],
            ],
            'cross-gateway-alerts' => [
                'position' => ['row' => 2, 'col' => 0],
            ],
        ];

        $this->configService->updateWidgetLayout($user, 'global', $layoutUpdates);

        $config = $this->configService->getUserDashboardConfig($user, 'global');
        $this->assertEquals(['row' => 1, 'col' => 2], $config->getWidgetPosition('system-overview'));
        $this->assertEquals(['width' => 8, 'height' => 6], $config->getWidgetSize('system-overview'));
        $this->assertEquals(['row' => 2, 'col' => 0], $config->getWidgetPosition('cross-gateway-alerts'));
    }

    public function test_update_single_widget_layout()
    {
        $user = User::factory()->create();

        $this->configService->updateSingleWidgetLayout(
            $user,
            'global',
            'system-overview',
            ['row' => 3, 'col' => 4],
            ['width' => 10, 'height' => 8]
        );

        $config = $this->configService->getUserDashboardConfig($user, 'global');
        $this->assertEquals(['row' => 3, 'col' => 4], $config->getWidgetPosition('system-overview'));
        $this->assertEquals(['width' => 10, 'height' => 8], $config->getWidgetSize('system-overview'));
    }

    public function test_update_single_widget_layout_with_invalid_position_throws_exception()
    {
        $user = User::factory()->create();

        $this->expectException(ValidationException::class);
        $this->configService->updateSingleWidgetLayout(
            $user,
            'global',
            'system-overview',
            ['row' => -1, 'col' => 4], // Invalid row
            ['width' => 10, 'height' => 8]
        );
    }

    public function test_update_single_widget_layout_with_invalid_size_throws_exception()
    {
        $user = User::factory()->create();

        $this->expectException(ValidationException::class);
        $this->configService->updateSingleWidgetLayout(
            $user,
            'global',
            'system-overview',
            ['row' => 1, 'col' => 4],
            ['width' => 0, 'height' => 8] // Invalid width
        );
    }

    public function test_reset_dashboard_config()
    {
        $user = User::factory()->create();
        
        // Create config with custom settings
        $config = UserDashboardConfig::create([
            'user_id' => $user->id,
            'dashboard_type' => 'global',
            'widget_config' => ['custom' => 'data'],
            'layout_config' => ['custom' => 'layout'],
        ]);

        $resetConfig = $this->configService->resetDashboardConfig($user, 'global');

        $this->assertNotEquals(['custom' => 'data'], $resetConfig->widget_config);
        $this->assertNotEquals(['custom' => 'layout'], $resetConfig->layout_config);
        $this->assertArrayHasKey('visibility', $resetConfig->widget_config);
        $this->assertArrayHasKey('positions', $resetConfig->layout_config);
    }

    public function test_get_default_widget_config_for_global_dashboard()
    {
        $config = $this->configService->getDefaultWidgetConfig('global');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('visibility', $config);
        $this->assertArrayHasKey('system-overview', $config['visibility']);
        $this->assertArrayHasKey('cross-gateway-alerts', $config['visibility']);
        $this->assertTrue($config['visibility']['system-overview']);
    }

    public function test_get_default_widget_config_for_gateway_dashboard()
    {
        $config = $this->configService->getDefaultWidgetConfig('gateway');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('visibility', $config);
        $this->assertArrayHasKey('gateway-device-list', $config['visibility']);
        $this->assertArrayHasKey('real-time-readings', $config['visibility']);
        $this->assertTrue($config['visibility']['gateway-device-list']);
    }

    public function test_get_default_layout_config_for_global_dashboard()
    {
        $config = $this->configService->getDefaultLayoutConfig('global');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('positions', $config);
        $this->assertArrayHasKey('sizes', $config);
        $this->assertArrayHasKey('system-overview', $config['positions']);
        $this->assertArrayHasKey('system-overview', $config['sizes']);
    }

    public function test_get_default_layout_config_for_gateway_dashboard()
    {
        $config = $this->configService->getDefaultLayoutConfig('gateway');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('positions', $config);
        $this->assertArrayHasKey('sizes', $config);
        $this->assertArrayHasKey('gateway-device-list', $config['positions']);
        $this->assertArrayHasKey('gateway-device-list', $config['sizes']);
    }

    public function test_get_available_widgets_for_global_dashboard()
    {
        $widgets = $this->configService->getAvailableWidgets('global');

        $this->assertIsArray($widgets);
        $this->assertArrayHasKey('system-overview', $widgets);
        $this->assertArrayHasKey('cross-gateway-alerts', $widgets);
        $this->assertArrayHasKey('name', $widgets['system-overview']);
        $this->assertArrayHasKey('description', $widgets['system-overview']);
        $this->assertArrayHasKey('category', $widgets['system-overview']);
    }

    public function test_get_available_widgets_for_gateway_dashboard()
    {
        $widgets = $this->configService->getAvailableWidgets('gateway');

        $this->assertIsArray($widgets);
        $this->assertArrayHasKey('gateway-device-list', $widgets);
        $this->assertArrayHasKey('real-time-readings', $widgets);
        $this->assertArrayHasKey('name', $widgets['gateway-device-list']);
        $this->assertArrayHasKey('description', $widgets['gateway-device-list']);
        $this->assertArrayHasKey('category', $widgets['gateway-device-list']);
    }

    public function test_get_dashboard_summary()
    {
        $user = User::factory()->create();

        // Create configs with some hidden widgets
        $globalConfig = $this->configService->getUserDashboardConfig($user, 'global');
        $globalConfig->setWidgetVisibility('system-overview', false);

        $gatewayConfig = $this->configService->getUserDashboardConfig($user, 'gateway');
        $gatewayConfig->setWidgetVisibility('gateway-device-list', false);
        $gatewayConfig->setWidgetVisibility('real-time-readings', false);

        $summary = $this->configService->getDashboardSummary($user);

        $this->assertIsArray($summary);
        $this->assertArrayHasKey('global', $summary);
        $this->assertArrayHasKey('gateway', $summary);
        $this->assertEquals(3, $summary['global']['visible_widgets']); // 4 total - 1 hidden
        $this->assertEquals(1, $summary['global']['hidden_widgets']);
        $this->assertEquals(2, $summary['gateway']['visible_widgets']); // 4 total - 2 hidden
        $this->assertEquals(2, $summary['gateway']['hidden_widgets']);
    }

    public function test_export_dashboard_config()
    {
        $user = User::factory()->create();
        $config = $this->configService->getUserDashboardConfig($user, 'global');

        $exported = $this->configService->exportDashboardConfig($user, 'global');

        $this->assertIsArray($exported);
        $this->assertEquals('global', $exported['dashboard_type']);
        $this->assertEquals($user->id, $exported['user_id']);
        $this->assertEquals($config->widget_config, $exported['widget_config']);
        $this->assertEquals($config->layout_config, $exported['layout_config']);
        $this->assertArrayHasKey('exported_at', $exported);
    }

    public function test_import_dashboard_config()
    {
        $user = User::factory()->create();

        $importData = [
            'dashboard_type' => 'global',
            'widget_config' => [
                'visibility' => [
                    'system-overview' => false,
                    'cross-gateway-alerts' => true,
                ]
            ],
            'layout_config' => [
                'positions' => [
                    'system-overview' => ['row' => 5, 'col' => 5],
                ],
                'sizes' => [
                    'system-overview' => ['width' => 8, 'height' => 10],
                ]
            ],
        ];

        $config = $this->configService->importDashboardConfig($user, $importData);

        $this->assertInstanceOf(UserDashboardConfig::class, $config);
        $this->assertFalse($config->getWidgetVisibility('system-overview'));
        $this->assertTrue($config->getWidgetVisibility('cross-gateway-alerts'));
        $this->assertEquals(['row' => 5, 'col' => 5], $config->getWidgetPosition('system-overview'));
        $this->assertEquals(['width' => 8, 'height' => 10], $config->getWidgetSize('system-overview'));
    }

    public function test_import_dashboard_config_with_invalid_data_throws_exception()
    {
        $user = User::factory()->create();

        $invalidImportData = [
            'dashboard_type' => 'invalid',
            'widget_config' => 'not_an_array',
        ];

        $this->expectException(ValidationException::class);
        $this->configService->importDashboardConfig($user, $invalidImportData);
    }

    public function test_import_dashboard_config_filters_invalid_widgets()
    {
        $user = User::factory()->create();

        $importData = [
            'dashboard_type' => 'global',
            'widget_config' => [
                'visibility' => [
                    'system-overview' => true,
                    'invalid-widget' => true, // This should be filtered out
                ]
            ],
            'layout_config' => [
                'positions' => [
                    'system-overview' => ['row' => 1, 'col' => 1],
                    'invalid-widget' => ['row' => 2, 'col' => 2], // This should be filtered out
                ],
                'sizes' => [
                    'system-overview' => ['width' => 6, 'height' => 4],
                    'invalid-widget' => ['width' => 4, 'height' => 4], // This should be filtered out
                ]
            ],
        ];

        $config = $this->configService->importDashboardConfig($user, $importData);

        $this->assertTrue($config->getWidgetVisibility('system-overview'));
        $this->assertTrue($config->getWidgetVisibility('invalid-widget')); // Should return default true since it's not in config
        $this->assertArrayNotHasKey('invalid-widget', $config->widget_config['visibility']);
        $this->assertArrayNotHasKey('invalid-widget', $config->layout_config['positions']);
        $this->assertArrayNotHasKey('invalid-widget', $config->layout_config['sizes']);
    }
}