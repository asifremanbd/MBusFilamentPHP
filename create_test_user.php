<?php

// Script to create a test user for authentication testing
require_once 'energy-monitor/vendor/autoload.php';

// Load Laravel application
$app = require_once 'energy-monitor/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Creating test user...\n";

try {
    // Check if user already exists
    $existingUser = App\Models\User::where('email', 'admin@energymonitor.com')->first();
    
    if ($existingUser) {
        echo "User already exists: {$existingUser->email}\n";
    } else {
        // Create new user
        $user = App\Models\User::create([
            'name' => 'Admin User',
            'email' => 'admin@energymonitor.com',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);
        
        echo "✅ User created successfully!\n";
        echo "Email: {$user->email}\n";
        echo "Password: password123\n";
        echo "You can now log in at: http://165.22.112.94/admin/login\n";
    }
} catch (Exception $e) {
    echo "❌ Error creating user: " . $e->getMessage() . "\n";
}