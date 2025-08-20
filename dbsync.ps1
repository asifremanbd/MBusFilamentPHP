# Database Sync Script - Clean Version
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
    Write-Host "Database Sync Script"
    Write-Host "Usage: .\dbsync.ps1 [option]"
    Write-Host ""
    Write-Host "Options:"
    Write-Host "  -Push    Push local database to server"
    Write-Host "  -Pull    Pull server database to local"
    Write-Host "  -Test    Test connections"
    Write-Host "  -Help    Show this help"
}

function Test-Connections {
    Write-Log "Testing connections..."
    
    # Test SSH
    Write-Host "Testing SSH..." -ForegroundColor Yellow
    $sshResult = ssh "$SERVER_USER@$SERVER_IP" "echo OK"
    if ($sshResult -eq "OK") {
        Write-Host "SSH connection works" -ForegroundColor Green
    } else {
        Write-Host "SSH connection failed" -ForegroundColor Red
        return
    }
    
    # Test local MySQL
    Write-Host "Testing local MySQL..." -ForegroundColor Yellow
    $localResult = mysql -u$LOCAL_DB_USER -e "SELECT 1" 2>&1
    if ($LASTEXITCODE -eq 0) {
        Write-Host "Local MySQL works" -ForegroundColor Green
    } else {
        Write-Host "Local MySQL failed" -ForegroundColor Red
        return
    }
    
    # Test server MySQL
    Write-Host "Testing server MySQL..." -ForegroundColor Yellow
    $serverResult = ssh "$SERVER_USER@$SERVER_IP" "mysql -u$SERVER_DB_USER -p$SERVER_DB_PASS -e 'SELECT 1'"
    if ($serverResult -match "1") {
        Write-Host "Server MySQL works" -ForegroundColor Green
    } else {
        Write-Host "Server MySQL failed" -ForegroundColor Red
        return
    }
    
    Write-Log "All connections successful!"
}

function Push-Database {
    Write-Log "Pushing local database to server..."
    
    # Create backup directory
    if (-not (Test-Path "backups")) {
        New-Item -ItemType Directory -Path "backups" | Out-Null
    }
    
    $timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
    $dumpFile = "backups\local_dump_$timestamp.sql"
    
    # Create local dump
    Write-Log "Creating local dump..."
    mysqldump -u$LOCAL_DB_USER $LOCAL_DB_NAME > $dumpFile
    
    if (Test-Path $dumpFile) {
        Write-Host "Local dump created: $dumpFile" -ForegroundColor Green
    } else {
        Write-Host "Failed to create local dump" -ForegroundColor Red
        return
    }
    
    # Transfer to server
    Write-Log "Transferring to server..."
    scp $dumpFile "$SERVER_USER@$SERVER_IP`:~/"
    
    if ($LASTEXITCODE -eq 0) {
        Write-Host "File transferred to server" -ForegroundColor Green
    } else {
        Write-Host "Transfer failed" -ForegroundColor Red
        return
    }
    
    # Import on server using a different approach
    Write-Log "Importing on server..."
    $remoteFile = "local_dump_$timestamp.sql"
    
    # Use bash -c to handle the redirection properly
    ssh "$SERVER_USER@$SERVER_IP" "bash -c 'mysql -u$SERVER_DB_USER -p$SERVER_DB_PASS $SERVER_DB_NAME < $remoteFile'"
    
    if ($LASTEXITCODE -eq 0) {
        Write-Host "Database imported on server" -ForegroundColor Green
        # Clean up remote file
        ssh "$SERVER_USER@$SERVER_IP" "rm $remoteFile"
        Write-Log "Push completed successfully!"
    } else {
        Write-Host "Import failed on server" -ForegroundColor Red
    }
}

function Pull-Database {
    Write-Log "Pulling server database to local..."
    
    # Create backup directory
    if (-not (Test-Path "backups")) {
        New-Item -ItemType Directory -Path "backups" | Out-Null
    }
    
    $timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
    $remoteFile = "server_dump_$timestamp.sql"
    $localFile = "backups\$remoteFile"
    
    # Create server dump
    Write-Log "Creating server dump..."
    ssh "$SERVER_USER@$SERVER_IP" "mysqldump -u$SERVER_DB_USER -p$SERVER_DB_PASS $SERVER_DB_NAME > $remoteFile"
    
    if ($LASTEXITCODE -eq 0) {
        Write-Host "Server dump created" -ForegroundColor Green
    } else {
        Write-Host "Failed to create server dump" -ForegroundColor Red
        return
    }
    
    # Transfer from server
    Write-Log "Transferring from server..."
    scp "$SERVER_USER@$SERVER_IP`:~/$remoteFile" "backups\"
    
    if ($LASTEXITCODE -eq 0) {
        Write-Host "File transferred from server" -ForegroundColor Green
    } else {
        Write-Host "Transfer failed" -ForegroundColor Red
        return
    }
    
    # Import locally using cmd for proper redirection
    Write-Log "Importing locally..."
    cmd /c "mysql -u$LOCAL_DB_USER $LOCAL_DB_NAME < $localFile"
    
    if ($LASTEXITCODE -eq 0) {
        Write-Host "Database imported locally" -ForegroundColor Green
        # Clean up remote file
        ssh "$SERVER_USER@$SERVER_IP" "rm $remoteFile"
        Write-Log "Pull completed successfully!"
    } else {
        Write-Host "Import failed locally" -ForegroundColor Red
    }
}

# Main execution
if ($Help) {
    Show-Help
} elseif ($Test) {
    Test-Connections
} elseif ($Push) {
    Push-Database
} elseif ($Pull) {
    Pull-Database
} else {
    Show-Help
}