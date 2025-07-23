# Energy Monitor Startup Script - Simple Version
# Run this from the project root directory

Write-Host "=== Energy Monitor Startup Script ===" -ForegroundColor Cyan
Write-Host ""

# Check if we're in the right directory
if (-not (Test-Path "energy-monitor")) {
    Write-Host "ERROR: energy-monitor directory not found!" -ForegroundColor Red
    Write-Host "Please run this script from the project root directory." -ForegroundColor Yellow
    Read-Host "Press Enter to exit"
    exit 1
}

# Check if .env exists
if (-not (Test-Path "energy-monitor\.env")) {
    Write-Host "ERROR: .env file not found in energy-monitor directory!" -ForegroundColor Red
    Write-Host "Please create your .env file first." -ForegroundColor Yellow
    Read-Host "Press Enter to exit"
    exit 1
}

Write-Host "✓ Project structure verified" -ForegroundColor Green

# Function to check if a command exists
function Test-Command($command) {
    try {
        Get-Command $command -ErrorAction Stop | Out-Null
        return $true
    } catch {
        return $false
    }
}

# Check dependencies
Write-Host "`nChecking dependencies..." -ForegroundColor Blue

$missing = @()
if (-not (Test-Command "php")) { $missing += "PHP" }
if (-not (Test-Command "composer")) { $missing += "Composer" }
if (-not (Test-Command "node")) { $missing += "Node.js" }
if (-not (Test-Command "npm")) { $missing += "NPM" }

if ($missing.Count -gt 0) {
    Write-Host "ERROR: Missing dependencies: $($missing -join ', ')" -ForegroundColor Red
    Write-Host "Please install the missing dependencies and try again." -ForegroundColor Yellow
    Read-Host "Press Enter to exit"
    exit 1
}

Write-Host "✓ All dependencies found" -ForegroundColor Green

# Check and start XAMPP services
Write-Host "`nChecking XAMPP services..." -ForegroundColor Blue

$mysqlRunning = $false
$apacheRunning = $false

try {
    $mysql = Get-Service -Name "MySQL" -ErrorAction SilentlyContinue
    if ($mysql -and $mysql.Status -eq 'Running') {
        $mysqlRunning = $true
        Write-Host "✓ MySQL is running" -ForegroundColor Green
    } else {
        Write-Host "⚠ MySQL is not running. Attempting to start..." -ForegroundColor Yellow
        try {
            Start-Service -Name "MySQL"
            Start-Sleep -Seconds 3
            $mysql = Get-Service -Name "MySQL"
            if ($mysql.Status -eq 'Running') {
                $mysqlRunning = $true
                Write-Host "✓ MySQL started successfully" -ForegroundColor Green
            }
        } catch {
            Write-Host "✗ Failed to start MySQL: $($_.Exception.Message)" -ForegroundColor Red
        }
    }
} catch {
    Write-Host "✗ MySQL service not found. Please install XAMPP." -ForegroundColor Red
}

try {
    $apache = Get-Service -Name "Apache2.4" -ErrorAction SilentlyContinue
    if ($apache -and $apache.Status -eq 'Running') {
        $apacheRunning = $true
        Write-Host "✓ Apache is running" -ForegroundColor Green
    } else {
        Write-Host "⚠ Apache is not running. Attempting to start..." -ForegroundColor Yellow
        try {
            Start-Service -Name "Apache2.4"
            Start-Sleep -Seconds 3
            $apache = Get-Service -Name "Apache2.4"
            if ($apache.Status -eq 'Running') {
                $apacheRunning = $true
                Write-Host "✓ Apache started successfully" -ForegroundColor Green
            }
        } catch {
            Write-Host "✗ Failed to start Apache: $($_.Exception.Message)" -ForegroundColor Red
        }
    }
} catch {
    Write-Host "✗ Apache service not found. Please install XAMPP." -ForegroundColor Red
}

if (-not $mysqlRunning) {
    Write-Host "`nERROR: MySQL is required but not running!" -ForegroundColor Red
    Write-Host "Please start MySQL through XAMPP Control Panel and try again." -ForegroundColor Yellow
    Read-Host "Press Enter to exit"
    exit 1
}

# Install dependencies
Write-Host "`nInstalling dependencies..." -ForegroundColor Blue
Set-Location "energy-monitor"

Write-Host "Installing PHP dependencies..." -ForegroundColor Cyan
composer install
if ($LASTEXITCODE -ne 0) {
    Write-Host "✗ Composer install failed" -ForegroundColor Red
    Set-Location ..
    Read-Host "Press Enter to exit"
    exit 1
}
Write-Host "✓ PHP dependencies installed" -ForegroundColor Green

