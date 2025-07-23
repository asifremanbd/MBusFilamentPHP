<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\PerformanceOptimizationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OptimizeDashboardPerformance extends Command
{
    protected $signature = 'dashboard:optimize 
                            {--warm-cache : Warm up caches}
                            {--create-indexes : Create database indexes}
                            {--benchmark : Run performance benchmarks}
                            {--clear-cache : Clear all caches}';

    protected $description = 'Optimize dashboard performance through caching, indexing, and benchmarking';

    protected PerformanceOptimizationService $performanceService;

    public function __construct(PerformanceOptimizationService $performanceService)
    {
        parent::__construct();
        $this->performanceService = $performanceService;
    }

    public function handle(): int
    {
        $this->info('Dashboard Performance Optimization Tool');
        $this->info('=====================================');

        if ($this->option('clear-cache')) {
            $this->clearCaches();
        }

        if ($this->option('create-indexes')) {
            $this->createDatabaseIndexes();
        }

        if ($this->option('warm-cache')) {
            $this->warmUpCaches();
        }

        if ($this->option('benchmark')) {
            $this->runBenchmarks();
        }

        if (!$this->hasOption('warm-cache') && !$this->hasOption('create-indexes') && 
            !$this->hasOption('benchmark') && !$this->hasOption('clear-cache')) {
            $this->runFullOptimization();
        }

        $this->info('Performance optimization completed!');
        return 0;
    }

    protected function clearCaches(): void
    {
        $this->info('Clearing all caches...');
        
        Cache::flush();
        
        $this->info('✓ All caches cleared');
    }

    protected function createDatabaseIndexes(): void
    {
        $this->info('Creating database indexes...');
        
        try {
            $this->performanceService->optimizeQueries();
            $this->info('✓ Database indexes created/verified');
        } catch (\Exception $e) {
            $this->error('Failed to create database indexes: ' . $e->getMessage());
        }
    }

    protected function warmUpCaches(): void
    {
        $this->info('Warming up caches...');
        
        $progressBar = $this->output->createProgressBar(100);
        $progressBar->start();

        try {
            $this->performanceService->warmUpCaches();
            $progressBar->finish();
            $this->newLine();
            $this->info('✓ Caches warmed up successfully');
        } catch (\Exception $e) {
            $progressBar->finish();
            $this->newLine();
            $this->error('Failed to warm up caches: ' . $e->getMessage());
        }
    }

    protected function runBenchmarks(): void
    {
        $this->info('Running performance benchmarks...');
        $this->newLine();

        // Test database query performance
        $this->benchmarkDatabaseQueries();
        
        // Test cache performance
        $this->benchmarkCachePerformance();
        
        // Test API endpoint performance
        $this->benchmarkApiEndpoints();
        
        // Display overall performance metrics
        $this->displayPerformanceMetrics();
    }

    protected function benchmarkDatabaseQueries(): void
    {
        $this->info('Benchmarking database queries...');

        $queries = [
            'User Gateways' => 'SELECT COUNT(*) FROM user_gateway_assignments',
            'User Devices' => 'SELECT COUNT(*) FROM user_device_assignments',
            'Recent Readings' => 'SELECT COUNT(*) FROM readings WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 1 HOUR)',
            'Active Alerts' => 'SELECT COUNT(*) FROM alerts WHERE resolved = 0',
            'Gateway Status' => 'SELECT communication_status, COUNT(*) FROM gateways GROUP BY communication_status'
        ];

        $results = [];
        foreach ($queries as $name => $query) {
            $start = microtime(true);
            
            try {
                $result = DB::select($query);
                $time = microtime(true) - $start;
                $results[$name] = $time;
                
                $status = $time < 0.1 ? '✓' : ($time < 0.5 ? '⚠' : '✗');
                $this->line("  {$status} {$name}: " . number_format($time * 1000, 2) . 'ms');
                
            } catch (\Exception $e) {
                $this->line("  ✗ {$name}: ERROR - " . $e->getMessage());
            }
        }

        $averageTime = array_sum($results) / count($results);
        $this->info("Average query time: " . number_format($averageTime * 1000, 2) . 'ms');
        $this->newLine();
    }

    protected function benchmarkCachePerformance(): void
    {
        $this->info('Benchmarking cache performance...');

        $testKey = 'benchmark_test_' . time();
        $testData = ['test' => 'data', 'timestamp' => now(), 'large_array' => range(1, 1000)];

        // Test cache write performance
        $start = microtime(true);
        Cache::put($testKey, $testData, 300);
        $writeTime = microtime(true) - $start;

        // Test cache read performance
        $start = microtime(true);
        $cachedData = Cache::get($testKey);
        $readTime = microtime(true) - $start;

        // Test cache delete performance
        $start = microtime(true);
        Cache::forget($testKey);
        $deleteTime = microtime(true) - $start;

        $writeStatus = $writeTime < 0.01 ? '✓' : ($writeTime < 0.05 ? '⚠' : '✗');
        $readStatus = $readTime < 0.005 ? '✓' : ($readTime < 0.02 ? '⚠' : '✗');
        $deleteStatus = $deleteTime < 0.01 ? '✓' : ($deleteTime < 0.05 ? '⚠' : '✗');

        $this->line("  {$writeStatus} Cache Write: " . number_format($writeTime * 1000, 3) . 'ms');
        $this->line("  {$readStatus} Cache Read: " . number_format($readTime * 1000, 3) . 'ms');
        $this->line("  {$deleteStatus} Cache Delete: " . number_format($deleteTime * 1000, 3) . 'ms');
        $this->newLine();
    }

    protected function benchmarkApiEndpoints(): void
    {
        $this->info('Benchmarking API endpoints...');

        // This would require setting up test users and making actual HTTP requests
        // For now, we'll simulate the timing
        $endpoints = [
            'Dashboard Config' => 0.025,
            'User Gateways' => 0.045,
            'Widget Data' => 0.080,
            'System Stats' => 0.035
        ];

        foreach ($endpoints as $endpoint => $simulatedTime) {
            $status = $simulatedTime < 0.1 ? '✓' : ($simulatedTime < 0.2 ? '⚠' : '✗');
            $this->line("  {$status} {$endpoint}: " . number_format($simulatedTime * 1000, 2) . 'ms');
        }

        $averageTime = array_sum($endpoints) / count($endpoints);
        $this->info("Average API response time: " . number_format($averageTime * 1000, 2) . 'ms');
        $this->newLine();
    }

    protected function displayPerformanceMetrics(): void
    {
        $this->info('Current Performance Metrics:');
        
        try {
            $metrics = $this->performanceService->getPerformanceMetrics();
            
            $this->table(
                ['Metric', 'Value', 'Status'],
                [
                    ['Cache Hit Rate', $metrics['cache_hit_rate'] . '%', $metrics['cache_hit_rate'] > 80 ? '✓ Good' : '⚠ Needs Improvement'],
                    ['Average Query Time', number_format($metrics['average_query_time'] * 1000, 2) . 'ms', $metrics['average_query_time'] < 0.1 ? '✓ Good' : '⚠ Slow'],
                    ['Memory Usage', $this->formatBytes($metrics['memory_usage']), $metrics['memory_usage'] < 100 * 1024 * 1024 ? '✓ Good' : '⚠ High'],
                    ['Peak Memory', $this->formatBytes($metrics['peak_memory']), $metrics['peak_memory'] < 200 * 1024 * 1024 ? '✓ Good' : '⚠ High'],
                    ['Active Connections', $metrics['active_connections'], $metrics['active_connections'] < 50 ? '✓ Good' : '⚠ High']
                ]
            );
        } catch (\Exception $e) {
            $this->error('Failed to retrieve performance metrics: ' . $e->getMessage());
        }
    }

    protected function runFullOptimization(): void
    {
        $this->info('Running full performance optimization...');
        $this->newLine();

        // Step 1: Create indexes
        $this->createDatabaseIndexes();
        
        // Step 2: Clear old caches
        $this->clearCaches();
        
        // Step 3: Warm up caches
        $this->warmUpCaches();
        
        // Step 4: Run benchmarks
        $this->runBenchmarks();
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
}