# Teltonika RTU Connection Test Deployment Script (PowerShell)
# This script deploys the updated configuration and tests the Teltonika RTU connection

Write-Host "===== Teltonika RTU Connection Test Deployment =====" -ForegroundColor Green
Write-Host "Deploying updated Modbus configuration and testing Teltonika RTU at 192.168.1.1"

# Check if we're in the right directory
if (-not (Test-Path "python-modbus-service\config.json")) {
    Write-Host "❌ Error: python-modbus-service\config.json not found" -ForegroundColor Red
    Write-Host "Please run this script from the energy-monitor root directory"
    exit 1
}

# Backup existing configuration
Write-Host "📁 Creating backup of existing configuration..." -ForegroundColor Yellow
$timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
Copy-Item "python-modbus-service\config.json" "python-modbus-service\config.json.backup.$timestamp"

# Check if Python dependencies are installed
Write-Host "🔍 Checking Python dependencies..." -ForegroundColor Yellow
Set-Location "python-modbus-service"

try {
    python -c "import pymodbus" 2>$null
    if ($LASTEXITCODE -ne 0) {
        Write-Host "📦 Installing Python dependencies..." -ForegroundColor Yellow
        pip install -r requirements.txt
    }
} catch {
    Write-Host "📦 Installing Python dependencies..." -ForegroundColor Yellow
    pip install -r requirements.txt
}

# Test the Teltonika RTU connection
Write-Host "🔌 Testing Teltonika RTU connection..." -ForegroundColor Cyan
python test_teltonika_connection.py

if ($LASTEXITCODE -eq 0) {
    Write-Host "✅ Teltonika RTU connection test successful!" -ForegroundColor Green
    
    # Run a single poll to test the full configuration
    Write-Host "📊 Testing full Modbus polling with new configuration..." -ForegroundColor Cyan
    python poller.py
    
    if ($LASTEXITCODE -eq 0) {
        Write-Host "✅ Full Modbus polling test successful!" -ForegroundColor Green
        Write-Host "🚀 Ready to start scheduled polling"
        
        # Ask if user wants to start the scheduler
        $startScheduler = Read-Host "Do you want to start the Modbus scheduler? (y/n)"
        if ($startScheduler -eq "y" -or $startScheduler -eq "Y") {
            Write-Host "🔄 Starting Modbus scheduler..." -ForegroundColor Yellow
            Start-Process python -ArgumentList "scheduler.py" -WindowStyle Hidden
            Write-Host "✅ Scheduler started in background" -ForegroundColor Green
            Write-Host "📋 Check scheduler.log for ongoing status"
        }
    } else {
        Write-Host "❌ Full Modbus polling test failed" -ForegroundColor Red
        Write-Host "Check the configuration and try again"
    }
} else {
    Write-Host "❌ Teltonika RTU connection test failed" -ForegroundColor Red
    Write-Host "Please check:" -ForegroundColor Yellow
    Write-Host "  - VPN connection to gateway"
    Write-Host "  - Teltonika RTU IP address (192.168.1.1)"
    Write-Host "  - Modbus TCP port (502)"
    Write-Host "  - Network routing"
}

Set-Location ".."

Write-Host "===== Deployment Complete =====" -ForegroundColor Green