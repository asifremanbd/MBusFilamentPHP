<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Models\User;
use App\Models\Gateway;
use App\Models\Device;
use App\Models\UserDeviceAssignment;
use App\Services\WidgetFactory;
use App\Services\UserPermissionService;
use App\Widgets\BaseWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesApplication;

class WidgetFactoryTest extends TestCase
{
    use CreatesApplication, RefreshDatabase;

    protected WidgetFactory $widgetFactory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createApplication();
        $this->widgetFactory = app(WidgetFactory::class);
    }

    public function test_factory_can_register_and_create_widgets()
    {
        $user = User::factory()->create(['role' => 'admin']);
        
        // Register a test widget
        $this->widgetFactory->register('test-widget', TestFactoryWidget::class);

        $widget = $this->widgetFactory->create('test-widget', $user);

        $this->assertInstanceOf(TestFactoryWidget::class, $widget);
        $this->assertTrue($this->widgetFactory->isRegistered('test-widget'));
    }

    public function test_factory_returns_null_for_unknown_widget_type()
    {
        $user = User::factory()->create(['role' => 'admin']);

        $widget = $this->widgetFactory->create('unknown-widget', $user);

        $this->assertNull($widget);
    }

    public function test_factory_can_unregister_widgets()
    {
        $this->widgetFactory->register('test-widget', TestFactoryWidget::class);
        $this->assertTrue($this->widgetFactory->isRegistered('test-widget'));

        $this->widgetFactory->unregister('test-widget');
        $this->assertFalse($this->widgetFactory->isRegistered('test-widget'));
    }

    public function test_factory_can_create_multiple_widgets()
    {
        $user = User::factory()->create(['role' => 'admin']);
        
        $this->widgetFactory->register('test-widget-1', TestFactoryWidget::class);
        $this->widgetFactory->register('test-widget-2', TestFactoryWidget::class);

        $widgetSpecs = [
            ['type' => 'test-widget-1', 'config' => ['param1' => 'value1']],
            ['type' => 'test-widget-2', 'config' => ['param2' => 'value2']],
            ['type' => 'unknown-widget', 'config' => []], // This should be skipped
        ];

        $widgets = $this->widgetFactory->createMultiple($widgetSpecs, $user);

        $this->assertCount(2, $widgets);
        $this->assertArrayHasKey('test-widget-1', $widgets);
        $this->assertArrayHasKey('test-widget-2', $widgets);
        $this->assertArrayNotHasKey('unknown-widget', $widgets);
    }

    public function test_factory_can_get_authorized_widgets()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $gateway = Gateway::factory()->create();
        
        // Register test widgets
        $this->widgetFactory->register('system-overview', TestFactoryWidget::class);
        $this->widgetFactory->register('cross-gateway-alerts', TestFactoryWidget::class);

        $authorizedWidgets = $this->widgetFactory->getAuthorizedWidgets($user, 'global');

        $this->assertIsArray($authorizedWidgets);
        // Admin should have access to all widgets
        $this->assertNotEmpty($authorizedWidgets);
    }

    public function test_factory_can_render_multiple_widgets()
    {
        $user = User::factory()->create(['role' => 'admin']);
        
        $this->widgetFactory->register('test-widget-1', TestFactoryWidget::class);
        $this->widgetFactory->register('test-widget-2', TestFactoryWidget::class);

        $widget1 = $this->widgetFactory->create('test-widget-1', $user);
        $widget2 = $this->widgetFactory->create('test-widget-2', $user);

        $widgets = [
            'test-widget-1' => $widget1,
            'test-widget-2' => $widget2,
        ];

        $renderedWidgets = $this->widgetFactory->renderWidgets($widgets);

        $this->assertCount(2, $renderedWidgets);
        $this->assertArrayHasKey('test-widget-1', $renderedWidgets);
        $this->assertArrayHasKey('test-widget-2', $renderedWidgets);
        $this->assertEquals('success', $renderedWidgets['test-widget-1']['status']);
        $this->assertEquals('success', $renderedWidgets['test-widget-2']['status']);
    }

    public function test_factory_can_get_widgets_metadata()
    {
        $user = User::factory()->create(['role' => 'admin']);
        
        $this->widgetFactory->register('test-widget', TestFactoryWidget::class);
        $widget = $this->widgetFactory->create('test-widget', $user);

        $widgets = ['test-widget' => $widget];
        $metadata = $this->widgetFactory->getWidgetsMetadata($widgets);

        $this->assertCount(1, $metadata);
        $this->assertArrayHasKey('test-widget', $metadata);
        $this->assertArrayHasKey('widget_type', $metadata['test-widget']);
        $this->assertArrayHasKey('widget_name', $metadata['test-widget']);
    }

    public function test_factory_can_clear_widgets_cache()
    {
        $user = User::factory()->create(['role' => 'admin']);
        
        $this->widgetFactory->register('test-widget', TestFactoryWidget::class);
        $widget = $this->widgetFactory->create('test-widget', $user);

        // Render to populate cache
        $widget->render();

        $widgets = ['test-widget' => $widget];
        
        // This should not throw any exceptions
        $this->widgetFactory->clearWidgetsCache($widgets);
        
        // Verify cache was cleared by checking next render is not from cache
        $result = $widget->render();
        $this->assertFalse($result['metadata']['from_cache']);
    }

    public function test_factory_can_validate_widget_config()
    {
        $this->widgetFactory->register('test-widget', TestFactoryWidget::class);

        $validConfig = ['param1' => 'value1'];
        $this->assertTrue($this->widgetFactory->validateWidgetConfig('test-widget', $validConfig));

        $this->assertFalse($this->widgetFactory->validateWidgetConfig('unknown-widget', []));
    }

    public function test_factory_can_get_widget_performance_metrics()
    {
        $user = User::factory()->create(['role' => 'admin']);
        
        $this->widgetFactory->register('test-widget', TestFactoryWidget::class);
        $widget = $this->widgetFactory->create('test-widget', $user);

        $metrics = $this->widgetFactory->getWidgetPerformanceMetrics($widget);

        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('widget_type', $metrics);
        $this->assertArrayHasKey('execution_time', $metrics);
        $this->assertArrayHasKey('memory_usage', $metrics);
        $this->assertArrayHasKey('status', $metrics);
        $this->assertArrayHasKey('timestamp', $metrics);
        $this->assertEquals('test-widget', $metrics['widget_type']);
    }

    public function test_factory_gets_registered_widgets()
    {
        $this->widgetFactory->register('test-widget-1', TestFactoryWidget::class);
        $this->widgetFactory->register('test-widget-2', TestFactoryWidget::class);

        $registeredWidgets = $this->widgetFactory->getRegisteredWidgets();

        $this->assertIsArray($registeredWidgets);
        $this->assertContains('test-widget-1', $registeredWidgets);
        $this->assertContains('test-widget-2', $registeredWidgets);
    }

    public function test_factory_gets_widget_class()
    {
        $this->widgetFactory->register('test-widget', TestFactoryWidget::class);

        $widgetClass = $this->widgetFactory->getWidgetClass('test-widget');
        $this->assertEquals(TestFactoryWidget::class, $widgetClass);

        $unknownClass = $this->widgetFactory->getWidgetClass('unknown-widget');
        $this->assertNull($unknownClass);
    }
}

/**
 * Test widget implementation for factory testing
 */
class TestFactoryWidget extends BaseWidget
{
    protected function getData(): array
    {
        return [
            'factory_test_data' => 'factory_test_value',
            'config' => $this->config,
            'timestamp' => now()->toISOString(),
        ];
    }

    protected function getWidgetType(): string
    {
        return 'test-widget';
    }

    protected function getWidgetName(): string
    {
        return 'Test Factory Widget';
    }

    protected function getWidgetDescription(): string
    {
        return 'A test widget for factory testing';
    }

    protected function hasPermission(): bool
    {
        return $this->user->isAdmin();
    }
}