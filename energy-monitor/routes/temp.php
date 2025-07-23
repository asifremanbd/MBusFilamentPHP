<?php

use Illuminate\Support\Facades\Route;
use App\Models\User;
use App\Models\Gateway;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

// Temporary route to set up the application
Route::get('/setup', function () {
    // Create admin user if it doesn't exist
    $admin = User::firstOrCreate(
        ['email' => 'admin@example.com'],
        [
            'name' => 'Admin User',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'phone' => '+1234567890',
            'email_notifications' => true,
            'sms_notifications' => false,
            'notification_critical_only' => false,
        ]
    );

    // Create OEMIA gateway if it doesn't exist
    $gateway = Gateway::firstOrCreate(
        ['name' => 'OEMIA Gateway 1'],
        [
            'fixed_ip' => '10.225.57.5',
            'sim_number' => '00467191031035460',
            'gsm_signal' => -70,
            'gnss_location' => '54.7753, 9.9348', // Flintbek, Germany coordinates
        ]
    );

    // Log in the admin user
    Auth::login($admin);

    // Return success message
    return response()->json([
        'success' => true,
        'message' => 'Setup completed successfully',
        'user' => $admin,
        'gateway' => $gateway,
        'login_url' => url('/admin')
    ]);
});

// Temporary route to check system status
Route::get('/status', function () {
    return response()->json([
        'users_count' => User::count(),
        'gateways_count' => Gateway::count(),
        'gateways' => Gateway::all(),
        'system_status' => 'operational',
        'login_url' => url('/admin')
    ]);
});