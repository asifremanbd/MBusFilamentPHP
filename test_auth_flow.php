<?php

/**
 * Test Authentication Flow for RTU Dashboard
 * 
 * This script tests the authentication flow to ensure users are properly
 * redirected to login when accessing protected RTU dashboard routes.
 */

echo "Testing Authentication Flow for RTU Dashboard\n";
echo "============================================\n\n";

// Test URLs
$baseUrl = 'http://165.22.112.94';
$testUrls = [
    '/dashboard/rtu/3' => 'RTU Dashboard (should redirect to login)',
    '/login' => 'Login route (should redirect to admin login)',
    '/admin/login' => 'Filament admin login (should show login form)'
];

foreach ($testUrls as $path => $description) {
    echo "Testing: {$description}\n";
    echo "URL: {$baseUrl}{$path}\n";
    
    // Initialize cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // Don't follow redirects automatically
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true); // HEAD request only
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    
    if (curl_error($ch)) {
        echo "❌ Error: " . curl_error($ch) . "\n";
    } else {
        echo "Status: {$httpCode}\n";
        if ($redirectUrl) {
            echo "Redirects to: {$redirectUrl}\n";
        }
        
        // Check expected behavior
        if ($path === '/dashboard/rtu/3' && ($httpCode === 302 || $httpCode === 301)) {
            echo "✅ Correctly redirects unauthenticated users\n";
        } elseif ($path === '/login' && ($httpCode === 302 || $httpCode === 301)) {
            echo "✅ Login route redirects properly\n";
        } elseif ($path === '/admin/login' && $httpCode === 200) {
            echo "✅ Admin login page accessible\n";
        } else {
            echo "⚠️  Unexpected response\n";
        }
    }
    
    curl_close($ch);
    echo "\n";
}

echo "Authentication flow test completed.\n";
echo "\nNext steps:\n";
echo "1. Visit {$baseUrl}/admin/login to log in\n";
echo "2. After login, visit {$baseUrl}/dashboard/rtu/3 to access RTU dashboard\n";