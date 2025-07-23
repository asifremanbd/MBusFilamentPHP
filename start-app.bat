@echo off
setlocal enabledelayedexpansion

REM Create simple log file
set "logfile=startup-log.txt"

echo === Energy Monitor Startup Script === > "%logfile%"
echo Starting at %date% %time% >> "%logfile%"
echo. >> "%logfile%"

echo === Energy Monitor Startup Script ===
echo Starting at %date% %time%
echo Logging to: %logfile%
echo.

REM Function to log and display
goto :main

:LogAndShow
echo %~1
echo %~1 >> "%logfile%"
goto :eof

:main
call :LogAndShow "=== STEP 1: Checking Project Structure ==="

REM Check current directory
call :LogAndShow "Current directory: %CD%"

REM Check if we're in the right directory
if not exist "energy-monitor" (
    call :LogAndShow "ERROR: energy-monitor directory not found!"
    call :LogAndShow "Directory contents:"
    dir >> "%logfile%"
    call :LogAndShow "Please run this script from the project root directory."
    call :LogAndShow "You are currently in: %CD%"
    call :LogAndShow "Please navigate to your project folder first."
    pause
    exit /b 1
)

REM Check if .env exists
if not exist "energy-monitor\.env" (
    call :LogAndShow "ERROR: .env file not found in energy-monitor directory!"
    call :LogAndShow "Contents of energy-monitor directory:"
    dir energy-monitor >> "%logfile%"
    call :LogAndShow "Please create your .env file first."
    pause
    exit /b 1
)

call :LogAndShow "✓ Project structure verified"

REM Check dependencies
call :LogAndShow ""
call :LogAndShow "=== STEP 2: Checking Dependencies ==="

call :LogAndShow "Checking PHP..."
where php >nul 2>&1
if %errorlevel% neq 0 (
    call :LogAndShow "ERROR: PHP not found in PATH"
    call :LogAndShow "Please install PHP or add it to your PATH"
    pause
    exit /b 1
) else (
    php --version >> "%logfile%" 2>&1
    call :LogAndShow "✓ PHP found"
)

call :LogAndShow "Checking Composer..."
where composer >nul 2>&1
if %errorlevel% neq 0 (
    call :LogAndShow "ERROR: Composer not found in PATH"
    call :LogAndShow "Please install Composer or add it to your PATH"
    pause
    exit /b 1
) else (
    composer --version >> "%logfile%" 2>&1
    call :LogAndShow "✓ Composer found"
)

call :LogAndShow "Checking Node.js..."
where node >nul 2>&1
if %errorlevel% neq 0 (
    call :LogAndShow "ERROR: Node.js not found in PATH"
    call :LogAndShow "Please install Node.js or add it to your PATH"
    pause
    exit /b 1
) else (
    node --version >> "%logfile%" 2>&1
    call :LogAndShow "✓ Node.js found"
)

call :LogAndShow "Checking NPM..."
where npm >nul 2>&1
if %errorlevel% neq 0 (
    call :LogAndShow "ERROR: NPM not found in PATH"
    call :LogAndShow "Please install NPM or add it to your PATH"
    pause
    exit /b 1
) else (
    npm --version >> "%logfile%" 2>&1
    call :LogAndShow "✓ NPM found"
)

call :LogAndShow "✓ All dependencies found"

REM Check XAMPP services
call :LogAndShow ""
call :LogAndShow "=== STEP 3: Checking XAMPP Services ==="

call :LogAndShow "Checking MySQL service..."
sc query MySQL >> "%logfile%" 2>&1
if %errorlevel% equ 0 (
    call :LogAndShow "MySQL service found"
    net start MySQL >> "%logfile%" 2>&1
    if %errorlevel% equ 0 (
        call :LogAndShow "✓ MySQL started successfully"
    ) else (
        call :LogAndShow "⚠ MySQL may already be running"
    )
) else (
    call :LogAndShow "MySQL service not found, checking if process is running..."
    tasklist /FI "IMAGENAME eq mysqld.exe" | find /i "mysqld.exe" >nul
    if %errorlevel% equ 0 (
        call :LogAndShow "✓ MySQL process is running"
    ) else (
        call :LogAndShow "✗ MySQL is not running - please start it through XAMPP Control Panel"
        call :LogAndShow "Cannot continue without database"
        pause
        exit /b 1
    )
)

call :LogAndShow "Checking Apache service..."
sc query Apache2.4 >> "%logfile%" 2>&1
if %errorlevel% equ 0 (
    call :LogAndShow "Apache2.4 service found"
    net start Apache2.4 >> "%logfile%" 2>&1
    if %errorlevel% equ 0 (
        call :LogAndShow "✓ Apache started successfully"
    ) else (
        call :LogAndShow "⚠ Apache may already be running"
    )
) else (
    call :LogAndShow "Apache2.4 service not found, checking if process is running..."
    tasklist /FI "IMAGENAME eq httpd.exe" | find /i "httpd.exe" >nul
    if %errorlevel% equ 0 (
        call :LogAndShow "✓ Apache process is running"
    ) else (
        call :LogAndShow "⚠ Apache is not running - continuing anyway"
    )
)

REM Navigate to Laravel directory
call :LogAndShow ""
call :LogAndShow "=== STEP 4: Navigating to Laravel Directory ==="
cd energy-monitor
call :LogAndShow "Changed to directory: %CD%"

