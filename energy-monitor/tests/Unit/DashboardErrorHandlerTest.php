<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Models\User;
use App\Models\Gateway;
use App\Models\Device;
use App\Models\UserDeviceAssignment;
use App\Services\DashboardErrorHandler;
use App\Services\UserPermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\CreatesApplication;

class DashboardErrorHandlerTest extends TestCase
{
    use CreatesApplication, RefreshDatabase;

    protected DashboardErrorHandler $errorHandler;
    protected User $user;
    protected Gateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createApplication();
        
        $this->errorHandler = app(DashboardErrorHandler::class);
        $this->user = User::factory()->create(['role' => 'operator']);
        $this->gateway = Gateway::factory()->create();
    }

    public function test_permission_error_handling_returns_proper_response()
    {
        $exception = new AuthorizationException('Access denied');
        
        $response = $this->errorHandler->handlePermissionError($this->user, 'gateway_dashboard', $exception);
        
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_widget_error_handling_returns_fallback_data()
    {
        $exception = new \Exception('Database connection failed');
        
        $result = $this->errorHandler->handleWidgetError('system-overview', $exception);
        
        $this->assertEquals('error', $result['status']);
        $this->assertEquals('system-overview', $result['widget_id']);
        $this->assertArrayHasKey('fallback_data', $result);
        $this->assertArrayHasKey('retry_strategy', $result);
        $this->assertNotEmpty($result['fallback_data']);
    }

    public function test_network_error_handling_provides_retry_strategy()
    {
        $exception = new \Exception('Connection timeout');
        
        $result = $this->errorHandler->handleNetworkError('dashboard_load', $exception);
        
        $this->assertEquals('network_error', $result['status']);
        $this->assertArrayHasKey('retry_strategy', $result);
        $this->assertTrue($result['retry_strategy']['available']);
        $this->assertEquals('exponential_backoff', $result['retry_strategy']['strategy']);
    }

    public function test_database_error_handling_suggests_cached_data()
    {
        $exception = new \Exception('Database connection lost');
        
        $result = $this->errorHandler->handleDatabaseError('widget_load', $exception);
        
        $this->assertEquals('database_error', $result['status']);
        $this->assertArrayHasKey('cached_data', $result);
        $this->assertArrayHasKey('retry_strategy', $result);
    }

    public function test_error_classification_works_correctly()
    {
        $permissionError = new AuthorizationException('Access denied');
        $networkError = new \Exception('Connection refused');
        $databaseError = new \Exception('Database query failed');
        
        $permissionResult = $this->errorHandler->handleWidgetError('test-widget', $permissionError);
        $networkResult = $this->errorHandler->handleWidgetError('test-widget', $networkError);
        $databaseResult = $this->errorHandler->handleWidgetError('test-widget', $databaseError);
        
        $this->assertEquals('permission', $permissionResult['error_type']);
        $this->assertEquals('application', $networkResult['error_type']); // Generic classification
        $this->assertEquals('application', $databaseResult['error_type']); // Generic classification
    }

    public function test_error_severity_determination_works()
    {
        $criticalError = new AuthorizationException('Permission denied');
        $mediumError = new \Exception('Network timeout');
        
        $criticalResult = $this->errorHandler->handleWidgetError('test-widget', $criticalError);
        $mediumResult = $this->errorHandler->handleWidgetError('test-widget', $mediumError);
        
        $this->assertEquals('high', $criticalResult['error_severity']);
        $this->assertEquals('medium', $mediumResult['error_severity']);
    }

    public function test_widget_degradation_levels_work()
    {
        $networkError = new \Exception('Network timeout');
        $databaseError = new \Exception('Database connection failed');
        $permissionError = new AuthorizationException('Access denied');
        
        $networkResult = $this->errorHandler->degradeWidget('test-widget', $networkError);
        $databaseResult = $this->errorHandler->degradeWidget('test-widget', $databaseError);
        $permissionResult = $this->errorHandler->degradeWidget('test-widget', $permissionError);
        
        $this->assertEquals('degraded_partial', $networkResult['status']);
        $this->assertEquals('degraded_partial', $databaseResult['status']);
        $this->assertEquals('degraded_full', $permissionResult['status']);
    }

    public function test_fallback_actions_are_context_appropriate()
    {
        // User with no device access
        $userWithoutAccess = User::factory()->create(['role' => 'operator']);
        $exception = new AuthorizationException('No access');
        
        $response = $this->errorHandler->handlePermissionError($userWithoutAccess, 'gateway_dashboard', $exception);
        $viewData = $response->original;
        
        $this->assertEquals('contact_admin', $viewData['fallback_action']['action']);
        
        // User with some device access
        UserDeviceAssignment::create([
            'user_id' => $this->user->id,
            'device_id' => Device::factory()->create(['gateway_id' => $this->gateway->id])->id,
            'assigned_at' => now(),
        ]);
        
        $response2 = $this->errorHandler->handlePermissionError($this->user, 'gateway_dashboard', $exception);
        $viewData2 = $response2->original;
        
        $this->assertNotEquals('contact_admin', $viewData2['fallback_action']['action']);
    }

    public function test_error_codes_are_generated_correctly()
    {
        $exception = new \Exception('Test error');
        
        $result = $this->errorHandler->handleWidgetError('system-overview', $exception);
        
        $this->assertArrayHasKey('error_code', $result);
        $this->assertStringContainsString('APPLICATION_SYSTEM_OVERVIEW', $result['error_code']);
    }

    public function test_user_friendly_error_messages_are_provided()
    {
        $permissionError = new AuthorizationException('Access denied');
        $networkError = new \Exception('Connection timeout');
        $databaseError = new \Exception('Database query failed');
        
        $permissionResult = $this->errorHandler->handleWidgetError('test-widget', $permissionError);
        $networkResult = $this->errorHandler->handleWidgetError('test-widget', $networkError);
        $databaseResult = $this->errorHandler->handleWidgetError('test-widget', $databaseError);
        
        $this->assertStringContainsString('permission', $permissionResult['message']);
        $this->assertStringContainsString('unexpected error', $networkResult['message']);
        $this->assertStringContainsString('unexpected error', $databaseResult['message']);
    }

    public function test_api_error_responses_are_properly_formatted()
    {
        $exception = new \Exception('Test API error');
        
        $result = $this->errorHandler->createApiErrorResponse($exception, 'api_endpoint');
        
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertArrayHasKey('retry_info', $result);
        $this->assertEquals('application', $result['error']['type']);
        $this->assertEquals('medium', $result['error']['severity']);
    }

    public function test_fallback_data_contains_appropriate_structure()
    {
        $exception = new \Exception('Widget failed');
        
        $systemOverviewResult = $this->errorHandler->handleWidgetError('system-overview', $exception);
        $alertsResult = $this->errorHandler->handleWidgetError('cross-gateway-alerts', $exception);
        
        // System overview should have energy consumption data structure
        $this->assertArrayHasKey('total_energy_consumption', $systemOverviewResult['fallback_data']);
        $this->assertArrayHasKey('active_devices_count', $systemOverviewResult['fallback_data']);
        
        // Alerts widget should have alerts data structure
        $this->assertArrayHasKey('critical_alerts', $alertsResult['fallback_data']);
        $this->assertArrayHasKey('warning_alerts', $alertsResult['fallback_data']);
        
        // Both should have fallback metadata
        $this->assertArrayHasKey('_fallback', $systemOverviewResult['fallback_data']);
        $this->assertArrayHasKey('_fallback', $alertsResult['fallback_data']);
    }

    public function test_retry_strategies_vary_by_error_type()
    {
        $networkError = new \Exception('Network timeout');
        $permissionError = new AuthorizationException('Access denied');
        $databaseError = new \Exception('Database connection failed');
        
        $networkResult = $this->errorHandler->handleWidgetError('test-widget', $networkError);
        $permissionResult = $this->errorHandler->handleWidgetError('test-widget', $permissionError);
        $databaseResult = $this->errorHandler->handleWidgetError('test-widget', $databaseError);
        
        // Network errors should be retryable
        $this->assertTrue($networkResult['retry_strategy']['available']);
        
        // Permission errors should not be retryable
        $this->assertFalse($permissionResult['retry_strategy']['available']);
        
        // Database errors should be retryable but with different strategy
        $this->assertTrue($databaseResult['retry_strategy']['available']);
        $this->assertNotEquals(
            $networkResult['retry_strategy']['strategy'], 
            $databaseResult['retry_strategy']['strategy']
        );
    }
}