# Simple Database Sync - PowerShell Compatible
param(
    [switch]$Push,
    [switch]$Pull,
    [switch]$Test,
    [switch]$Help
)

# Configuration
$SERVER_USER = "root"
$SERVER_IP = "165.22.112.94"
$LOCAL_DB_NAME = "energy_monitor"
$LOCAL_DB_USER = "root"
$SERVER_DB_NAME = "energy_monitor"
$SERVER_DB_USER = "root"
$SERVER_DB_PASS = "2tDEoBWefYLp.PYyPF"

function Write-Log {
    param([string]$Message)
    Write-Host "[$(Get-Date -Format 'HH:mm:ss')] $Message" -ForegroundColor Green
}

function Show-Help {
    Write-Host "Simple Database Sync"
    Write-Host "Usage: .\sync-now.ps1 [option]"
    Write-Host ""
    Write-Host "Options:"
    Write-Host "  -Push    Push local database to server"
    Write-Host "  -Pull    Pull server database to local"
    Write-Host "  -Test    Test connections"
    Write-Host "  -Help    Show this help"
    Write-Host ""
    Write-Host "Examples:"
    Write-Host "  .\sync-now.ps1 -Test"
    Write-Host "  .\sync-now.ps1 -Push"
    Write-Host "  .\sync-now.ps1 -Pull"
}

function Test-Connections {
    Write-Log "Testing all connections..."
    
    # Test local MySQL
    Write-Host "Testing local MySQL..." -ForegroundColor Yellow
    $localResult = mysql -u $LOCAL_DB_USER -e "SELECT 'OK' as status" 2>&1
    if ($LASTEXITCODE -eq 0) {
        Write-Host "✓ Local MySQL works" -ForegroundColor Green
    } else {
        Write-Host "✗ Local MySQL failed: $localResult" -ForegroundColor Red
        return
    }
    
    # Test SSH
    Write-Host "Testing SSH..." -ForegroundColor Yellow
    $sshResult = ssh "$SERVER_USER@$SERVER_IP" "echo 'SSH OK'"
    if ($sshResult -eq "SSH OK") {
        Write-Host "✓ SSH works" -ForegroundColor Green
    } else {
        Write-Host "✗ SSH failed" -ForegroundColor Red
        return
    }
    
    # Test server MySQL
    Write-Host "Testing server MySQL..." -ForegroundColor Yellow
    $serverResult = ssh "$SERVER_USER@$SERVER_IP" "mysql -u$SERVER_DB_USER -p$SERVER_DB_PASS -e 'SELECT 1' 2>/dev/null"
    if ($serverResult -match "1") {
        Write-Host "✓ Server MySQL works" -ForegroundColor Green
    } else {
        Write-Host "✗ Server MySQL failed" -ForegroundColor Red
        return
    }
    
    Write-Log "All connections successful! Ready to sync."
}

function Push-Database {
    Write-Log "Pushing local database to server..."
    
    # Create backup directory
    if (-not (Test-Path "backups")) {
        New-Item -ItemType Directory -Path "backups" | Out-Null
    }
    
    $timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
    
    # Create local dump
    Write-Log "Creating local dump..."
    mysqldump -u $LOCAL_DB_USER $LOCAL_DB_NAME > "backups\push_$timestamp.sql"
    Write-Host "✓ Local dump created" -ForegroundColor Green
    
    # Transfer to server
    Write-Log "Transferring to server..."
    scp "backups\push_$timestamp.sql" "$SERVER_USER@$SERVER_IP`:~/"
    Write-Host "✓ File transferred" -ForegroundColor Green
    
    # Import on server using a script approach
    Write-Log "Importing on server..."
    ssh "$SERVER_USER@$SERVER_IP" "cat push_$timestamp.sql | mysql -u$SERVER_DB_USER -p$SERVER_DB_PASS $SERVER_DB_NAME"
    ssh "$SERVER_USER@$SERVER_IP" "rm push_$timestamp.sql"
    Write-Host "✓ Database imported successfully!" -ForegroundColor Green
    Write-Log "Push completed!"
}

function Pull-Database {
    Write-Log "Pulling server database to local..."
    
    # Create backup directory
    if (-not (Test-Path "backups")) {
        New-Item -ItemType Directory -Path "backups" | Out-Null
    }
    
    $timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
    
    # Create server dump
    Write-Log "Creating server dump..."
    ssh "$SERVER_USER@$SERVER_IP" "mysqldump -u$SERVER_DB_USER -p$SERVER_DB_PASS $SERVER_DB_NAME > pull_$timestamp.sql"
    Write-Host "✓ Server dump created" -ForegroundColor Green
    
    # Transfer from server
    Write-Log "Transferring from server..."
    scp "$SERVER_USER@$SERVER_IP`:~/pull_$timestamp.sql" "backups\"
    Write-Host "✓ File transferred" -ForegroundColor Green
    
    # Import locally
    Write-Log "Importing locally..."
    cmd /c "mysql -u $LOCAL_DB_USER $LOCAL_DB_NAME < backups\pull_$timestamp.sql"
    ssh "$SERVER_USER@$SERVER_IP" "rm pull_$timestamp.sql"
    Write-Host "✓ Database imported successfully!" -ForegroundColor Green
    Write-Log "Pull completed!"
}

# Main execution
if ($Test) {
    Test-Connections
} elseif ($Push) {
    Push-Database
} elseif ($Pull) {
    Pull-Database
} else {
    Show-Help
}