REM Test database connection
call :LogAndShow ""
call :LogAndShow "=== STEP 5: Testing Database Connection ==="
call :LogAndShow "Testing database connection..."

echo Testing database... > temp_db_test.php
echo ^<?php >> temp_db_test.php
echo require_once 'vendor/autoload.php'; >> temp_db_test.php
echo $app = require_once 'bootstrap/app.php'; >> temp_db_test.php
echo try { >> temp_db_test.php
echo     $pdo = new PDO('mysql:host=127.0.0.1;port=3306', 'root', 'asifreman'); >> temp_db_test.php
echo     echo 'DB_CONNECTION_OK'; >> temp_db_test.php
echo } catch(Exception $e) { >> temp_db_test.php
echo     echo 'DB_CONNECTION_FAIL: ' . $e-^>getMessage(); >> temp_db_test.php
echo } >> temp_db_test.php
echo ?^> >> temp_db_test.php

php temp_db_test.php >> "%logfile%" 2>&1
php temp_db_test.php > db_result.txt 2>&1
del temp_db_test.php

findstr "DB_CONNECTION_OK" db_result.txt >nul
if %errorlevel% equ 0 (
    call :LogAndShow "✓ Database connection successful"
) else (
    call :LogAndShow "✗ Database connection failed"
    call :LogAndShow "Database test result:"
    type db_result.txt >> "%logfile%"
    call :LogAndShow "Please check your database configuration"
    del db_result.txt
    cd ..
    pause
    exit /b 1
)
del db_result.txt

REM Install dependencies
call :LogAndShow ""
call :LogAndShow "=== STEP 6: Installing Dependencies ==="

call :LogAndShow "Installing PHP dependencies with Composer..."
composer install >> "%logfile%" 2>&1
if %errorlevel% neq 0 (
    call :LogAndShow "ERROR: Composer install failed - check log for details"
    cd ..
    pause
    exit /b 1
)
call :LogAndShow "✓ PHP dependencies installed"

call :LogAndShow "Installing Node.js dependencies..."
npm install >> "%logfile%" 2>&1
if %errorlevel% neq 0 (
    call :LogAndShow "ERROR: NPM install failed - check log for details"
    cd ..
    pause
    exit /b 1
)
call :LogAndShow "✓ Node.js dependencies installed"

call :LogAndShow "Building frontend assets..."
npm run build >> "%logfile%" 2>&1
if %errorlevel% neq 0 (
    call :LogAndShow "ERROR: Asset build failed - check log for details"
    cd ..
    pause
    exit /b 1
)
call :LogAndShow "✓ Frontend assets built"

REM Laravel setup
call :LogAndShow ""
call :LogAndShow "=== STEP 7: Setting up Laravel ==="

REM Generate app key if needed
findstr /C:"APP_KEY=base64:" .env >nul
if %errorlevel% neq 0 (
    call :LogAndShow "Generating application key..."
    php artisan key:generate >> "%logfile%" 2>&1
    call :LogAndShow "✓ Application key generated"
) else (
    call :LogAndShow "✓ Application key already exists"
)

REM Run migrations
call :LogAndShow "Running database migrations..."
php artisan migrate --force >> "%logfile%" 2>&1
if %errorlevel% neq 0 (
    call :LogAndShow "ERROR: Database migrations failed - check log for details"
    cd ..
    pause
    exit /b 1
)
call :LogAndShow "✓ Database migrations completed"

REM Seed database
call :LogAndShow "Seeding database..."
php artisan db:seed --force >> "%logfile%" 2>&1
if %errorlevel% equ 0 (
    call :LogAndShow "✓ Database seeded successfully"
) else (
    call :LogAndShow "⚠ Database seeding had issues, but continuing..."
)

REM Clear caches
call :LogAndShow "Clearing Laravel caches..."
php artisan config:clear >> "%logfile%" 2>&1
php artisan cache:clear >> "%logfile%" 2>&1
php artisan view:clear >> "%logfile%" 2>&1
call :LogAndShow "✓ Caches cleared"

cd ..

REM Start Python service if available
if exist "python-modbus-service" (
    call :LogAndShow ""
    call :LogAndShow "=== STEP 8: Starting Python Modbus Service ==="
    cd python-modbus-service
    
    call :LogAndShow "Starting Python service in background..."
    start /B python scheduler.py
    call :LogAndShow "✓ Python Modbus service started"
    cd ..
) else (
    call :LogAndShow "⚠ Python Modbus service directory not found, skipping..."
)

REM Start Laravel server
call :LogAndShow ""
call :LogAndShow "=== STEP 9: Starting Laravel Server ==="
cd energy-monitor

call :LogAndShow ""
call :LogAndShow "╔══════════════════════════════════════════════════════════════╗"
call :LogAndShow "║                    🎉 ALL READY TO GO! 🎉                   ║"
call :LogAndShow "║                                                              ║"
call :LogAndShow "║  Your Energy Monitor application will start at:              ║"
call :LogAndShow "║  👉 http://localhost:8000                                    ║"
call :LogAndShow "║                                                              ║"
call :LogAndShow "║  Log file: startup-log.txt                                   ║"
call :LogAndShow "║  Press Ctrl+C to stop the server                            ║"
call :LogAndShow "╚══════════════════════════════════════════════════════════════╝"
call :LogAndShow ""

call :LogAndShow "Starting Laravel development server..."
php artisan serve

call :LogAndShow ""
call :LogAndShow "Server stopped at %date% %time%"
cd ..
pause