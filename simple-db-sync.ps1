# Simple Database Sync Script - Reliable PowerShell Version
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
$LOCAL_DB_PASS = ""
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
    Write-Host "Simple Database Sync"
    Write-Host "Usage: .\simple-db-sync.ps1 [option]"
    Write-Host ""
    Write-Host "Options:"
    Write-Host "  -Push    Push local database to server"
    Write-Host "  -Pull    Pull server database to local"
    Write-Host "  -Test    Test database connections"
    Write-Host "  -Help    Show this help"
    Write-Host ""
    Write-Host "Examples:"
    Write-Host "  .\simple-db-sync.ps1 -Test"
    Write-Host "  .\simple-db-sync.ps1 -Push"
    Write-Host "  .\simple-db-sync.ps1 -Pull"
}

function Test-Connections {
    Write-Log "Testing connections..."
    
    # Test SSH
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
    
    # Test local database
    Write-Host "Testing local database..." -ForegroundColor Yellow
    try {
        if ($LOCAL_DB_PASS -eq "") {
            $localTest = mysql -u$LOCAL_DB_USER -e "SELECT 1" 2>&1
        } else {
            $localTest = mysql -u$LOCAL_DB_USER -p$LOCAL_DB_PASS -e "SELECT 1" 2>&1
        }
        
        if ($LASTEXITCODE -eq 0) {
            Write-Host "✓ Local database connection successful" -ForegroundColor Green
        } else {
            Write-Error "Local database connection failed: $localTest"
            return $false
        }
    }
    catch {
        Write-Error "Local database test failed: $_"
        return $false
    }
    
    # Test server database
    Write-Host "Testing server database..." -ForegroundColor Yellow
    try {
        $serverTest = ssh "$SERVER_USER@$SERVER_IP" "mysql -u$SERVER_DB_USER -p$SERVER_DB_PASS -e 'SELECT 1' 2>&1"
        if ($serverTest -match "1") {
            Write-Host "✓ Server database connection successful" -ForegroundColor Green
        } else {
            Write-Error "Server database connection failed: $serverTest"
            return $false
        }
    }
    catch {
        Write-Error "Server database test failed: $_"
        return $false
    }
    
    Write-Log "All connections successful!"
    return $true
}

function Push-Database {
    Write-Log "Pushing local database to server..."
    
    if (-not (Test-Connections)) {
        Write-Error "Connection test failed. Aborting sync."
        return
    }
    
    # Create backup directory if it doesn't exist
    if (-not (Test-Path "backups")) {
        New-Item -ItemType Directory -Path "backups" | Out-Null
    }
    
    $timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
    
    # Create server backup
    Write-Log "Creating server backup..."
    try {
        ssh "$SERVER_USER@$SERVER_IP" "mysqldump -u$SERVER_DB_USER -p$SERVER_DB_PASS $SERVER_DB_NAME > backup_server_$timestamp.sql"
        Write-Host "✓ Server backup created" -ForegroundColor Green
    }
    catch {
        Write-Error "Failed to create server backup: $_"
    }
    
    # Create local dump
    Write-Log "Creating local database dump..."
    try {
        if ($LOCAL_DB_PASS -eq "") {
            mysqldump -u$LOCAL_DB_USER $LOCAL_DB_NAME > "backups\local_to_server_$timestamp.sql"
        } else {
            mysqldump -u$LOCAL_DB_USER -p$LOCAL_DB_PASS $LOCAL_DB_NAME > "backups\local_to_server_$timestamp.sql"
        }
        Write-Host "✓ Local dump created: backups\local_to_server_$timestamp.sql" -ForegroundColor Green
    }
    catch {
        Write-Error "Failed to create local dump: $_"
        return
    }
    
    # Transfer to server
    Write-Log "Transferring to server..."
    try {
        scp "backups\local_to_server_$timestamp.sql" "$SERVER_USER@$SERVER_IP`:~/"
        Write-Host "✓ File transferred to server" -ForegroundColor Green
    }
    catch {
        Write-Error "Failed to transfer file: $_"
        return
    }
    
    # Import on server
    Write-Log "Importing database on server..."
    try {
        ssh "$SERVER_USER@$SERVER_IP" "mysql -u$SERVER_DB_USER -p$SERVER_DB_PASS $SERVER_DB_NAME `< local_to_server_$timestamp.sql"
        ssh "$SERVER_USER@$SERVER_IP" "rm local_to_server_$timestamp.sql"
        Write-Host "✓ Database imported successfully!" -ForegroundColor Green
    }
    catch {
        Write-Error "Failed to import database: $_"
        return
    }
    
    Write-Log "Database push completed successfully!"
}

function Pull-Database {
    Write-Log "Pulling server database to local..."
    
    if (-not (Test-Connections)) {
        Write-Error "Connection test failed. Aborting sync."
        return
    }
    
    # Create backup directory if it doesn't exist
    if (-not (Test-Path "backups")) {
        New-Item -ItemType Directory -Path "backups" | Out-Null
    }
    
    $timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
    
    # Create local backup
    Write-Log "Creating local backup..."
    try {
        if ($LOCAL_DB_PASS -eq "") {
            mysqldump -u$LOCAL_DB_USER $LOCAL_DB_NAME > "backups\backup_local_$timestamp.sql"
        } else {
            mysqldump -u$LOCAL_DB_USER -p$LOCAL_DB_PASS $LOCAL_DB_NAME > "backups\backup_local_$timestamp.sql"
        }
        Write-Host "✓ Local backup created: backups\backup_local_$timestamp.sql" -ForegroundColor Green
    }
    catch {
        Write-Error "Failed to create local backup: $_"
    }
    
    # Create server dump
    Write-Log "Creating server database dump..."
    try {
        ssh "$SERVER_USER@$SERVER_IP" "mysqldump -u$SERVER_DB_USER -p$SERVER_DB_PASS $SERVER_DB_NAME > server_to_local_$timestamp.sql"
        Write-Host "✓ Server dump created" -ForegroundColor Green
    }
    catch {
        Write-Error "Failed to create server dump: $_"
        return
    }
    
    # Transfer from server
    Write-Log "Transferring from server..."
    try {
        scp "$SERVER_USER@$SERVER_IP`:~/server_to_local_$timestamp.sql" "backups\"
        Write-Host "✓ File transferred from server" -ForegroundColor Green
    }
    catch {
        Write-Error "Failed to transfer file: $_"
        return
    }
    
    # Import locally using cmd to handle redirection properly
    Write-Log "Importing database locally..."
    try {
        if ($LOCAL_DB_PASS -eq "") {
            cmd /c "mysql -u$LOCAL_DB_USER $LOCAL_DB_NAME < backups\server_to_local_$timestamp.sql"
        } else {
            cmd /c "mysql -u$LOCAL_DB_USER -p$LOCAL_DB_PASS $LOCAL_DB_NAME < backups\server_to_local_$timestamp.sql"
        }
        
        if ($LASTEXITCODE -eq 0) {
            Write-Host "✓ Database imported successfully!" -ForegroundColor Green
        } else {
            Write-Error "Database import failed"
            return
        }
    }
    catch {
        Write-Error "Failed to import database: $_"
        return
    }
    
    # Clean up server file
    try {
        ssh "$SERVER_USER@$SERVER_IP" "rm server_to_local_$timestamp.sql"
    }
    catch {
        Write-Error "Failed to clean up server file: $_"
    }
    
    Write-Log "Database pull completed successfully!"
}

# Main execution
switch ($true) {
    $Push { Push-Database }
    $Pull { Pull-Database }
    $Test { Test-Connections }
    default { Show-Help }
}