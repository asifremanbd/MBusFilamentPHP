<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Models\User;
use App\Models\Gateway;
use App\Models\Device;
use App\Models\UserDeviceAssignment;
use App\Widgets\BaseWidget;
use App\Services\UserPermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\CreatesApplication;

class BaseWidgetTest extends TestCase
{
    use CreatesApplication, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createApplication();
    }

    public function test_widget_renders_successfully_with_permission()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $widget = new TestWidget($user);

        $result = $widget->render();

        $this->assertEquals('success', $result['status']);
        $this->assertEquals('test-widget', $result['widget_type']);
        $this->assertEquals('Test Widget', $result['widget_name']);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('metadata', $result);
    }

    public function test_widget_returns_unauthorized_without_permission()
    {
        $user = User::factory()->create(['role' => 'operator']);
        $widget = new TestWidget($user);

        $result = $widget->render();

        $this->assertEquals('unauthorized', $result['status']);
        $this->assertEquals('You do not have permission to access this widget', $result['message']);
        $this->assertEmpty($result['data']);
    }

    public function test_widget_caching_works()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $widget = new TestWidget($user);

        // First call should not be from cache
        $result1 = $widget->render();
        $this->assertFalse($result1['metadata']['from_cache']);

        // Second call should be from cache
        $result2 = $widget->render();
        $this->assertTrue($result2['metadata']['from_cache']);

        // Data should be the same
        $this->assertEquals($result1['data'], $result2['data']);
    }

    public function test_widget_cache_can_be_disabled()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $widget = new TestWidget($user);
        $widget->disableCache();

        $result1 = $widget->render();
        $result2 = $widget->render();

        $this->assertFalse($result1['metadata']['from_cache']);
        $this->assertFalse($result2['metadata']['from_cache']);
    }

    public function test_widget_cache_can_be_cleared()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $widget = new TestWidget($user);

        // First call to populate cache
        $widget->render();

        // Clear cache
        $widget->clearCache();

        // Next call should not be from cache
        $result = $widget->render();
        $this->assertFalse($result['metadata']['from_cache']);
    }

    public function test_widget_handles_errors_gracefully()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $widget = new ErrorWidget($user);

        $result = $widget->render();

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('Widget failed to load', $result['message']);
        $this->assertArrayHasKey('retry_available', $result['metadata']);
        $this->assertTrue($result['metadata']['retry_available']);
    }

    public function test_widget_config_can_be_updated()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $widget = new TestWidget($user, ['initial' => 'config']);

        $this->assertEquals(['initial' => 'config'], $widget->getConfig());

        $widget->setConfig(['updated' => 'config']);
        $this->assertEquals(['initial' => 'config', 'updated' => 'config'], $widget->getConfig());
    }

    public function test_widget_metadata_is_correct()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $widget = new TestWidget($user);

        $metadata = $widget->getMetadata();

        $this->assertEquals('test-widget', $metadata['widget_type']);
        $this->assertEquals('Test Widget', $metadata['widget_name']);
        $this->assertEquals('A test widget for unit testing', $metadata['widget_description']);
        $this->assertTrue($metadata['cache_enabled']);
        $this->assertEquals(300, $metadata['cache_ttl']);
        $this->assertFalse($metadata['requires_gateway']);
    }

    public function test_widget_cache_key_is_unique()
    {
        $user1 = User::factory()->create(['role' => 'admin']);
        $user2 = User::factory()->create(['role' => 'admin']);
        
        $widget1 = new TestWidget($user1);
        $widget2 = new TestWidget($user2);

        $cacheKey1 = $this->getPrivateProperty($widget1, 'getCacheKey');
        $cacheKey2 = $this->getPrivateProperty($widget2, 'getCacheKey');

        $this->assertNotEquals($cacheKey1, $cacheKey2);
    }

    public function test_widget_permission_config_includes_gateway_id()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $gateway = Gateway::factory()->create();
        $widget = new TestWidget($user, [], $gateway->id);

        $permissionConfig = $this->getPrivateMethod($widget, 'getPermissionConfig');

        $this->assertEquals($gateway->id, $permissionConfig['gateway_id']);
    }

    public function test_widget_permission_config_includes_device_ids()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $deviceIds = [1, 2, 3];
        $widget = new TestWidget($user, ['device_ids' => $deviceIds]);

        $permissionConfig = $this->getPrivateMethod($widget, 'getPermissionConfig');

        $this->assertEquals($deviceIds, $permissionConfig['device_ids']);
    }

    /**
     * Helper method to access private methods
     */
    private function getPrivateMethod($object, $methodName)
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invoke($object);
    }

    /**
     * Helper method to access private properties
     */
    private function getPrivateProperty($object, $methodName)
    {
        return $this->getPrivateMethod($object, $methodName);
    }
}

/**
 * Test widget implementation for testing
 */
class TestWidget extends BaseWidget
{
    protected function getData(): array
    {
        return [
            'test_data' => 'test_value',
            'timestamp' => now()->toISOString(),
        ];
    }

    protected function getWidgetType(): string
    {
        return 'test-widget';
    }

    protected function getWidgetName(): string
    {
        return 'Test Widget';
    }

    protected function getWidgetDescription(): string
    {
        return 'A test widget for unit testing';
    }

    protected function hasPermission(): bool
    {
        return $this->user->isAdmin();
    }
}

/**
 * Error widget implementation for testing error handling
 */
class ErrorWidget extends BaseWidget
{
    protected function getData(): array
    {
        throw new \Exception('Test error');
    }

    protected function getWidgetType(): string
    {
        return 'error-widget';
    }

    protected function getWidgetName(): string
    {
        return 'Error Widget';
    }

    protected function getWidgetDescription(): string
    {
        return 'A widget that always throws an error';
    }

    protected function hasPermission(): bool
    {
        return true;
    }

    protected function getFallbackData(): array
    {
        return ['fallback' => 'data'];
    }
}