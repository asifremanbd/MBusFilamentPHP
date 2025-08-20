# MySQL Service Check Script

function Check-MySQLService {
    Write-Host "Checking MySQL service status..." -ForegroundColor Yellow
    
    $mysqlService = Get-Service -Name "*mysql*" -ErrorAction SilentlyContinue
    
    if ($mysqlService) {
        Write-Host "MySQL Service found: $($mysqlService.Name)" -ForegroundColor Green
        Write-Host "Status: $($mysqlService.Status)" -ForegroundColor $(if ($mysqlService.Status -eq "Running") { "Green" } else { "Red" })
        
        if ($mysqlService.Status -ne "Running") {
            Write-Host ""
            Write-Host "MySQL is not running. To start it:" -ForegroundColor Yellow
            Write-Host "1. Open PowerShell as Administrator" -ForegroundColor Cyan
            Write-Host "2. Run: Start-Service -Name '$($mysqlService.Name)'" -ForegroundColor Cyan
            Write-Host ""
            Write-Host "Or use Services.msc to start it manually" -ForegroundColor Cyan
            return $false
        } else {
            Write-Host "MySQL is running!" -ForegroundColor Green
            return $true
        }
    } else {
        Write-Host "No MySQL service found" -ForegroundColor Red
        return $false
    }
}

function Test-MySQLConnection {
    Write-Host "Testing MySQL connection..." -ForegroundColor Yellow
    
    try {
        $result = mysql -u root -e "SELECT 'Connection successful' as status" 2>&1
        
        if ($LASTEXITCODE -eq 0) {
            Write-Host "MySQL connection successful!" -ForegroundColor Green
            Write-Host $result
            return $true
        } else {
            Write-Host "MySQL connection failed:" -ForegroundColor Red
            Write-Host $result
            return $false
        }
    }
    catch {
        Write-Host "MySQL connection test failed: $_" -ForegroundColor Red
        return $false
    }
}

function Check-Database {
    Write-Host "Checking if energy_monitor database exists..." -ForegroundColor Yellow
    
    try {
        $result = mysql -u root -e "SHOW DATABASES LIKE 'energy_monitor'" 2>&1
        
        if ($result -match "energy_monitor") {
            Write-Host "Database 'energy_monitor' exists!" -ForegroundColor Green
        } else {
            Write-Host "Database 'energy_monitor' does not exist" -ForegroundColor Yellow
            Write-Host "To create it, run:" -ForegroundColor Cyan
            Write-Host "mysql -u root -e 'CREATE DATABASE energy_monitor;'" -ForegroundColor Cyan
        }
    }
    catch {
        Write-Host "Could not check database: $_" -ForegroundColor Red
    }
}

# Main execution
Write-Host "=== MySQL Setup Check ===" -ForegroundColor Cyan
Write-Host ""

if (Check-MySQLService) {
    if (Test-MySQLConnection) {
        Check-Database
        Write-Host ""
        Write-Host "MySQL setup looks good! You can now use the database sync scripts." -ForegroundColor Green
    }
} else {
    Write-Host ""
    Write-Host "Please start MySQL service first, then run this script again." -ForegroundColor Yellow
}