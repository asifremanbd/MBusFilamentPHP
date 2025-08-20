# SSH to production server and run RTU connectivity test
# Usage: .\ssh_production_test.ps1 -ServerIP "your-server-ip" -Username "ubuntu"

param(
    [Parameter(Mandatory=$true)]
    [string]$ServerIP,
    
    [Parameter(Mandatory=$false)]
    [string]$Username = "ubuntu"
)

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "SSH Production RTU Test" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Connecting to: $Username@$ServerIP" -ForegroundColor Yellow
Write-Host "========================================" -ForegroundColor Cyan

# Upload the test script
Write-Host "📤 Uploading test script..." -ForegroundColor Blue

try {
    scp production_rtu_check.py "$Username@$ServerIP`:~/production_rtu_check.py"
    Write-Host "✅ Script uploaded successfully" -ForegroundColor Green
    
    Write-Host "🧪 Running RTU test on production server..." -ForegroundColor Blue
    Write-Host ""
    
    # Run the test script on production server
    ssh $Username@$ServerIP "python3 ~/production_rtu_check.py"
    
    Write-Host ""
    Write-Host "🧹 Cleaning up..." -ForegroundColor Blue
    ssh $Username@$ServerIP "rm ~/production_rtu_check.py"
    
    Write-Host "✅ Test completed" -ForegroundColor Green
    
} catch {
    Write-Host "❌ Error: $_" -ForegroundColor Red
    Write-Host ""
    Write-Host "Manual SSH command:" -ForegroundColor Yellow
    Write-Host "ssh $Username@$ServerIP" -ForegroundColor White
    Write-Host ""
    Write-Host "Then run these commands on the server:" -ForegroundColor Yellow
    Write-Host "wget https://raw.githubusercontent.com/your-repo/production_rtu_check.py" -ForegroundColor White
    Write-Host "python3 production_rtu_check.py" -ForegroundColor White
}

Write-Host "========================================" -ForegroundColor Cyan