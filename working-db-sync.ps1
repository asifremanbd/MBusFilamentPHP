# Working Database Sync Script
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

function Write-Error {
    param([string]$Message)
    Write-Host "[ERROR] $Message" -ForegroundColor Red
}

function Show-Help {
    Write-Host "Working Database Sync Script"
    Write-Host "Usage: .\working-db-sync.ps1 [option]"
    Write-Host ""
    Write-Host "Options:"
    Write-Host "  -Push    Push local database to server"
    Write-Host "  -Pull    Pull server database to local"
    Write-Host "  -Test    Test database connections"
    Write-Host "  -Help    Show this help"
    Write-Host ""
    Write-Host "Examples:"
    Write-Host "  .\working-db-sync.ps1 -Test"
    Write-Host "  .\working-db-sync.ps1 -Push"
    Write-Host "  .\working-db-sync.ps1 -Pull"
}

function Test-Connections {
    Write-Log "Testing all connections..."
    
    # Test local MySQL
    Write-Host "Testing local MySQL..." -ForegroundColor Yellow
    try {
        $result = mysql -u $LOCAL_DB_USER -e "SELECT 'OK' as status" 2>&1
        if ($LASTEXITCODE -eq 0) {
            Write-Host "✓ Local MySQL connection successful" -ForegroundColor Green
        } else {
            Write-Error "Local MySQL connection failed: $result"
            return $false
        }
    }
    catch {
        Write-Error "Local MySQL test failed: $_"
        return $false
    }
    
    # Test SSH connection
    Write-Host "Testing SSH connection..." -ForegroundColor Yellow
    try {
        $sshResult = ssh "$SERVER_USER@$SERVER_IP" "echo 'SSH OK'"
        if ($sshResult -eq "SSH OK") {
            Write-Host "✓ SSH connection successful" -ForegroundColor Green
        } else {
            Write-Error "SSH connection failed"
            return $false
        }
    }
    catch {
        Write-Error "SSH test failed: $_"
        return $false
    }
    
    # Test server MySQL
    Write-Host "Testing server MySQL..." -ForegroundColor Yellow
    try {
        $serverResult = ssh "$SERVER_USER@$SERVER_IP" "mysql -u$SERVER_DB_USER -p$SERVER_DB_PASS -e 'SELECT 1' 2>/dev/null"
        if ($serverResult -match "1") {
            Write-Host "✓ Server MySQL connection successful" -ForegroundColor Green
        } else {
            Write-Error "Server MySQL connection failed"
            return $false
        }
    }
    catch {
        Write-Error "Server MySQL test failed: $_"
        return $false
    }
    
    Write-Log "All connections successful! Database sync is ready to use."
    return $true
}

function Push-Database {
    Write-Log "Pushing local database to server..."
    
    # Create backup directory
    if (-not (Test-Path "backups")) {
        New-Item -ItemType Directory -Path "backups" | Out-Null
        Write-Host "Created backups directory" -ForegroundColor Cyan
    }
    
    $timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
    $dumpFile = "backups\push_$timestamp.sql"
    
    # Create local dump
    Write-Log "Creating local database dump..."
    try {
        mysqldump -u $LOCAL_DB_USER $LOCAL_DB_NAME > $dumpFile
        if (Test-Path $dumpFile) {
            Write-Host "✓ Local dump created: $dumpFile" -ForegroundColor Green
        } else {
            Write-Error "Failed to create local dump"
            return
        }
    }
    catch {
        Write-Error "Failed to create local dump: $_"
        return
    }
    
    # Transfer to server
    Write-Log "Transferring to server..."
    try {
        scp $dumpFile "$SERVER_USER@$SERVER_IP`:~/"
        if ($LASTEXITCODE -eq 0) {
            Write-Host "✓ File transferred to server" -ForegroundColor Green
        } else {
            Write-Error "Transfer failed"
            return
        }
    }
    catch {
        Write-Error "Transfer failed: $_"
        return
    }
    
    # Import on server
    Write-Log "Importing database on server..."
    $remoteFile = "push_$timestamp.sql"
    try {
        ssh "$SERVER_USER@$SERVER_IP" "mysql -u$SERVER_DB_USER -p$SERVER_DB_PASS $SERVER_DB_NAME < $remoteFile"
        if ($LASTEXITCODE -eq 0) {
            Write-Host "✓ Database imported on server" -ForegroundColor Green
            # Clean up remote file
            ssh "$SERVER_USER@$SERVER_IP" "rm $remoteFile"
            Write-Log "Database push completed successfully!"
        } else {
            Write-Error "Import failed on server"
        }
    }
    catch {
        Write-Error "Import failed: $_"
    }
}

function Pull-Database {
    Write-Log "Pulling server database to local..."
    
    # Create backup directory
    if (-not (Test-Path "backups")) {
        New-Item -ItemType Directory -Path "backups" | Out-Null
        Write-Host "Created backups directory" -ForegroundColor Cyan
    }
    
    $timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
    $remoteFile = "pull_$timestamp.sql"
    $localFile = "backups\$remoteFile"
    
    # Create server dump
    Write-Log "Creating server database dump..."
    try {
        ssh "$SERVER_USER@$SERVER_IP" "mysqldump -u$SERVER_DB_USER -p$SERVER_DB_PASS $SERVER_DB_NAME > $remoteFile"
        if ($LASTEXITCODE -eq 0) {
            Write-Host "✓ Server dump created" -ForegroundColor Green
        } else {
            Write-Error "Failed to create server dump"
            return
        }
    }
    catch {
        Write-Error "Failed to create server dump: $_"
        return
    }
    
    # Transfer from server
    Write-Log "Transferring from server..."
    try {
        scp "$SERVER_USER@$SERVER_IP`:~/$remoteFile" "backups\"
        if ($LASTEXITCODE -eq 0) {
            Write-Host "✓ File transferred from server" -ForegroundColor Green
        } else {
            Write-Error "Transfer failed"
            return
        }
    }
    catch {
        Write-Error "Transfer failed: $_"
        return
    }
    
    # Import locally
    Write-Log "Importing database locally..."
    try {
        cmd /c "mysql -u $LOCAL_DB_USER $LOCAL_DB_NAME < $localFile"
        if ($LASTEXITCODE -eq 0) {
            Write-Host "✓ Database imported locally" -ForegroundColor Green
            # Clean up remote file
            ssh "$SERVER_USER@$SERVER_IP" "rm $remoteFile"
            Write-Log "Database pull completed successfully!"
        } else {
            Write-Error "Import failed locally"
        }
    }
    catch {
        Write-Error "Import failed: $_"
    }
}

# Main execution
switch ($true) {
    $Test { Test-Connections }
    $Push { Push-Database }
    $Pull { Pull-Database }
    default { Show-Help }
}