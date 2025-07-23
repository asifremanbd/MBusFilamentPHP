<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Gateway;
use App\Models\Device;
use App\Models\Reading;
use App\Models\Alert;
use App\Models\UserGatewayAssignment;
use App\Models\UserDeviceAssignment;
use App\Services\UserPermissionService;
use App\Services\DashboardConfigService;
use App\Services\WidgetFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected $gateways;
    protected $devices;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'operator']);
        
        // Create large dataset for performance testing
        $this->createLargeDataset();
    }

    protected function createLargeDataset(): void
    {
        // Create 50 gateways
        $this->gateways = Gateway::factory()->count(50)->create();
        
        // Create 500 devices (10 per gateway)
        $this->devices = collect();
        foreach ($this->gateways as $gateway) {
            $gatewayDevices = Device::factory()->count(10)->create([
                'gateway_id' => $gateway->id
            ]);
            $this->devices = $this->devices->merge($gatewayDevices);
        }

        // Assign user to all gateways and devices
        foreach ($this->gateways as $gateway) {
            UserGatewayAssignment::create([
                'user_id' => $this->user->id,
                'gateway_id' => $gateway->id
            ]);
        }

        foreach ($this->devices as $device) {
            UserDeviceAssignment::create([
                'user_id' => $this->user->id,
                'device_id' => $device->id
            ]);
        }

        // Create readings for performance testing
        $this->createReadingsData();
        
        // Create alerts for performance testing
        $this->createAlertsData();
    }

    protected function createReadingsData(): void
    {
        // Create 10,000 readings across all devices
        $readingsData = [];
        $deviceIds = $this->devices->pluck('id')->toArray();
        
        for ($i = 0; $i < 10000; $i++) {
            $readingsData[] = [
                'device_id' => $deviceIds[array_rand($deviceIds)],
                'parameter_name' => 'voltage',
                'value' => rand(220, 240),
                'unit' => 'V',
                'timestamp' => now()->subMinutes(rand(1, 1440)),
                'created_at' => now(),
                'updated_at' => now()
            ];
        }

        // Bulk insert for better performance
        Reading::insert($readingsData);
    }

    protected function createAlertsData(): void
    {
        // Create 1,000 alerts
        $alertsData = [];
        $deviceIds = $this->devices->pluck('id')->toArray();
        
        for ($i = 0; $i < 1000; $i++) {
            $alertsData[] = [
                'device_id' => $deviceIds[array_rand($deviceIds)],
                'parameter_name' => 'voltage',
                'value' => rand(180, 260),
                'threshold_min' => 200,
                'threshold_max' => 250,
                'severity' => ['info', 'warning', 'critical'][array_rand(['info', 'warning', 'critical'])],
                'message' => 'Test alert message',
                'resolved' => rand(0, 1),
                'timestamp' => now()->subMinutes(rand(1, 1440)),
                'created_at' => now(),
                'updated_at' => now()
            ];
        }

        Alert::insert($alertsData);
    }

    /** @test */
    public function permission_service_performs_well_with_large_dataset()
    {
        $permissionService = app(UserPermissionService::class);

        // Measure performance of getting authorized gateways
        $start = microtime(true);
        $gateways = $permissionService->getAuthorizedGateways($this->user->id);
        $gatewayTime = microtime(true) - $start;

        // Should complete within reasonable time (< 100ms)
        $this->assertLessThan(0.1, $gatewayTime, 'Gateway permission check took too long');
        $this->assertCount(50, $gateways);

        // Measure performance of getting authorized devices
        $start = microtime(true);
        $devices = $permissionService->getAuthorizedDevices($this->user->id);
        $deviceTime = microtime(true) - $start;

        // Should complete within reasonable time (< 200ms)
        $this->assertLessThan(0.2, $deviceTime, 'Device permission check took too long');
        $this->assertCount(500, $devices);
    }

    /** @test */
    public function dashboard_config_service_performs_well()
    {
        $configService = app(DashboardConfigService::class);

        // Measure performance of getting dashboard config
        $start = microtime(true);
        $config = $configService->getUserDashboardConfig($this->user, 'global');
        $configTime = microtime(true) - $start;

        // Should complete within reasonable time (< 50ms)
        $this->assertLessThan(0.05, $configTime, 'Dashboard config retrieval took too long');
        $this->assertNotNull($config);
    }

    /** @test */
    public function widget_factory_performs_well_with_large_dataset()
    {
        $widgetFactory = app(WidgetFactory::class);

        // Measure performance of getting authorized widgets
        $start = microtime(true);
        $widgets = $widgetFactory->getAuthorizedWidgets($this->user, 'global');
        $widgetTime = microtime(true) - $start;

        // Should complete within reasonable time (< 100ms)
        $this->assertLessThan(0.1, $widgetTime, 'Widget authorization took too long');
        $this->assertNotEmpty($widgets);
    }

    /** @test */
    public function dashboard_api_endpoints_perform_well()
    {
        // Test gateways API performance
        $start = microtime(true);
        $response = $this->actingAs($this->user)
            ->getJson('/api/dashboard/gateways');
        $apiTime = microtime(true) - $start;

        $response->assertStatus(200);
        // API should respond within reasonable time (< 500ms)
        $this->assertLessThan(0.5, $apiTime, 'Gateways API took too long');

        $gateways = $response->json('gateways');
        $this->assertCount(50, $gateways);
    }

    /** @test */
    public function database_queries_are_optimized()
    {
        // Enable query logging
        DB::enableQueryLog();

        $permissionService = app(UserPermissionService::class);
        
        // Get authorized gateways
        $gateways = $permissionService->getAuthorizedGateways($this->user->id);
        
        $queries = DB::getQueryLog();
        
        // Should use minimal number of queries (ideally 1-2)
        $this->assertLessThanOrEqual(3, count($queries), 'Too many database queries for gateway permissions');
        
        // Clear query log
        DB::flushQueryLog();
        
        // Get authorized devices
        $devices = $permissionService->getAuthorizedDevices($this->user->id);
        
        $queries = DB::getQueryLog();
        
        // Should use minimal number of queries
        $this->assertLessThanOrEqual(3, count($queries), 'Too many database queries for device permissions');
    }

    /** @test */
    public function caching_improves_performance_significantly()
    {
        $permissionService = app(UserPermissionService::class);

        // Clear cache
        Cache::flush();

        // First call (no cache)
        $start = microtime(true);
        $gateways1 = $permissionService->getAuthorizedGateways($this->user->id);
        $uncachedTime = microtime(true) - $start;

        // Second call (with cache)
        $start = microtime(true);
        $gateways2 = $permissionService->getAuthorizedGateways($this->user->id);
        $cachedTime = microtime(true) - $start;

        // Cached call should be significantly faster
        $this->assertLessThan($uncachedTime * 0.5, $cachedTime, 'Cache did not improve performance significantly');
        $this->assertEquals($gateways1->count(), $gateways2->count());
    }

    /** @test */
    public function concurrent_requests_perform_well()
    {
        // Simulate concurrent requests by making multiple API calls
        $responses = [];
        $times = [];

        for ($i = 0; $i < 10; $i++) {
            $start = microtime(true);
            $response = $this->actingAs($this->user)
                ->getJson('/api/dashboard/gateways');
            $times[] = microtime(true) - $start;
            $responses[] = $response;
        }

        // All requests should succeed
        foreach ($responses as $response) {
            $response->assertStatus(200);
        }

        // Average response time should be reasonable
        $averageTime = array_sum($times) / count($times);
        $this->assertLessThan(0.5, $averageTime, 'Average response time too high under concurrent load');
    }

    /** @test */
    public function memory_usage_is_reasonable()
    {
        $initialMemory = memory_get_usage();

        $permissionService = app(UserPermissionService::class);
        
        // Load large dataset
        $gateways = $permissionService->getAuthorizedGateways($this->user->id);
        $devices = $permissionService->getAuthorizedDevices($this->user->id);

        $finalMemory = memory_get_usage();
        $memoryUsed = $finalMemory - $initialMemory;

        // Memory usage should be reasonable (< 50MB for this dataset)
        $this->assertLessThan(50 * 1024 * 1024, $memoryUsed, 'Memory usage too high');
    }

    /** @test */
    public function widget_rendering_performs_well()
    {
        $widgetFactory = app(WidgetFactory::class);

        // Test system overview widget performance
        $start = microtime(true);
        $widget = $widgetFactory->create('system-overview', $this->user, []);
        if ($widget) {
            $data = $widget->getData();
        }
        $renderTime = microtime(true) - $start;

        // Widget rendering should be fast (< 200ms)
        $this->assertLessThan(0.2, $renderTime, 'Widget rendering took too long');
    }

    /** @test */
    public function bulk_operations_perform_well()
    {
        // Test bulk widget configuration update
        $layoutUpdates = [];
        for ($i = 0; $i < 10; $i++) {
            $layoutUpdates[] = [
                'widget_id' => "widget-{$i}",
                'position' => ['row' => $i, 'col' => 0],
                'size' => ['width' => 6, 'height' => 4]
            ];
        }

        $start = microtime(true);
        $response = $this->actingAs($this->user)
            ->postJson('/api/dashboard/config/widget/layout', [
                'dashboard_type' => 'global',
                'layout_updates' => $layoutUpdates
            ]);
        $bulkTime = microtime(true) - $start;

        // Bulk operations should be efficient (< 300ms)
        $this->assertLessThan(0.3, $bulkTime, 'Bulk widget update took too long');
    }

    /** @test */
    public function cache_invalidation_is_efficient()
    {
        $permissionService = app(UserPermissionService::class);

        // Load data into cache
        $gateways = $permissionService->getAuthorizedGateways($this->user->id);

        // Measure cache invalidation time
        $start = microtime(true);
        
        // Add new gateway assignment (should invalidate cache)
        $newGateway = Gateway::factory()->create();
        UserGatewayAssignment::create([
            'user_id' => $this->user->id,
            'gateway_id' => $newGateway->id
        ]);
        
        $invalidationTime = microtime(true) - $start;

        // Cache invalidation should be fast (< 50ms)
        $this->assertLessThan(0.05, $invalidationTime, 'Cache invalidation took too long');

        // Verify cache was invalidated
        $updatedGateways = $permissionService->getAuthorizedGateways($this->user->id);
        $this->assertCount(51, $updatedGateways);
    }

    /** @test */
    public function dashboard_loading_with_large_dataset_performs_well()
    {
        // Test full dashboard page load
        $start = microtime(true);
        $response = $this->actingAs($this->user)
            ->get(route('dashboard.global'));
        $pageLoadTime = microtime(true) - $start;

        $response->assertStatus(200);
        
        // Full page load should be reasonable (< 1 second)
        $this->assertLessThan(1.0, $pageLoadTime, 'Dashboard page load took too long');
    }

    /** @test */
    public function error_handling_doesnt_impact_performance()
    {
        // Test performance when handling errors
        $start = microtime(true);
        
        // Make request that will result in error
        $response = $this->actingAs($this->user)
            ->get(route('dashboard.gateway', ['gateway' => 99999]));
        
        $errorTime = microtime(true) - $start;

        $response->assertStatus(404);
        
        // Error handling should be fast (< 100ms)
        $this->assertLessThan(0.1, $errorTime, 'Error handling took too long');
    }
}
"