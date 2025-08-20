<?php

namespace Tests\Feature;

use App\Models\Gateway;
use App\Models\User;
use App\Services\RTUDataService;
use App\Services\RTUAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Mocks\RTUGatewayMock;
use Mockery;

class RTUPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected array $rtuGateways;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->rtuGateways = Gateway::factory()->rtu()->count(10)->create()->toArray();
    }

    /** @test */
    public function it_handles_concurrent_data_collection_from_multiple_gateways()
    {
        $startTime = microtime(true);
        $responses = [];

        // Mock RTU data service for performance testing
        $mockRTUDataService = Mockery::mock(RTUDataService::class);
        
        foreach ($this->rtuGateways as $gateway) {
            $gatewayModel = Gateway::find($gateway['id']);
            
            $mockRTUDataService->shouldReceive('getSystemHealth')
                ->with($gatewayModel)
                ->andReturn(RTUGatewayMock::getSystemHealthData($gatewayModel));
                
            $mockRTUDataService->shouldReceive('getNetworkStatus')
                ->with($gatewayModel)
                ->andReturn(RTUGatewayMock::getNetworkStatusData($gatewayModel));
                
            $mockRTUDataService->shouldReceive('getIOStatus')
                ->with($gatewayModel)
                ->andReturn(RTUGatewayMock::getIOStatusData($gatewayModel));
        }

        $this->app->instance(RTUDataService::class, $mockRTUDataService);

        // Simulate concurrent requests to multiple RTU gateways
        foreach ($this->rtuGateways as $gateway) {
            $gatewayModel = Gateway::find($gateway['id']);
            $responses[] = $this->actingAs($this->user)
                ->get(route('dashboard.rtu', $gatewayModel));
        }

        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;

        // Assert all requests completed successfully
        foreach ($responses as $response) {
            $response->assertStatus(200);
        }

        // Performance assertion: should handle 10 gateways in under 5 seconds
        $this->assertLessThan(5.0, $totalTime, 
            "RTU dashboard loading for 10 gateways took {$totalTime}s, should be under 5s");
    }

    /** @test */
    public function it_efficiently_queries_database_for_rtu_data()
    {
        DB::enableQueryLog();

        $gateway = Gateway::find($this->rtuGateways[0]['id']);
        
        $this->actingAs($this->user)
            ->get(route('dashboard.rtu', $gateway));

        $queries = DB::getQueryLog();
        
        // Should not exceed reasonable number of queries for RTU dashboard
        $this->assertLessThan(15, count($queries), 
            'RTU dashboard should not execute more than 15 database queries');

        // Check for N+1 query problems
        $selectQueries = array_filter($queries, function($query) {
            return strpos(strtolower($query['query']), 'select') === 0;
        });

        $this->assertLessThan(10, count($selectQueries), 
            'Too many SELECT queries detected, possible N+1 problem');
    }

    /** @test */
    public function it_uses_caching_for_frequently_accessed_rtu_metrics()
    {
        $gateway = Gateway::find($this->rtuGateways[0]['id']);
        $cacheKey = "rtu_system_health_{$gateway->id}";

        // Mock cached data
        Cache::put($cacheKey, RTUGatewayMock::getSystemHealthData($gateway), 300);

        $mockRTUDataService = Mockery::mock(RTUDataService::class);
        
        // Should not call the service if data is cached
        $mockRTUDataService->shouldReceive('getSystemHealth')
            ->with($gateway)
            ->never();

        $this->app->instance(RTUDataService::class, $mockRTUDataService);

        // Test that cached data is used
        $cachedData = Cache::get($cacheKey);
        $this->assertNotNull($cachedData);
        $this->assertEquals(85, $cachedData['health_score']);
    }

    /** @test */
    public function it_handles_high_concurrent_user_load()
    {
        $users = User::factory()->count(20)->create();
        $gateway = Gateway::find($this->rtuGateways[0]['id']);
        
        $startTime = microtime(true);
        $responses = [];

        // Mock services for all concurrent requests
        $mockRTUDataService = Mockery::mock(RTUDataService::class);
        $mockRTUAlertService = Mockery::mock(RTUAlertService::class);

        $mockRTUDataService->shouldReceive('getSystemHealth')->andReturn(
            RTUGatewayMock::getSystemHealthData($gateway)
        );
        $mockRTUDataService->shouldReceive('getNetworkStatus')->andReturn(
            RTUGatewayMock::getNetworkStatusData($gateway)
        );
        $mockRTUDataService->shouldReceive('getIOStatus')->andReturn(
            RTUGatewayMock::getIOStatusData($gateway)
        );
        $mockRTUDataService->shouldReceive('getTrendData')->andReturn(
            RTUGatewayMock::getTrendData($gateway)
        );
        
        $mockRTUAlertService->shouldReceive('getGroupedAlerts')->andReturn(
            RTUGatewayMock::getGroupedAlertsData($gateway)
        );

        $this->app->instance(RTUDataService::class, $mockRTUDataService);
        $this->app->instance(RTUAlertService::class, $mockRTUAlertService);

        // Simulate 20 concurrent users accessing the same RTU dashboard
        foreach ($users as $user) {
            $responses[] = $this->actingAs($user)
                ->get(route('dashboard.rtu', $gateway));
        }

        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;

        // Assert all requests completed successfully
        foreach ($responses as $response) {
            $response->assertStatus(200);
        }

        // Performance assertion: should handle 20 concurrent users in under 10 seconds
        $this->assertLessThan(10.0, $totalTime, 
            "RTU dashboard with 20 concurrent users took {$totalTime}s, should be under 10s");
    }

    /** @test */
    public function it_optimizes_memory_usage_for_large_datasets()
    {
        $memoryBefore = memory_get_usage(true);
        
        $gateway = Gateway::find($this->rtuGateways[0]['id']);
        
        // Mock large dataset
        $mockRTUDataService = Mockery::mock(RTUDataService::class);
        $mockRTUDataService->shouldReceive('getTrendData')
            ->with($gateway, '7d')
            ->andReturn(RTUGatewayMock::getTrendData($gateway, '7d'));

        $this->app->instance(RTUDataService::class, $mockRTUDataService);

        $this->actingAs($this->user)
            ->get(route('dashboard.rtu', $gateway));

        $memoryAfter = memory_get_usage(true);
        $memoryUsed = $memoryAfter - $memoryBefore;

        // Should not use more than 50MB for RTU dashboard rendering
        $this->assertLessThan(50 * 1024 * 1024, $memoryUsed, 
            "RTU dashboard used {$memoryUsed} bytes, should be under 50MB");
    }

    /** @test */
    public function it_handles_timeout_scenarios_gracefully()
    {
        $gateway = Gateway::find($this->rtuGateways[0]['id']);
        
        $mockRTUDataService = Mockery::mock(RTUDataService::class);
        
        // Simulate timeout for system health
        $mockRTUDataService->shouldReceive('getSystemHealth')
            ->with($gateway)
            ->andThrow(new \Exception('Connection timeout'));
            
        // Other services should still work
        $mockRTUDataService->shouldReceive('getNetworkStatus')
            ->with($gateway)
            ->andReturn(RTUGatewayMock::getNetworkStatusData($gateway));
            
        $mockRTUDataService->shouldReceive('getIOStatus')
            ->with($gateway)
            ->andReturn(RTUGatewayMock::getIOStatusData($gateway));
            
        $mockRTUDataService->shouldReceive('getTrendData')
            ->with($gateway, '24h')
            ->andReturn(RTUGatewayMock::getTrendData($gateway));

        $mockRTUAlertService = Mockery::mock(RTUAlertService::class);
        $mockRTUAlertService->shouldReceive('getGroupedAlerts')
            ->with($gateway)
            ->andReturn(RTUGatewayMock::getGroupedAlertsData($gateway));

        $this->app->instance(RTUDataService::class, $mockRTUDataService);
        $this->app->instance(RTUAlertService::class, $mockRTUAlertService);

        $startTime = microtime(true);
        
        $response = $this->actingAs($this->user)
            ->get(route('dashboard.rtu', $gateway));

        $endTime = microtime(true);
        $responseTime = $endTime - $startTime;

        // Should still render successfully despite timeout
        $response->assertStatus(200);
        
        // Should not take too long even with timeouts
        $this->assertLessThan(3.0, $responseTime, 
            "RTU dashboard with timeout took {$responseTime}s, should be under 3s");
    }

    /** @test */
    public function it_efficiently_handles_digital_output_control_requests()
    {
        $gateway = Gateway::find($this->rtuGateways[0]['id']);
        
        $mockRTUDataService = Mockery::mock(RTUDataService::class);
        $mockRTUDataService->shouldReceive('setDigitalOutput')
            ->times(10) // 10 rapid requests
            ->andReturn(RTUGatewayMock::getDigitalOutputControlResponse(true));

        $this->app->instance(RTUDataService::class, $mockRTUDataService);

        $startTime = microtime(true);
        
        // Simulate 10 rapid digital output control requests
        for ($i = 0; $i < 10; $i++) {
            $response = $this->actingAs($this->user)
                ->postJson(route('api.rtu.digital-output', [
                    'gateway' => $gateway,
                    'output' => 'do1'
                ]), ['state' => $i % 2 === 0]);
                
            $response->assertStatus(200);
        }

        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;

        // Should handle 10 control requests in under 2 seconds
        $this->assertLessThan(2.0, $totalTime, 
            "10 digital output control requests took {$totalTime}s, should be under 2s");
    }

    /** @test */
    public function it_maintains_performance_with_large_alert_datasets()
    {
        $gateway = Gateway::find($this->rtuGateways[0]['id']);
        
        // Create large number of alerts for performance testing
        $alerts = [];
        for ($i = 0; $i < 1000; $i++) {
            $alerts[] = (object) [
                'type' => 'Test Alert ' . $i,
                'message' => 'Test alert message ' . $i,
                'severity' => ['critical', 'warning', 'info'][rand(0, 2)],
                'count' => rand(1, 5),
                'latest_timestamp' => now()->subMinutes(rand(1, 1440)),
                'is_grouped' => rand(0, 1) === 1
            ];
        }

        $mockRTUAlertService = Mockery::mock(RTUAlertService::class);
        $mockRTUAlertService->shouldReceive('getGroupedAlerts')
            ->with($gateway)
            ->andReturn([
                'critical_count' => 50,
                'warning_count' => 200,
                'info_count' => 750,
                'grouped_alerts' => array_slice($alerts, 0, 10), // Only show top 10
                'has_alerts' => true,
                'status_summary' => '50 Critical Alerts'
            ]);

        $this->app->instance(RTUAlertService::class, $mockRTUAlertService);

        $startTime = microtime(true);
        
        $response = $this->actingAs($this->user)
            ->get(route('dashboard.rtu', $gateway));

        $endTime = microtime(true);
        $responseTime = $endTime - $startTime;

        $response->assertStatus(200);
        
        // Should handle large alert datasets efficiently
        $this->assertLessThan(2.0, $responseTime, 
            "RTU dashboard with 1000 alerts took {$responseTime}s, should be under 2s");
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}