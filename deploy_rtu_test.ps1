# Deploy and run RTU connection test on production server
# Usage: .\deploy_rtu_test.ps1 -ServerIP "your-server-ip" -Username "ubuntu"

param(
    [Parameter(Mandatory=$true)]
    [string]$ServerIP,
    
    [Parameter(Mandatory=$false)]
    [string]$Username = "ubuntu",
    
    [Parameter(Mandatory=$false)]
    [string]$RemotePath = "/tmp/rtu_test"
)

$LocalTestFile = "test_rtu_production.py"

Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "RTU Connection Test - Production Deployment" -ForegroundColor Cyan
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "Server: $Username@$ServerIP" -ForegroundColor Yellow
Write-Host "Remote path: $RemotePath" -ForegroundColor Yellow
Write-Host "==========================================" -ForegroundColor Cyan

# Check if test file exists
if (-not (Test-Path $LocalTestFile)) {
    Write-Host "❌ Error: $LocalTestFile not found" -ForegroundColor Red
    exit 1
}

Write-Host "📤 Uploading test script to production server..." -ForegroundColor Blue

try {
    # Create remote directory and upload test script
    ssh $Username@$ServerIP "mkdir -p $RemotePath"
    scp $LocalTestFile "$Username@$ServerIP`:$RemotePath/"
    
    Write-Host "✅ Test script uploaded successfully" -ForegroundColor Green
    
    Write-Host "🔧 Installing dependencies on production server..." -ForegroundColor Blue
    
    # Install Python dependencies if needed
    $installScript = @"
if ! python3 -c "import pymodbus" 2>/dev/null; then
    echo "Installing pymodbus..."
    pip3 install pymodbus --user
else
    echo "pymodbus already installed"
fi
"@
    
    ssh $Username@$ServerIP $installScript
    
    Write-Host "✅ Dependencies ready" -ForegroundColor Green
    
    Write-Host "🧪 Running RTU connection test on production server..." -ForegroundColor Blue
    
    # Run the test and capture output
    $testResult = ssh $Username@$ServerIP "cd $RemotePath && python3 test_rtu_production.py"
    
    if ($LASTEXITCODE -eq 0) {
        Write-Host "✅ RTU connection test completed successfully" -ForegroundColor Green
    } else {
        Write-Host "❌ Test found issues - check output above for details" -ForegroundColor Red
    }
    
    Write-Host $testResult
    
} catch {
    Write-Host "❌ Error during deployment or execution: $_" -ForegroundColor Red
} finally {
    Write-Host "🧹 Cleaning up..." -ForegroundColor Blue
    ssh $Username@$ServerIP "rm -rf $RemotePath"
}

Write-Host "✅ RTU connection test completed" -ForegroundColor Green
Write-Host "==========================================" -ForegroundColor Cyan