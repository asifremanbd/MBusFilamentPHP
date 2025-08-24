<?php

// Simple script to check users in the system
require_once 'energy-monitor/vendor/autoload.php';

// Load Laravel application
$app = require_once 'energy-monitor/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Checking users in the system...\n";

try {
    $userCount = App\Models\User::count();
    echo "Total users: {$userCount}\n";
    
    if ($userCount > 0) {
        $users = App\Models\User::select('id', 'name', 'email', 'created_at')->get();
        echo "\nUsers:\n";
        foreach ($users as $user) {
            echo "- ID: {$user->id}, Name: {$user->name}, Email: {$user->email}\n";
        }
    } else {
        echo "No users found. You may need to create a user first.\n";
        echo "You can create a user via Filament admin panel or using artisan tinker.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}