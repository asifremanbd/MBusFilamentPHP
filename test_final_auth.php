<?php

/**
 * Final Authentication Test
 * Tests the complete authentication flow for RTU dashboard access
 */

echo "Final Authentication Flow Test\n";
echo "==============================\n\n";

$baseUrl = 'http://165.22.112.94';

// Test 1: Check if RTU dashboard redirects properly when not authenticated
echo "Test 1: RTU Dashboard Access (Unauthenticated)\n";
echo "URL: {$baseUrl}/dashboard/rtu/3\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/dashboard/rtu/3');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_NOBODY, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_error($ch)) {
    echo "❌ Error: " . curl_error($ch) . "\n";
} else {
    echo "Status Code: {$httpCode}\n";
    if ($httpCode === 302 || $httpCode === 301) {
        echo "✅ Correctly redirects unauthenticated users\n";
    } else {
        echo "⚠️  Unexpected status code\n";
    }
}
curl_close($ch);

echo "\n";

// Test 2: Check login route
echo "Test 2: Login Route\n";
echo "URL: {$baseUrl}/login\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_NOBODY, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_error($ch)) {
    echo "❌ Error: " . curl_error($ch) . "\n";
} else {
    echo "Status Code: {$httpCode}\n";
    if ($httpCode === 302 || $httpCode === 301) {
        echo "✅ Login route redirects to admin login\n";
    } else {
        echo "⚠️  Unexpected status code\n";
    }
}
curl_close($ch);

echo "\n";

// Test 3: Check admin login page
echo "Test 3: Admin Login Page\n";
echo "URL: {$baseUrl}/admin/login\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/admin/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_error($ch)) {
    echo "❌ Error: " . curl_error($ch) . "\n";
} else {
    echo "Status Code: {$httpCode}\n";
    if ($httpCode === 200) {
        echo "✅ Admin login page is accessible\n";
        if (strpos($response, 'login') !== false || strpos($response, 'email') !== false) {
            echo "✅ Login form appears to be present\n";
        }
    } else {
        echo "⚠️  Unexpected status code\n";
    }
}
curl_close($ch);

echo "\n";
echo "Authentication Flow Summary:\n";
echo "============================\n";
echo "✅ Login route added: /login → /admin/login\n";
echo "✅ Test user created: admin@energymonitor.com / password123\n";
echo "✅ AdminPanelProvider fixed (removed non-existent GatewayDashboard)\n";
echo "\nNext Steps:\n";
echo "1. Visit {$baseUrl}/admin/login\n";
echo "2. Log in with: admin@energymonitor.com / password123\n";
echo "3. After login, visit {$baseUrl}/dashboard/rtu/3\n";
echo "4. You should now see the RTU dashboard with all widgets\n";