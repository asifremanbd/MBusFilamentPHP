<?php

namespace Tests\Feature;

use App\Models\Gateway;
use App\Models\User;
use App\Services\RTUQueryOptimizationService;
use App\Services\RTUCacheService;
use App\Services\RTUDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Mocks\RTUGatewayMock;

class RTUOptimizationValidationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected array $rtuGateways;
    protected RTUQueryOptimizationService $optimizationService;
    protected RTUCacheService $cacheService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->rtuGateways = Gateway::factory()->rtu()->count(5)->create()->toArray();
        $this->optimizationService = app(RTUQueryOptimizationService::class);
        $this->cacheService = app(RTUCacheService::class);
    }

    /** @test */
    public function it_validates_database_query_optimization()
    {
        DB::enableQueryLog();
        
        $gateway = Gateway::find($this->rtuGateways[0]['id']);
        
        // Test optimized gateway retrieval
        $optimizedGateway = $this->optimizationService->getRTUGatewayWithData($gateway->id);
        
        $queries = DB::getQueryLog();
        
        // Should use efficient queries with proper joins and selects
        $this->assertNotNull($optimizedGateway);
        $this->assertLessThan(5, count($queries), 'Should use minimal queries with proper optimization');
        
        // Verify eager loading is working
        $this->assertTrue($optimizedGateway->relationLoaded('devices'));
        $this->assertTrue($optimizedGateway->relationLoaded('alerts'));
    }

    /** @test */
    public function it_validates_cache_layer_effectiveness()
    {
        $gateway = Gateway::find($this->rtuGateways[0]['id']);
        
        // Clear any existing cache
        $this->cacheService->invalidateGatewayCache($gateway);
        
        // First call should miss cache
        $startTime = microtime(true);
        $systemHealth1 = $this->cacheService->getSystemHealth($gateway);
        $firstCallTime = microtime(true) - $startTime;
        
        $this->assertNull($systemHealth1, 'First call should miss cache');
        
        // Cache some data
        $testData = RTUGatewayMock::getSystemHealthData($gateway);
        $this->cacheService->cacheSystemHealth($gateway, $testData);
        
        // Second call should hit cache
        $startTime = microtime(true);
        $systemHealth2 = $this->cacheService->getSystemHealth($gateway);
        $secondCallTime = microtime(true) - $startTime;
        
        $this->assertNotNull($systemHealth2, 'Second call should hit cache');
        $this->assertEquals($testData, $systemHealth2);
        $this->assertLessThan($firstCallTime, $secondCallTime, 'Cached call should be faster');
    }

    /** @test */
    public function it_validates_bulk_operations_performance()
    {
        $gateways = Gateway::whereIn('id', array_column($this->rtuGateways, 'id'))->get();
        
        // Test bulk data update
        $bulkData = [];
        foreach ($gateways as $gateway) {
            $bulkData[$gateway->id] = [
                'cpu_load' => rand(10, 90),
                'memory_usage' => rand(20, 80),
                'communication_status' => 'online'
            ];
        }
        
        $startTime = microtime(true);
        $this->optimizationService->bulkUpdateRTUData($bulkData);
        $bulkUpdateTime = microtime(true) - $startTime;
        
        // Verify bulk update was efficient
        $this->assertLessThan(1.0, $bulkUpdateTime, 'Bulk update should complete in under 1 second');
        
        // Verify data was updated
        foreach ($gateways as $gateway) {
            $gateway->refresh();
            $this->assertEquals($bulkData[$gateway->id]['cpu_load'], $gateway->cpu_load);
            $this->assertEquals($bulkData[$gateway->id]['memory_usage'], $gateway->memory_usage);
        }
    }

    /** @test */
    public function it_validates_optimized_alert_retrieval()
    {
        $gateway = Gateway::find($this->rtuGateways[0]['id']);
        
        // Test optimized alert retrieval with filters
        $filters = [
            'severity' => ['critical', 'warning'],
            'time_range' => '24h'
        ];
        
        $startTime = microtime(true);
        $alerts = $this->optimizationService->getOptimizedAlerts($gateway, $filters);
        $retrievalTime = microtime(true) - $startTime;
        
        // Should be fast and return structured data
        $this->assertLessThan(0.5, $retrievalTime, 'Alert retrieval should be under 0.5 seconds');
        $this->assertIsArray($alerts);
        $this->assertArrayHasKey('critical_count', $alerts);
        $this->assertArrayHasKey('warning_count', $alerts);
        $this->assertArrayHasKey('grouped_alerts', $alerts);
    }

    /** @test */
    public function it_validates_trend_data_aggregation_performance()
    {
        $gateway = Gateway::find($this->rtuGateways[0]['id']);
        
        // Test optimized trend data retrieval
        $metrics = ['signal_strength', 'cpu_load', 'memory_usage'];
        
        $startTime = microtime(true);
        $trendData = $this->optimizationService->getOptimizedTrendData($gateway, '24h', $metrics);
        $trendTime = microtime(true) - $startTime;
        
        // Should use database aggregation for performance
        $this->assertLessThan(1.0, $trendTime, 'Trend data retrieval should be under 1 second');
        $this->assertIsArray($trendData);
        $this->assertArrayHasKey('has_data', $trendData);
        $this->assertArrayHasKey('metrics', $trendData);
    }

    /** @test */
    public function it_validates_cache_warming_strategy()
    {
        $gateway = Gateway::find($this->rtuGateways[0]['id']);
        $rtuDataService = app(RTUDataService::class);
        
        // Clear cache first
        $this->cacheService->invalidateGatewayCache($gateway);
        
        // Warm up cache
        $startTime = microtime(true);
        $this->cacheService->warmUpGatewayCache($gateway, $rtuDataService);
        $warmupTime = microtime(true) - $startTime;
        
        // Verify cache warming was efficient
        $this->assertLessThan(2.0, $warmupTime, 'Cache warming should complete in under 2 seconds');
        
        // Verify data is now cached
        $this->assertNotNull($this->cacheService->getSystemHealth($gateway));
        $this->assertNotNull($this->cacheService->getNetworkStatus($gateway));
        $this->assertNotNull($this->cacheService->getIOStatus($gateway));
    }

    /** @test */
    public function it_validates_concurrent_access_optimization()
    {
        $gateway = Gateway::find($this->rtuGateways[0]['id']);
        $concurrentUsers = 10;
        
        // Pre-warm cache for optimal performance
        $this->cacheService->warmUpGatewayCache($gateway, app(RTUDataService::class));
        
        $startTime = microtime(true);
        $responses = [];
        
        // Simulate concurrent access
        for ($i = 0; $i < $concurrentUsers; $i++) {
            $user = User::factory()->create();
            $responses[] = $this->actingAs($user)
                ->get(route('dashboard.rtu', $gateway));
        }
        
        $totalTime = microtime(true) - $startTime;
        
        // Verify concurrent access performance
        $this->assertLessThan(3.0, $totalTime, 'Concurrent access should be handled efficiently');
        
        // Verify all requests succeeded
        foreach ($responses as $response) {
            $response->assertStatus(200);
        }
    }

    /** @test */
    public function it_validates_memory_usage_optimization()
    {
        $memoryBefore = memory_get_usage(true);
        
        $gateway = Gateway::find($this->rtuGateways[0]['id']);
        
        // Perform multiple operations that could cause memory leaks
        for ($i = 0; $i < 20; $i++) {
            $this->optimizationService->getRTUGatewayWithData($gateway->id);
            $this->cacheService->getSystemHealth($gateway);
            $this->cacheService->getNetworkStatus($gateway);
            $this->cacheService->getIOStatus($gateway);
            
            // Force garbage collection
            if ($i % 5 === 0) {
                gc_collect_cycles();
            }
        }
        
        $memoryAfter = memory_get_usage(true);
        $memoryIncrease = $memoryAfter - $memoryBefore;
        
        // Memory usage should be reasonable
        $this->assertLessThan(20 * 1024 * 1024, $memoryIncrease, 'Memory increase should be under 20MB');
    }

    /** @test */
    public function it_validates_database_index_optimization()
    {
        // Test that required indexes exist and are being used
        $indexResults = $this->optimizationService->ensureOptimalIndexes();
        
        // Should either create indexes or confirm they exist
        $this->assertIsArray($indexResults);
        
        // Test query performance with indexes
        DB::enableQueryLog();
        
        $gateway = Gateway::find($this->rtuGateways[0]['id']);
        $this->optimizationService->getRTUGateways()->where('id', $gateway->id)->first();
        
        $queries = DB::getQueryLog();
        
        // Should use efficient queries
        $this->assertLessThan(3, count($queries), 'Should use minimal queries with proper indexing');
    }

    /** @test */
    public function it_validates_cache_invalidation_strategy()
    {
        $gateway = Gateway::find($this->rtuGateways[0]['id']);
        
        // Cache some data
        $testData = RTUGatewayMock::getSystemHealthData($gateway);
        $this->cacheService->cacheSystemHealth($gateway, $testData);
        
        // Verify data is cached
        $this->assertNotNull($this->cacheService->getSystemHealth($gateway));
        
        // Invalidate cache
        $this->cacheService->invalidateGatewayCache($gateway);
        
        // Verify cache is cleared
        $this->assertNull($this->cacheService->getSystemHealth($gateway));
    }

    /** @test */
    public function it_validates_real_time_update_performance()
    {
        $gateway = Gateway::find($this->rtuGateways[0]['id']);
        $updateCount = 50;
        
        $startTime = microtime(true);
        
        // Simulate rapid real-time updates
        for ($i = 0; $i < $updateCount; $i++) {
            $this->cacheService->cacheRealTimeUpdate($gateway, 'system_health', [
                'cpu_load' => rand(10, 90),
                'memory_usage' => rand(20, 80),
                'timestamp' => now()->toISOString()
            ]);
        }
        
        $updateTime = microtime(true) - $startTime;
        
        // Real-time updates should be very fast
        $this->assertLessThan(1.0, $updateTime, 'Real-time updates should complete in under 1 second');
        
        // Verify latest data is accessible
        $latestData = $this->cacheService->getRealTimeData($gateway, 'system_health');
        $this->assertNotNull($latestData);
        $this->assertArrayHasKey('cpu_load', $latestData);
    }

    /** @test */
    public function it_validates_performance_monitoring_capabilities()
    {
        // Test performance statistics collection
        $stats = $this->optimizationService->getPerformanceStats();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('slow_queries', $stats);
        $this->assertArrayHasKey('index_usage', $stats);
        $this->assertArrayHasKey('cache_hit_rate', $stats);
        $this->assertArrayHasKey('query_recommendations', $stats);
    }

    /** @test */
    public function it_validates_cache_statistics_accuracy()
    {
        $gateway = Gateway::find($this->rtuGateways[0]['id']);
        
        // Generate some cache activity
        for ($i = 0; $i < 10; $i++) {
            $testData = RTUGatewayMock::getSystemHealthData($gateway);
            $this->cacheService->cacheSystemHealth($gateway, $testData);
            $this->cacheService->getSystemHealth($gateway);
        }
        
        // Get cache statistics
        $stats = $this->cacheService->getCacheStats();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_keys', $stats);
        $this->assertArrayHasKey('hit_rate', $stats);
        $this->assertArrayHasKey('memory_usage', $stats);
        $this->assertArrayHasKey('key_breakdown', $stats);
    }
}