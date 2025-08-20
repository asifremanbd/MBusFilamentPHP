# Simple Database Test
Write-Host "Testing Database Sync Setup" -ForegroundColor Green

# Test local MySQL
Write-Host "Testing local MySQL..." -ForegroundColor Yellow
$result = mysql -u root -e "SELECT 'Connection OK' as status"
if ($LASTEXITCODE -eq 0) {
    Write-Host "Local MySQL: OK" -ForegroundColor Green
} else {
    Write-Host "Local MySQL: FAILED" -ForegroundColor Red
}

# Test SSH
Write-Host "Testing SSH..." -ForegroundColor Yellow
$ssh = ssh root@165.22.112.94 "echo 'SSH OK'"
if ($ssh -eq "SSH OK") {
    Write-Host "SSH Connection: OK" -ForegroundColor Green
} else {
    Write-Host "SSH Connection: FAILED" -ForegroundColor Red
}

# Test server MySQL
Write-Host "Testing server MySQL..." -ForegroundColor Yellow
$server = ssh root@165.22.112.94 "mysql -u energy_user -penergy_password -e 'SELECT 1' 2>/dev/null"
if ($server -match "1") {
    Write-Host "Server MySQL: OK" -ForegroundColor Green
} else {
    Write-Host "Server MySQL: FAILED" -ForegroundColor Red
}

Write-Host "Database sync setup test completed!" -ForegroundColor Cyan