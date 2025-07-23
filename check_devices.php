<?php

require 'energy-monitor/vendor/autoload.php';

$app = require_once 'energy-monitor/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Device;
use App\Models\Register;

echo "=== Device and Register Configuration ===\n\n";

for ($i = 1; $i <= 8; $i++) {
    $device = Device::find($i);
    if ($device) {
        echo "Device $i: {$device->name}\n";
        $registers = Register::where('device_id', $i)->get(['parameter_name']);
        foreach ($registers as $register) {
            echo "  - {$register->parameter_name}\n";
        }
        echo "\n";
    }
}