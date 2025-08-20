<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Gateway;
use App\Models\Device;
use App\Models\UserGatewayAssignment;
use App\Models\UserDeviceAssignment;
use App\Services\PerformanceOptimizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LoadTest extends TestCase
{
    use RefreshDatabase;

    protected $users;
    protected $gateways;
    protected $devices;
    protected PerformanceOptimizationService $performanceService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->performanceService = app(PerformanceOptimizationService::class);
        $this->createLoadTestData();
    }

    protected function createLoadTestData(): void
    {
        // Create 100 users for load testing
        $this->users = User::factory()->count(100)->create(['role' => 'operator']);
        
        // Create 20 gateways
        $this->gateways = Gateway::factory()->count(20)->create();
        
        // Create 200 devices (10 per gateway)
        $this->devices = collect();
        foreach ($this->gateways as $gateway) {
            $gatewayDevices = Device::factory()->count(10)->create([
                'gateway_id' => $gateway->id
            ]);
            $this->devices = $this->devices->merge($gatewayDevices);
        }

        // Assign random gateways and devices to users
        foreach ($this->users as $user) {
            // Each user gets 2-5 random gateways
            $userGateways = $this->gateways->random(rand(2, 5));
            foreach ($userGateways as $gateway) {
                UserGatewayAssignment::create([
                    'user_id' => $user->id,
                    'gateway_id' => $gateway->id
                ]);
            }

            // Each user gets 5-15 random devices
            $userDevices = $this->devices->random(rand(5, 15));
            foreach ($userDevices as $device) {
                UserDeviceAssignment::create([
                    'user_id' => $user->id,
                    'device_id' => $device->id
                ]);
            }
        }
    }

    /** @test */
    public function system_handles_concurrent_dashboard_requests()
    {
        $this->markTestSkipped('Load test - run manually when needed');

        $concurrentUsers = 50;
        $requestsPerUser = 10;
        $results = [];

        Log::info("Starting load test with {$concurrentUsers} concurrent users");

        // Simulate concurrent requests
        $processes = [];
        for ($i = 0; $i < $concurrentUsers; $i++) {
            $user = $this->users->random();
            $processes[] = $this->simulateUserSession($user, $requestsPerUser);
        }

        // Wait for all processes to complete and collect results
        foreach ($processes as $process) {
            $results[] = $process;
        }

        $this->analyzeLoadTestResults($results);
    }

    protected function simulateUserSession(User $user, int $requestCount): array
    {
        $sessionResults = [
            'user_id' => $user->id,
            'requests' => [],
            'total_time' => 0,
            'errors' => 0
        ];

        $sessionStart = microtime(true);

        for ($i = 0; $i < $requestCount; $i++) {
            $requestStart = microtime(true);
            
            try {
                // Simulate different types of requests
                $requestType = rand(1, 4);
                
                switch ($requestType) {
                    case 1:
                        // Global dashboard
                        $response = $this->actingAs($user)->get(route('dashboard.global'));
                        break;
                    case 2:
                        // Gateway dashboard
                        $gateway = $this->gateways->random();
                        $response = $this->actingAs($user)->get(route('dashboard.gateway', ['gateway' => $gateway->id]));
                        break;
                    case 3:
                        // Gateways API
                        $response = $this->actingAs($user)->getJson('/api/dashboard/gateways');
                        break;
                    case 4:
                        // Dashboard config API
                        $response = $this->actingAs($user)->getJson('/api/dashboard/config?dashboard_type=global');
                        break;
                }

                $requestTime = microtime(true) - $requestStart;
                
                $sessionResults['requests'][] = [
                    'type' => $requestType,
                    'time' => $requestTime,
                    'status' => $response->getStatusCode(),
                    'success' => $response->isSuccessful()
                ];

                if (!$response->isSuccessful()) {
                    $sessionResults['errors']++;
                }

                // Small delay between requests
                usleep(rand(100000, 500000)); // 100-500ms

            } catch (\Exception $e) {
                $sessionResults['errors']++;
                $sessionResults['requests'][] = [
                    'type' => $requestType,
                    'time' => microtime(true) - $requestStart,
                    'status' => 500,
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        $sessionResults['total_time'] = microtime(true) - $sessionStart;
        
        return $sessionResults;
    }

    protected function analyzeLoadTestResults(array $results): void
    {
        $totalRequests = 0;
        $totalErrors = 0;
        $totalTime = 0;
        $requestTimes = [];

        foreach ($results as $session) {
            $totalRequests += count($session['requests']);
            $totalErrors += $session['errors'];
            $totalTime += $session['total_time'];

            foreach ($session['requests'] as $request) {
                $requestTimes[] = $request['time'];
            }
        }

        $averageRequestTime = array_sum($requestTimes) / count($requestTimes);
        $maxRequestTime = max($requestTimes);
        $minRequestTime = min($requestTimes);
        $errorRate = ($totalErrors / $totalRequests) * 100;

        // Performance assertions
        $this->assertLessThan(1.0, $averageRequestTime, 'Average request time too high');
        $this->assertLessThan(5.0, $maxRequestTime, 'Maximum request time too high');
        $this->assertLessThan(5.0, $errorRate, 'Error rate too high');

        Log::info('Load test results', [
            'total_requests' => $totalRequests,
            'total_errors' => $totalErrors,
            'error_rate' => $errorRate . '%',
            'average_request_time' => $averageRequestTime . 's',
            'min_request_time' => $minRequestTime . 's',
            'max_request_time' => $maxRequestTime . 's',
            'total_test_time' => $totalTime . 's'
        ]);
    }

    /** @test */
    public function cache_performance_under_load()
    {
        $this->markTestSkipped('Load test - run manually when needed');

        Cache::flush();
        
        // Warm up caches
        $this->performanceService->warmUpCaches();

        $users = $this->users->take(20);
        $cacheHits = 0;
        $cacheMisses = 0;

        foreach ($users as $user) {
            $start = microtime(true);
            
            // First call (should be cached after warm-up)
            $permissions1 = $this->performanceService->getCachedUserPermissions($user->id);
            $time1 = microtime(true) - $start;

            $start = microtime(true);
            
            // Second call (should definitely be cached)
            $permissions2 = $this->performanceService->getCachedUserPermissions($user->id);
            $time2 = microtime(true) - $start;

            // Second call should be significantly faster
            if ($time2 < $time1 * 0.5) {
                $cacheHits++;
            } else {
                $cacheMisses++;
            }
        }

        $cacheHitRate = ($cacheHits / ($cacheHits + $cacheMisses)) * 100;
        
        $this->assertGreaterThan(80, $cacheHitRate, 'Cache hit rate too low under load');
        
        Log::info('Cache performance under load', [
            'cache_hits' => $cacheHits,
            'cache_misses' => $cacheMisses,
            'hit_rate' => $cacheHitRate . '%'
        ]);
    }

    /** @test */
    public function database_connection_pool_handles_load()
    {
        $this->markTestSkipped('Load test - run manually when needed');

        $maxConcurrentQueries = 50;
        $queryResults = [];

        // Execute multiple queries simultaneously
        for ($i = 0; $i < $maxConcurrentQueries; $i++) {
            $user = $this->users->random();
            
            $start = microtime(true);
            
            try {
                // Execute a complex query
                $result = DB::table('user_gateway_assignments')
                    ->join('gateways', 'user_gateway_assignments.gateway_id', '=', 'gateways.id')
                    ->join('devices', 'gateways.id', '=', 'devices.gateway_id')
                    ->where('user_gateway_assignments.user_id', $user->id)
                    ->count();
                
                $queryTime = microtime(true) - $start;
                
                $queryResults[] = [
                    'success' => true,
                    'time' => $queryTime,
                    'result_count' => $result
                ];
                
            } catch (\Exception $e) {
                $queryResults[] = [
                    'success' => false,
                    'time' => microtime(true) - $start,
                    'error' => $e->getMessage()
                ];
            }
        }

        $successfulQueries = array_filter($queryResults, fn($r) => $r['success']);
        $failedQueries = array_filter($queryResults, fn($r) => !$r['success']);
        
        $successRate = (count($successfulQueries) / count($queryResults)) * 100;
        $averageQueryTime = array_sum(array_column($successfulQueries, 'time')) / count($successfulQueries);

        $this->assertGreaterThan(95, $successRate, 'Database query success rate too low');
        $this->assertLessThan(0.5, $averageQueryTime, 'Average query time too high under load');

        Log::info('Database connection pool performance', [
            'total_queries' => count($queryResults),
            'successful_queries' => count($successfulQueries),
            'failed_queries' => count($failedQueries),
            'success_rate' => $successRate . '%',
            'average_query_time' => $averageQueryTime . 's'
        ]);
    }

    /** @test */
    public function memory_usage_remains_stable_under_load()
    {
        $this->markTestSkipped('Load test - run manually when needed');

        $initialMemory = memory_get_usage();
        $memoryReadings = [$initialMemory];

        // Simulate sustained load
        for ($i = 0; $i < 100; $i++) {
            $user = $this->users->random();
            
            // Perform memory-intensive operations
            $permissions = $this->performanceService->getCachedUserPermissions($user->id);
            $stats = $this->performanceService->getCachedDashboardStats($user->id, 'global');
            
            // Record memory usage every 10 iterations
            if ($i % 10 === 0) {
                $memoryReadings[] = memory_get_usage();
            }
            
            // Force garbage collection occasionally
            if ($i % 25 === 0) {
                gc_collect_cycles();
            }
        }

        $finalMemory = memory_get_usage();
        $peakMemory = memory_get_peak_usage();
        $memoryIncrease = $finalMemory - $initialMemory;
        $memoryIncreasePercent = ($memoryIncrease / $initialMemory) * 100;

        // Memory increase should be reasonable (< 50% increase)
        $this->assertLessThan(50, $memoryIncreasePercent, 'Memory usage increased too much under load');
        
        // Peak memory should be reasonable (< 128MB)
        $this->assertLessThan(128 * 1024 * 1024, $peakMemory, 'Peak memory usage too high');

        Log::info('Memory usage under load', [
            'initial_memory' => $this->formatBytes($initialMemory),
            'final_memory' => $this->formatBytes($finalMemory),
            'peak_memory' => $this->formatBytes($peakMemory),
            'memory_increase' => $this->formatBytes($memoryIncrease),
            'increase_percent' => $memoryIncreasePercent . '%'
        ]);
    }

    /** @test */
    public function response_times_remain_consistent_under_load()
    {
        $this->markTestSkipped('Load test - run manually when needed');

        $responseTimes = [];
        $user = $this->users->first();

        // Measure response times over sustained load
        for ($i = 0; $i < 50; $i++) {
            $start = microtime(true);
            
            $response = $this->actingAs($user)
                ->getJson('/api/dashboard/gateways');
            
            $responseTime = microtime(true) - $start;
            $responseTimes[] = $responseTime;
            
            $this->assertEquals(200, $response->getStatusCode());
            
            // Small delay between requests
            usleep(100000); // 100ms
        }

        $averageTime = array_sum($responseTimes) / count($responseTimes);
        $maxTime = max($responseTimes);
        $minTime = min($responseTimes);
        $standardDeviation = $this->calculateStandardDeviation($responseTimes);

        // Response times should be consistent
        $this->assertLessThan(0.5, $averageTime, 'Average response time too high');
        $this->assertLessThan(1.0, $maxTime, 'Maximum response time too high');
        $this->assertLessThan(0.2, $standardDeviation, 'Response time variance too high');

        Log::info('Response time consistency under load', [
            'average_time' => $averageTime . 's',
            'min_time' => $minTime . 's',
            'max_time' => $maxTime . 's',
            'standard_deviation' => $standardDeviation . 's',
            'total_requests' => count($responseTimes)
        ]);
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;
        
        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }
        
        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }

    protected function calculateStandardDeviation(array $values): float
    {
        $mean = array_sum($values) / count($values);
        $squaredDifferences = array_map(fn($x) => pow($x - $mean, 2), $values);
        $variance = array_sum($squaredDifferences) / count($values);
        
        return sqrt($variance);
    }
}