Write-Host "Installing Node.js dependencies..." -ForegroundColor Cyan
npm install
if ($LASTEXITCODE -ne 0) {
    Write-Host "✗ NPM install failed" -ForegroundColor Red
    Set-Location ..
    Read-Host "Press Enter to exit"
    exit 1
}
Write-Host "✓ Node.js dependencies installed" -ForegroundColor Green

Write-Host "Building frontend assets..." -ForegroundColor Cyan
npm run build
if ($LASTEXITCODE -ne 0) {
    Write-Host "✗ Asset build failed" -ForegroundColor Red
    Set-Location ..
    Read-Host "Press Enter to exit"
    exit 1
}
Write-Host "✓ Frontend assets built" -ForegroundColor Green

# Laravel setup
Write-Host "`nSetting up Laravel..." -ForegroundColor Blue

# Generate app key if needed
$envContent = Get-Content ".env" -Raw
if (-not ($envContent -match "APP_KEY=base64:")) {
    Write-Host "Generating application key..." -ForegroundColor Cyan
    php artisan key:generate
    Write-Host "✓ Application key generated" -ForegroundColor Green
}

# Test database connection
Write-Host "Testing database connection..." -ForegroundColor Cyan
$dbTest = php artisan tinker --execute="try { DB::connection()->getPdo(); echo 'OK'; } catch(Exception `$e) { echo 'FAIL: ' . `$e->getMessage(); }" 2>&1
if ($dbTest -match "OK") {
    Write-Host "✓ Database connection successful" -ForegroundColor Green
} else {
    Write-Host "✗ Database connection failed: $dbTest" -ForegroundColor Red
    Write-Host "Please check your .env database settings." -ForegroundColor Yellow
    Set-Location ..
    Read-Host "Press Enter to exit"
    exit 1
}

# Run migrations
Write-Host "Running database migrations..." -ForegroundColor Cyan
php artisan migrate --force
if ($LASTEXITCODE -ne 0) {
    Write-Host "✗ Database migrations failed" -ForegroundColor Red
    Set-Location ..
    Read-Host "Press Enter to exit"
    exit 1
}
Write-Host "✓ Database migrations completed" -ForegroundColor Green

# Seed database
Write-Host "Seeding database..." -ForegroundColor Cyan
php artisan db:seed --force
if ($LASTEXITCODE -eq 0) {
    Write-Host "✓ Database seeded successfully" -ForegroundColor Green
} else {
    Write-Host "⚠ Database seeding had issues, but continuing..." -ForegroundColor Yellow
}

# Clear caches
Write-Host "Clearing caches..." -ForegroundColor Cyan
php artisan config:clear | Out-Null
php artisan cache:clear | Out-Null
php artisan view:clear | Out-Null
Write-Host "✓ Caches cleared" -ForegroundColor Green

Set-Location ..

# Start Python service if available
if (Test-Path "python-modbus-service") {
    Write-Host "`nStarting Python Modbus service..." -ForegroundColor Blue
    Set-Location "python-modbus-service"
    
    # Check Python dependencies
    python -c "import requests, schedule, logging" 2>$null
    if ($LASTEXITCODE -ne 0) {
        Write-Host "Installing Python dependencies..." -ForegroundColor Cyan
        pip install -r requirements.txt
    }
    
    # Start Python service in background
    Start-Process -FilePath "python" -ArgumentList "scheduler.py" -WindowStyle Hidden
    Write-Host "✓ Python Modbus service started" -ForegroundColor Green
    Set-Location ..
}

# Start Laravel server
Write-Host "`nStarting Laravel development server..." -ForegroundColor Blue
Set-Location "energy-monitor"

Write-Host ""
Write-Host "╔══════════════════════════════════════════════════════════════╗" -ForegroundColor Green
Write-Host "║                    🎉 ALL READY TO GO! 🎉                   ║" -ForegroundColor Green
Write-Host "║                                                              ║" -ForegroundColor Green
Write-Host "║  Your Energy Monitor application will start at:              ║" -ForegroundColor Green
Write-Host "║  👉 http://localhost:8000                                    ║" -ForegroundColor Green
Write-Host "║                                                              ║" -ForegroundColor Green
Write-Host "║  Press Ctrl+C to stop the server                            ║" -ForegroundColor Green
Write-Host "╚══════════════════════════════════════════════════════════════╝" -ForegroundColor Green
Write-Host ""

# Start Laravel server (this will block until Ctrl+C)
php artisan serve

Write-Host "`nServer stopped. Goodbye!" -ForegroundColor Yellow
Set-Location ..