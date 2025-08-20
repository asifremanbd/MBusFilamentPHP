<?php

require_once 'energy-monitor/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'energy-monitor/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Gateway;
use App\Models\User;

echo "=== RTU Dashboard Test ===" . PHP_EOL;

// Check if we have any gateways
$gateways = Gateway::all();
echo "Total gateways: " . $gateways->count() . PHP_EOL;

if ($gateways->isEmpty()) {
    echo "No gateways found. Creating a test RTU gateway..." . PHP_EOL;
    
    $gateway = Gateway::create([
        'name' => 'Test RTU Gateway',
        'fixed_ip' => '192.168.1.100',
        'gateway_type' => 'teltonika_rut956',
        'communication_status' => 'online',
        'cpu_load' => 45.5,
        'memory_usage' => 67.2,
        'uptime_hours' => 168,
        'rssi' => -75,
        'wan_ip' => '203.0.113.1',
        'sim_iccid' => '8944123456789012345',
        'sim_apn' => 'internet',
        'sim_operator' => 'Test Operator',
        'di1_status' => true,
        'di2_status' => false,
        'do1_status' => false,
        'do2_status' => true,
        'analog_input_voltage' => 5.25,
        'last_system_update' => now()
    ]);
    
    echo "Created test gateway: " . $gateway->name . " (ID: " . $gateway->id . ")" . PHP_EOL;
} else {
    $gateway = $gateways->first();
    
    // Update the first gateway to be an RTU gateway for testing
    $gateway->update([
        'gateway_type' => 'teltonika_rut956',
        'communication_status' => 'online',
        'cpu_load' => 45.5,
        'memory_usage' => 67.2,
        'uptime_hours' => 168,
        'rssi' => -75,
        'wan_ip' => '203.0.113.1',
        'sim_iccid' => '8944123456789012345',
        'sim_apn' => 'internet',
        'sim_operator' => 'Test Operator',
        'di1_status' => true,
        'di2_status' => false,
        'do1_status' => false,
        'do2_status' => true,
        'analog_input_voltage' => 5.25,
        'last_system_update' => now()
    ]);
    
    echo "Updated gateway: " . $gateway->name . " (ID: " . $gateway->id . ") to RTU type" . PHP_EOL;
}

// Test RTU gateway methods
echo "Testing RTU gateway methods..." . PHP_EOL;
echo "Is RTU Gateway: " . ($gateway->isRTUGateway() ? 'Yes' : 'No') . PHP_EOL;
echo "System Health Score: " . $gateway->getSystemHealthScore() . "/100" . PHP_EOL;
echo "Signal Quality Status: " . $gateway->getSignalQualityStatus() . PHP_EOL;

// Test RTU services
echo "Testing RTU services..." . PHP_EOL;

try {
    $rtuDataService = app(\App\Services\RTUDataService::class);
    $systemHealth = $rtuDataService->getSystemHealth($gateway);
    echo "✓ RTUDataService->getSystemHealth() works" . PHP_EOL;
    echo "  - Uptime: " . ($systemHealth['uptime_hours'] ?? 'N/A') . " hours" . PHP_EOL;
    echo "  - CPU Load: " . ($systemHealth['cpu_load'] ?? 'N/A') . "%" . PHP_EOL;
    echo "  - Memory Usage: " . ($systemHealth['memory_usage'] ?? 'N/A') . "%" . PHP_EOL;
} catch (Exception $e) {
    echo "✗ RTUDataService error: " . $e->getMessage() . PHP_EOL;
}

try {
    $rtuAlertService = app(\App\Services\RTUAlertService::class);
    $alerts = $rtuAlertService->getGroupedAlerts($gateway);
    echo "✓ RTUAlertService->getGroupedAlerts() works" . PHP_EOL;
    echo "  - Critical: " . ($alerts['critical_count'] ?? 0) . PHP_EOL;
    echo "  - Warning: " . ($alerts['warning_count'] ?? 0) . PHP_EOL;
    echo "  - Status: " . ($alerts['status_summary'] ?? 'Unknown') . PHP_EOL;
} catch (Exception $e) {
    echo "✗ RTUAlertService error: " . $e->getMessage() . PHP_EOL;
}

// Check if we have a user for testing
$users = User::all();
if ($users->isEmpty()) {
    echo "No users found. You'll need to create a user to test the dashboard." . PHP_EOL;
} else {
    $user = $users->first();
    echo "Test user available: " . $user->email . " (ID: " . $user->id . ")" . PHP_EOL;
}

echo "RTU Dashboard URL: http://your-domain/dashboard/rtu/" . $gateway->id . PHP_EOL;
echo "=== Test Complete ===" . PHP_EOL;