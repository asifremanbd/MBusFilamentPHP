<?php

/**
 * RTU Dashboard Comprehensive Test Runner
 * 
 * This script runs all RTU-related tests and generates a comprehensive report
 * covering functionality, performance, and optimization validation.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Illuminate\Support\Facades\Artisan;

class RTUTestRunner
{
    private array $testResults = [];
    private float $startTime;
    private array $performanceMetrics = [];

    public function __construct()
    {
        $this->startTime = microtime(true);
        echo "RTU Dashboard Comprehensive Test Suite\n";
        echo "=====================================\n\n";
    }

    /**
     * Run all RTU comprehensive tests
     */
    public function runAllTests(): void
    {
        $this->runUnitTests();
        $this->runFeatureTests();
        $this->runPerformanceTests();
        $this->runIntegrationTests();
        $this->runLoadTests();
        $this->generateReport();
    }

    /**
     * Run RTU unit tests
     */
    private function runUnitTests(): void
    {
        echo "Running RTU Unit Tests...\n";
        echo "------------------------\n";

        $unitTests = [
            'RTUDataServiceTest',
            'RTUAlertServiceTest',
            'RTUSystemHealthWidgetTest',
            'RTUIOMonitoringWidgetTest',
            'RTUTrendWidgetTest',
            'RTUAlertsWidgetTest',
            'RTUDashboardConfigServiceTest',
            'RTUDashboardSectionServiceTest',
            'RTUTrendPreferenceTest',
            'RTUWidgetErrorHandlerTest',
            'GatewayRTUMethodsTest'
        ];

        foreach ($unitTests as $test) {
            $this->runTest('Unit', $test);
        }

        echo "\n";
    }

    /**
     * Run RTU feature tests
     */
    private function runFeatureTests(): void
    {
        echo "Running RTU Feature Tests...\n";
        echo "----------------------------\n";

        $featureTests = [
            'RTUDashboardComprehensiveTest',
            'RTUDashboardControllerTest',
            'RTUDashboardNavigationTest',
            'RTUDashboardTemplateTest',
            'RTUIOControlTest',
            'RTUAlertsFilteringTest',
            'RTUTrendWidgetIntegrationTest',
            'RTUPreferencesControllerTest',
            'RTUCommunicationFailureTest',
            'RTUErrorHandlingIntegrationTest'
        ];

        foreach ($featureTests as $test) {
            $this->runTest('Feature', $test);
        }

        echo "\n";
    }

    /**
     * Run RTU performance tests
     */
    private function runPerformanceTests(): void
    {
        echo "Running RTU Performance Tests...\n";
        echo "-------------------------------\n";

        $performanceTests = [
            'RTUPerformanceTest'
        ];

        foreach ($performanceTests as $test) {
            $startTime = microtime(true);
            $result = $this->runTest('Feature', $test);
            $endTime = microtime(true);
            
            $this->performanceMetrics[$test] = [
                'execution_time' => $endTime - $startTime,
                'result' => $result
            ];
        }

        echo "\n";
    }

    /**
     * Run RTU integration tests
     */
    private function runIntegrationTests(): void
    {
        echo "Running RTU Integration Tests...\n";
        echo "-------------------------------\n";

        $integrationTests = [
            'RTUTeltonikaIntegrationTest'
        ];

        foreach ($integrationTests as $test) {
            $this->runTest('Feature', $test);
        }

        echo "\n";
    }

    /**
     * Run RTU load tests
     */
    private function runLoadTests(): void
    {
        echo "Running RTU Load Tests...\n";
        echo "------------------------\n";

        $loadTests = [
            'RTULoadTest'
        ];

        foreach ($loadTests as $test) {
            $startTime = microtime(true);
            $result = $this->runTest('Feature', $test);
            $endTime = microtime(true);
            
            $this->performanceMetrics[$test] = [
                'execution_time' => $endTime - $startTime,
                'result' => $result
            ];
        }

        echo "\n";
    }

    /**
     * Run individual test
     */
    private function runTest(string $type, string $testName): bool
    {
        $command = "test --testsuite={$type} --filter={$testName}";
        
        echo "  Running {$testName}... ";
        
        try {
            $exitCode = Artisan::call($command);
            $output = Artisan::output();
            
            if ($exitCode === 0 && !str_contains($output, 'FAILURES') && !str_contains($output, 'ERRORS')) {
                echo "✓ PASSED\n";
                $this->testResults[$testName] = [
                    'status' => 'PASSED',
                    'type' => $type,
                    'output' => $output
                ];
                return true;
            } else {
                echo "✗ FAILED\n";
                $this->testResults[$testName] = [
                    'status' => 'FAILED',
                    'type' => $type,
                    'output' => $output,
                    'exit_code' => $exitCode
                ];
                return false;
            }
        } catch (Exception $e) {
            echo "✗ ERROR: " . $e->getMessage() . "\n";
            $this->testResults[$testName] = [
                'status' => 'ERROR',
                'type' => $type,
                'error' => $e->getMessage()
            ];
            return false;
        }
    }

    /**
     * Generate comprehensive test report
     */
    private function generateReport(): void
    {
        $totalTime = microtime(true) - $this->startTime;
        
        echo "\nRTU Dashboard Test Report\n";
        echo "========================\n\n";

        // Summary statistics
        $totalTests = count($this->testResults);
        $passedTests = count(array_filter($this->testResults, fn($result) => $result['status'] === 'PASSED'));
        $failedTests = count(array_filter($this->testResults, fn($result) => $result['status'] === 'FAILED'));
        $errorTests = count(array_filter($this->testResults, fn($result) => $result['status'] === 'ERROR'));

        echo "Summary:\n";
        echo "--------\n";
        echo "Total Tests: {$totalTests}\n";
        echo "Passed: {$passedTests} (" . round(($passedTests / $totalTests) * 100, 1) . "%)\n";
        echo "Failed: {$failedTests} (" . round(($failedTests / $totalTests) * 100, 1) . "%)\n";
        echo "Errors: {$errorTests} (" . round(($errorTests / $totalTests) * 100, 1) . "%)\n";
        echo "Total Execution Time: " . round($totalTime, 2) . " seconds\n\n";

        // Performance metrics
        if (!empty($this->performanceMetrics)) {
            echo "Performance Metrics:\n";
            echo "-------------------\n";
            foreach ($this->performanceMetrics as $test => $metrics) {
                echo "{$test}: " . round($metrics['execution_time'], 2) . "s\n";
            }
            echo "\n";
        }

        // Test breakdown by type
        $testsByType = [];
        foreach ($this->testResults as $testName => $result) {
            $testsByType[$result['type']][] = $testName;
        }

        foreach ($testsByType as $type => $tests) {
            echo "{$type} Tests:\n";
            echo str_repeat('-', strlen($type) + 7) . "\n";
            
            foreach ($tests as $testName) {
                $result = $this->testResults[$testName];
                $status = $result['status'];
                $icon = $status === 'PASSED' ? '✓' : '✗';
                echo "  {$icon} {$testName} - {$status}\n";
            }
            echo "\n";
        }

        // Failed tests details
        $failedTestsDetails = array_filter($this->testResults, fn($result) => $result['status'] !== 'PASSED');
        
        if (!empty($failedTestsDetails)) {
            echo "Failed Tests Details:\n";
            echo "--------------------\n";
            
            foreach ($failedTestsDetails as $testName => $result) {
                echo "Test: {$testName}\n";
                echo "Status: {$result['status']}\n";
                
                if (isset($result['error'])) {
                    echo "Error: {$result['error']}\n";
                }
                
                if (isset($result['output'])) {
                    echo "Output:\n" . substr($result['output'], 0, 500) . "...\n";
                }
                
                echo "\n";
            }
        }

        // Recommendations
        echo "Recommendations:\n";
        echo "---------------\n";
        
        if ($passedTests === $totalTests) {
            echo "✓ All tests passed! RTU dashboard implementation is comprehensive and robust.\n";
        } else {
            echo "• Review failed tests and address any issues\n";
            echo "• Ensure all RTU functionality is properly tested\n";
            echo "• Verify performance requirements are met\n";
        }

        if (!empty($this->performanceMetrics)) {
            $avgPerformanceTime = array_sum(array_column($this->performanceMetrics, 'execution_time')) / count($this->performanceMetrics);
            if ($avgPerformanceTime > 10) {
                echo "• Consider optimizing performance tests - average execution time is high\n";
            }
        }

        echo "• Run tests regularly during development\n";
        echo "• Monitor performance metrics in production\n";
        echo "• Update tests when adding new RTU features\n\n";

        // Save detailed report to file
        $this->saveDetailedReport();
    }

    /**
     * Save detailed report to file
     */
    private function saveDetailedReport(): void
    {
        $reportData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'total_execution_time' => microtime(true) - $this->startTime,
            'test_results' => $this->testResults,
            'performance_metrics' => $this->performanceMetrics,
            'summary' => [
                'total_tests' => count($this->testResults),
                'passed_tests' => count(array_filter($this->testResults, fn($result) => $result['status'] === 'PASSED')),
                'failed_tests' => count(array_filter($this->testResults, fn($result) => $result['status'] === 'FAILED')),
                'error_tests' => count(array_filter($this->testResults, fn($result) => $result['status'] === 'ERROR'))
            ]
        ];

        $reportFile = __DIR__ . '/../../storage/logs/rtu-test-report-' . date('Y-m-d-H-i-s') . '.json';
        file_put_contents($reportFile, json_encode($reportData, JSON_PRETTY_PRINT));
        
        echo "Detailed report saved to: {$reportFile}\n";
    }
}

// Run the comprehensive test suite
if (php_sapi_name() === 'cli') {
    $runner = new RTUTestRunner();
    $runner->runAllTests();
}