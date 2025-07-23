@echo off
echo === Energy Monitor Startup Script ===
echo Starting at %date% %time%
echo.

REM Check project structure
if not exist "energy-monitor" (
    echo ERROR: energy-monitor directory not found!
    echo Current directory: %CD%
    pause
    exit /b 1
)

if not exist "energy-monitor\.env" (
    echo ERROR: .env file not found in energy-monitor directory!
    pause
    exit /b 1
)

echo ✓ Project structure verified
echo.

REM Check dependencies
echo Checking dependencies...

where php >nul 2>&1
if %errorlevel% neq 0 (
    echo ERROR: PHP not found
    pause
    exit /b 1
)
echo ✓ PHP found

where composer >nul 2>&1
if %errorlevel% neq 0 (
    echo ERROR: Composer not found
    pause
    exit /b 1
)
echo ✓ Composer found

where node >nul 2>&1
if %errorlevel% neq 0 (
    echo ERROR: Node.js not found
    pause
    exit /b 1
)
echo ✓ Node.js found

where npm >nul 2>&1
if %errorlevel% neq 0 (
    echo ERROR: NPM not found
    pause
    exit /b 1
)
echo ✓ NPM found

echo ✓ All dependencies found
echo.

REM Start XAMPP services
echo Starting XAMPP services...
net start MySQL >nul 2>&1
if %errorlevel% equ 0 (
    echo ✓ MySQL started
) else (
    echo ⚠ MySQL may already be running
)

net start Apache2.4 >nul 2>&1
if %errorlevel% equ 0 (
    echo ✓ Apache started
) else (
    echo ⚠ Apache may already be running
)

echo.

REM Navigate to Laravel directory
echo Navigating to energy-monitor directory...
cd energy-monitor

REM Install dependencies
echo Installing PHP dependencies...
composer install
if %errorlevel% neq 0 (
    echo ERROR: Composer install failed
    cd ..
    pause
    exit /b 1
)
echo ✓ PHP dependencies installed

echo Installing Node.js dependencies...
npm install
if %errorlevel% neq 0 (
    echo ERROR: NPM install failed
    cd ..
    pause
    exit /b 1
)
echo ✓ Node.js dependencies installed

echo Building frontend assets...
npm run build
if %errorlevel% neq 0 (
    echo ERROR: Asset build failed
    cd ..
    pause
    exit /b 1
)
echo ✓ Frontend assets built

REM Laravel setup
echo.
echo Setting up Laravel...

REM Generate app key if needed
findstr /C:"APP_KEY=base64:" .env >nul
if %errorlevel% neq 0 (
    echo Generating application key...
    php artisan key:generate
    echo ✓ Application key generated
)

REM Run migrations
echo Running database migrations...
php artisan migrate --force
if %errorlevel% neq 0 (
    echo ERROR: Database migrations failed
    cd ..
    pause
    exit /b 1
)
echo ✓ Database migrations completed

REM Seed database
echo Seeding database...
php artisan db:seed --force
if %errorlevel% equ 0 (
    echo ✓ Database seeded successfully
) else (
    echo ⚠ Database seeding had issues, continuing...
)

REM Clear caches
echo Clearing caches...
php artisan config:clear >nul
php artisan cache:clear >nul
php artisan view:clear >nul
echo ✓ Caches cleared

cd ..

REM Start Python service if available
if exist "python-modbus-service" (
    echo.
    echo Starting Python Modbus service...
    cd python-modbus-service
    start /B python scheduler.py
    echo ✓ Python service started in background
    cd ..
)

REM Start Laravel server
echo.
echo ╔══════════════════════════════════════════════════════════════╗
echo ║                    🎉 ALL READY TO GO! 🎉                   ║
echo ║                                                              ║
echo ║  Your Energy Monitor application will start at:              ║
echo ║  👉 http://localhost:8000                                    ║
echo ║                                                              ║
echo ║  Press Ctrl+C to stop the server                            ║
echo ╚══════════════════════════════════════════════════════════════╝
echo.

cd energy-monitor
echo Starting Laravel development server...
echo.
echo ╔══════════════════════════════════════════════════════════════╗
echo ║                    🎉 SERVER STARTING! 🎉                   ║
echo ║                                                              ║
echo ║  Your Energy Monitor application is now available at:        ║
echo ║  👉 http://localhost:8000                                    ║
echo ║                                                              ║
echo ║  Open this URL in your browser to access the application     ║
echo ║  Press Ctrl+C to stop the server when done                  ║
echo ╚══════════════════════════════════════════════════════════════╝
echo.
php artisan serve

echo.
echo Server stopped. Goodbye!
cd ..
pause