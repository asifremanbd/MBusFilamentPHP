# Database Sync Menu
Write-Host "=== Database Synchronization Menu ===" -ForegroundColor Cyan
Write-Host ""
Write-Host "Available options:" -ForegroundColor Yellow
Write-Host "1. Test connections (.\test-db.ps1)"
Write-Host "2. Push local database to server (.\push-db.ps1)"
Write-Host "3. Pull server database to local (.\pull-db.ps1)"
Write-Host ""
Write-Host "Quick commands:" -ForegroundColor Green
Write-Host "  .\test-db.ps1   - Test all connections"
Write-Host "  .\push-db.ps1   - Push local DB to server"
Write-Host "  .\pull-db.ps1   - Pull server DB to local"
Write-Host ""
Write-Host "Your setup:" -ForegroundColor Cyan
Write-Host "  Local DB: energy_monitor (MySQL via XAMPP)"
Write-Host "  Server: root@165.22.112.94"
Write-Host "  Server DB: energy_monitor"
Write-Host ""

$choice = Read-Host "Enter choice (1-3) or press Enter to exit"

switch ($choice) {
    "1" { 
        Write-Host "Running connection test..." -ForegroundColor Green
        .\test-db.ps1 
    }
    "2" { 
        Write-Host "Starting database push..." -ForegroundColor Green
        .\push-db.ps1 
    }
    "3" { 
        Write-Host "Starting database pull..." -ForegroundColor Green
        .\pull-db.ps1 
    }
    default { 
        Write-Host "Goodbye!" -ForegroundColor Green 
    }
}