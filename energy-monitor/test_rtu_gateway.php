<?php

require_once 'vendor/autoload.php';

use App\Models\Gateway;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    // Create a test RTU gateway
    $gateway = new Gateway();
    $gateway->name = 'Test RTU Gateway';
    $gateway->fixed_ip = '192.168.1.100';
    $gateway->gateway_type = 'teltonika_rut956';
    $gateway->wan_ip = '203.0.113.1';
    $gateway->sim_iccid = '8944501234567890123';
    $gateway->cpu_load = 45.5;
    $gateway->memory_usage = 67.8;
    $gateway->rssi = -75;
    $gateway->communication_status = 'online';
    $gateway->save();

    echo "Gateway created successfully with ID: {$gateway->id}\n";
    echo "Is RTU Gateway: " . ($gateway->isRTUGateway() ? 'Yes' : 'No') . "\n";
    echo "System Health Score: {$gateway->getSystemHealthScore()}\n";
    echo "Signal Quality Status: {$gateway->getSignalQualityStatus()}\n";

    // Clean up
    $gateway->delete();
    echo "Test gateway deleted successfully.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}