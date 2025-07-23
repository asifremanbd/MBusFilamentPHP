<?php

// This is a simple script to start the Laravel application
// while bypassing any problematic pages

echo "Starting Energy Monitor Application...\n";

// Change to the energy-monitor directory
chdir(__DIR__ . '/energy-monitor');

// Run database migrations if needed
echo "Running database migrations...\n";
system('php artisan migrate --force');

// Seed the database if needed
echo "Seeding database...\n";
system('php artisan db:seed --force');

// Clear caches
echo "Clearing caches...\n";
system('php artisan config:clear');
system('php artisan cache:clear');
system('php artisan view:clear');

// Start the server
echo "Starting Laravel server...\n";
echo "Access your application at: http://localhost:8000\n";
echo "Login with: admin@example.com / password\n";
echo "Press Ctrl+C to stop the server\n";

// Start the server
system('php artisan serve');