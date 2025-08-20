<?php

namespace Tests\Feature;

use App\Models\Gateway;
use App\Models\User;
use App\Services\RTUDataService;
use App\Services\RTUAlertService;
use App\Services\RTUCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Mocks\RTUGatewayMock;
use Mockery;
use GuzzleHttp\Promise;

class RTULoadTest extends TestCase
{
    use RefreshDatabase;

    protected array $users;
    protected array $rtuGateways;
    protected RTUDataService $rtuDataService;
    protected RTUAlertService $rtuAlertService;
    protected RTUCacheService $rtuCacheService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test users and gateways
        $this->users = User::factory()->count(50)->create()->toArray();
        $this->rtuGateways = Gateway::factory()->rtu()->count(20)->create()->toArray();
        
        // Mock services for load testing
        $this->setupMockServices();
    }

    /** @test */
    public function it_handles_high_concurrent_dashboard_access()
    {
        $concurrentUsers = 25;
        $gateway = Gateway::find($this->rtuGateways[0]['id']);
        
        $startTime = microtime(true);
        $promises = [];
        $responses = [];

        // Create concurrent requests
        for ($i = 0; $i < $concurrentUsers; $i++) {
            $user = User::find($this->users[$i]['id']);
            
            $promises[] = $this->actingAs($user)
                ->getAsync(route('dashboard.rtu', $gateway));
        }

        // Wait for all requests to complete
        $responses = Promise\settle($promises)->wait();
        
        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;

        // Analyze results
        $successCount = 0;
        $errorCount = 0;
        $responseTimes = [];

        foreach ($responses as $response) {
            if ($response['state'] === 'fulfilled') {
                $successCount++;
                $responseTimes[] = $response['value']->getTime();
            } else {
                $errorCount++;
            }
        }

        // Assertions
        $this->assertGreaterThan(20, $successCount, 'At least 80% of requests should succeed');
        $this->assertLessThan(5, $errorCount, 'Error rate should be less than 20%');
        $this->assertLessThan(15.0, $totalTime, "Load test took {$totalTime}s, should be under 15s");
        
        // Performance metrics
        $avgResponseTime = array_sum($responseTimes) / count($responseTimes);
        $this->assertLessThan(2.0, $avgResponseTime, "Average response time {$avgResponseTime}s should be under 2s");
    }

    /** @test */
    public function it_handles_concurrent_digital_output_control_requests()
    {
        $concurrentRequests = 20;
        $gateway = Gateway::find($this->rtuGateways[0]['id']);
        
        $startTime = microtime(true);
        $responses = [];

        // Create concurrent digital output control requests
        for ($i = 0; $i < $concurrentRequests; $i++) {
            $user = User::find($this->users[$i]['id']);
            $output = $i % 2 === 0 ? 'do1' : 'do2';
            $state = $i % 2 === 0;
            
            $responses[] = $this->actingAs($user)
                ->postJson(route('api.rtu.digital-output', [
                    'gateway' => $gateway,
                    'output' => $output
                ]), ['state' => $state]);
        }

        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;

        // Analyze results
        $successCount = 0;
        foreach ($responses as $response) {
            if ($response->getStatusCode() === 200) {
                $successCount++;
            }
        }

        $this->assertGreaterThan(15, $successCount, 'At least 75% of control requests should succeed');
        $this->assertLessThan(10.0, $totalTime, "Control requests took {$totalTime}s, should be under 10s");
    }

    /** @test */
    public function it_maintains_performance_with_multiple_gateways_and_users()
    {
        $usersPerGateway = 5;
        $gatewayCount = 10;
        
        $startTime = microtime(true);
        $responses = [];

        // Test multiple users accessing multiple gateways simultaneously
        for ($g = 0; $g < $gatewayCount; $g++) {
            $gateway = Gateway::find($this->rtuGateways[$g]['id']);
            
            for ($u = 0; $u < $usersPerGateway; $u++) {
                $userIndex = ($g * $usersPerGateway) + $u;
                $user = User::find($this->users[$userIndex]['id']);
                
                $responses[] = $this->actingAs($user)
                    ->get(route('dashboard.rtu', $gateway));
            }
        }

        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;

        // Analyze results
        $successCount = 0;
        foreach ($responses as $response) {
            if ($response->getStatusCode() === 200) {
                $successCount++;
            }
        }

        $expectedRequests = $gatewayCount * $usersPerGateway;
        $this->assertEquals($expectedRequests, count($responses));
        $this->assertGreaterThan($expectedRequests * 0.8, $successCount, 'At least 80% success rate expected');
        $this->assertLessThan(20.0, $totalTime, "Multi-gateway test took {$totalTime}s, should be under 20s");
    }

    /** @test */
    public function it_handles_database_connection_pool_under_load()
    {
        DB::enableQueryLog();
        
        $concurrentRequests = 30;
        $gateway = Gateway::find($this->rtuGateways[0]['id']);
        
        $responses = [];
        
        // Create high database load
        for ($i = 0; $i < $concurrentRequests; $i++) {
            $user = User::find($this->users[$i]['id']);
            
            $responses[] = $this->actingAs($user)
                ->get(route('dashboard.rtu', $gateway));
        }

        $queries = DB::getQueryLog();
        
        // Verify database performance
        $this->assertLessThan(500, count($queries), 'Should not exceed 500 total queries');
        
        // Check for connection pool exhaustion
        $successCount = 0;
        foreach ($responses as $response) {
            if ($response->getStatusCode() === 200) {
                $successCount++;
            }
        }
        
        $this->assertGreaterThan(25, $successCount, 'Should handle database load without connection issues');
    }

    /** @test */
    public function it_tests_cache_performance_under_load()
    {
        $gateway = Gateway::find($this->rtuGateways[0]['id']);
        
        // Pre-warm cache
        $this->rtuCacheService->warmUpGatewayCache($gateway, $this->rtuDataService);
        
        $cacheHits = 0;
        $cacheMisses = 0;
        $concurrentRequests = 40;
        
        $startTime = microtime(true);
        
        // Test cache performance under load
        for ($i = 0; $i < $concurrentRequests; $i++) {
            $user = User::find($this->users[$i]['id']);
            
            // Check if data is served from cache
            $systemHealth = $this->rtuCacheService->getSystemHealth($gateway);
            if ($systemHealth !== null) {
                $cacheHits++;
            } else {
                $cacheMisses++;
            }
            
            $response = $this->actingAs($user)
                ->get(route('dashboard.rtu', $gateway));
                
            $response->assertStatus(200);
        }
        
        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;
        
        // Cache performance assertions
        $hitRate = $cacheHits / ($cacheHits + $cacheMisses) * 100;
        $this->assertGreaterThan(80, $hitRate, "Cache hit rate {$hitRate}% should be above 80%");
        $this->assertLessThan(5.0, $totalTime, "Cached requests took {$totalTime}s, should be under 5s");
    }

    /** @test */
    public function it_tests_memory_usage_under_sustained_load()
    {
        $memoryBefore = memory_get_usage(true);
        $peakMemory = $memoryBefore;
        
        $gateway = Gateway::find($this->rtuGateways[0]['id']);
        $sustainedRequests = 100;
        
        // Sustained load test
        for ($i = 0; $i < $sustainedRequests; $i++) {
            $user = User::find($this->users[$i % count($this->users)]['id']);
            
            $response = $this->actingAs($user)
                ->get(route('dashboard.rtu', $gateway));
                
            $response->assertStatus(200);
            
            // Track peak memory usage
            $currentMemory = memory_get_usage(true);
            if ($currentMemory > $peakMemory) {
                $peakMemory = $currentMemory;
            }
            
            // Force garbage collection every 10 requests
            if ($i % 10 === 0) {
                gc_collect_cycles();
            }
        }
        
        $memoryAfter = memory_get_usage(true);
        $memoryIncrease = $memoryAfter - $memoryBefore;
        $peakIncrease = $peakMemory - $memoryBefore;
        
        // Memory usage assertions
        $this->assertLessThan(100 * 1024 * 1024, $memoryIncrease, 'Memory increase should be under 100MB');
        $this->assertLessThan(200 * 1024 * 1024, $peakIncrease, 'Peak memory increase should be under 200MB');
    }

    /** @test */
    public function it_tests_error_handling_under_load_with_service_failures()
    {
        $gateway = Gateway::find($this->rtuGateways[0]['id']);
        
        // Mock service failures for some requests
        $mockRTUDataService = Mockery::mock(RTUDataService::class);
        $mockRTUDataService->shouldReceive('getSystemHealth')
            ->andReturnUsing(function() {
                // Simulate 20% failure rate
                if (rand(1, 100) <= 20) {
                    throw new \Exception('Service temporarily unavailable');
                }
                return RTUGatewayMock::getSystemHealthData(new Gateway());
            });
            
        $mockRTUDataService->shouldReceive('getNetworkStatus')
            ->andReturn(RTUGatewayMock::getNetworkStatusData(new Gateway()));
            
        $mockRTUDataService->shouldReceive('getIOStatus')
            ->andReturn(RTUGatewayMock::getIOStatusData(new Gateway()));
            
        $mockRTUDataService->shouldReceive('getTrendData')
            ->andReturn(RTUGatewayMock::getTrendData(new Gateway()));

        $this->app->instance(RTUDataService::class, $mockRTUDataService);
        
        $concurrentRequests = 25;
        $responses = [];
        
        // Test error handling under load
        for ($i = 0; $i < $concurrentRequests; $i++) {
            $user = User::find($this->users[$i]['id']);
            
            $responses[] = $this->actingAs($user)
                ->get(route('dashboard.rtu', $gateway));
        }
        
        // Analyze error handling
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($responses as $response) {
            if ($response->getStatusCode() === 200) {
                $successCount++;
            } else {
                $errorCount++;
            }
        }
        
        // Should gracefully handle service failures
        $this->assertGreaterThan(15, $successCount, 'Should handle at least 60% of requests despite service failures');
        $this->assertLessThan(10, $errorCount, 'Should not have complete failures');
    }

    /** @test */
    public function it_tests_websocket_performance_for_real_time_updates()
    {
        $gateway = Gateway::find($this->rtuGateways[0]['id']);
        $updateCount = 50;
        
        $startTime = microtime(true);
        
        // Simulate real-time updates
        for ($i = 0; $i < $updateCount; $i++) {
            $this->rtuCacheService->cacheRealTimeUpdate($gateway, 'system_health', [
                'cpu_load' => rand(30, 80),
                'memory_usage' => rand(40, 90),
                'timestamp' => now()->toISOString()
            ]);
            
            $this->rtuCacheService->cacheRealTimeUpdate($gateway, 'io_status', [
                'do1_status' => rand(0, 1) === 1,
                'do2_status' => rand(0, 1) === 1,
                'timestamp' => now()->toISOString()
            ]);
        }
        
        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;
        
        // Real-time update performance
        $this->assertLessThan(2.0, $totalTime, "Real-time updates took {$totalTime}s, should be under 2s");
        
        // Verify data is accessible
        $realtimeData = $this->rtuCacheService->getRealTimeData($gateway, 'system_health');
        $this->assertNotNull($realtimeData);
        $this->assertArrayHasKey('cpu_load', $realtimeData);
    }

    /** @test */
    public function it_tests_api_rate_limiting_under_load()
    {
        $gateway = Gateway::find($this->rtuGateways[0]['id']);
        $rapidRequests = 60; // Exceed typical rate limits
        
        $responses = [];
        $user = User::find($this->users[0]['id']);
        
        // Make rapid API requests
        for ($i = 0; $i < $rapidRequests; $i++) {
            $responses[] = $this->actingAs($user)
                ->postJson(route('api.rtu.digital-output', [
                    'gateway' => $gateway,
                    'output' => 'do1'
                ]), ['state' => $i % 2 === 0]);
        }
        
        // Analyze rate limiting behavior
        $successCount = 0;
        $rateLimitedCount = 0;
        
        foreach ($responses as $response) {
            if ($response->getStatusCode() === 200) {
                $successCount++;
            } elseif ($response->getStatusCode() === 429) {
                $rateLimitedCount++;
            }
        }
        
        // Should implement proper rate limiting
        $this->assertGreaterThan(0, $rateLimitedCount, 'Should implement rate limiting for rapid requests');
        $this->assertGreaterThan(10, $successCount, 'Should allow reasonable number of requests');
    }

    protected function setupMockServices(): void
    {
        // Mock RTU Data Service
        $mockRTUDataService = Mockery::mock(RTUDataService::class);
        $mockRTUDataService->shouldReceive('getSystemHealth')
            ->andReturn(RTUGatewayMock::getSystemHealthData(new Gateway()));
        $mockRTUDataService->shouldReceive('getNetworkStatus')
            ->andReturn(RTUGatewayMock::getNetworkStatusData(new Gateway()));
        $mockRTUDataService->shouldReceive('getIOStatus')
            ->andReturn(RTUGatewayMock::getIOStatusData(new Gateway()));
        $mockRTUDataService->shouldReceive('getTrendData')
            ->andReturn(RTUGatewayMock::getTrendData(new Gateway()));
        $mockRTUDataService->shouldReceive('setDigitalOutput')
            ->andReturn(RTUGatewayMock::getDigitalOutputControlResponse(true));

        // Mock RTU Alert Service
        $mockRTUAlertService = Mockery::mock(RTUAlertService::class);
        $mockRTUAlertService->shouldReceive('getGroupedAlerts')
            ->andReturn(RTUGatewayMock::getGroupedAlertsData(new Gateway()));

        $this->app->instance(RTUDataService::class, $mockRTUDataService);
        $this->app->instance(RTUAlertService::class, $mockRTUAlertService);
        
        $this->rtuDataService = $mockRTUDataService;
        $this->rtuAlertService = $mockRTUAlertService;
        $this->rtuCacheService = app(RTUCacheService::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}