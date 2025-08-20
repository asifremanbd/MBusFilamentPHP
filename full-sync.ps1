# Complete Energy Monitor Synchronization Script (PowerShell)
# Handles code, database, and configuration sync

param(
    [switch]$DeployAll,
    [switch]$SyncCode,
    [switch]$SyncDbToServer,
    [switch]$SyncDbFromServer,
    [switch]$BackupAll,
    [switch]$Status,
    [switch]$Logs,
    [switch]$Help
)

# Configuration - Update these values
$GITHUB_REPO = "https://github.com/asifremanbd/MBusFilamentPHP.git"
$SERVER_USER = "root"
$SERVER_IP = "165.22.112.94"
$SERVER_PATH = "/MBusFilamentPHP"

# Database configuration
$LOCAL_DB_NAME = "energy_monitor"
$LOCAL_DB_USER = "root"
$LOCAL_DB_PASS = ""  # Empty password from .env file
$SERVER_DB_NAME = "energy_monitor"
$SERVER_DB_USER = "root"
$SERVER_DB_PASS = "2tDEoBWefYLp.PYyPF"

# Logging functions
function Write-Log {
    param([string]$Message)
    Write-Host "[$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] $Message" -ForegroundColor Green
}

function Write-Warning {
    param([string]$Message)
    Write-Host "[WARNING] $Message" -ForegroundColor Yellow
}

function Write-Error {
    param([string]$Message)
    Write-Host "[ERROR] $Message" -ForegroundColor Red
}

# Show help
function Show-Help {
    Write-Host "Complete Energy Monitor Sync Script (PowerShell)"
    Write-Host "Usage: .\full-sync.ps1 [options]"
    Write-Host ""
    Write-Host "Options:"
    Write-Host "  -DeployAll          Complete deployment (code + database)"
    Write-Host "  -SyncCode           Sync code only (git push + server pull)"
    Write-Host "  -SyncDbToServer     Push local database to server"
    Write-Host "  -SyncDbFromServer   Pull server database to local"
    Write-Host "  -BackupAll          Create backups of both local and server"
    Write-Host "  -Status             Check status of services on server"
    Write-Host "  -Logs               View server logs"
    Write-Host ""
    Write-Host "Development workflow:"
    Write-Host "  1. Make changes locally"
    Write-Host "  2. Test locally"
    Write-Host "  3. Run: .\full-sync.ps1 -SyncCode"
    Write-Host "  4. If database changes: .\full-sync.ps1 -SyncDbToServer"
}

# Check git status
function Test-GitStatus {
    $status = git status --porcelain
    if ($status) {
        Write-Warning "You have uncommitted changes. Please commit or stash them first."
        git status --short
        return $false
    }
    return $true
}

# Sync code to server
function Sync-Code {
    Write-Log "Starting code synchronization..."
    
    # Check git status
    if (-not (Test-GitStatus)) {
        Write-Error "Please commit your changes before syncing."
        return
    }
    
    # Push to GitHub
    Write-Log "Pushing to GitHub..."
    try {
        git push origin main
    } catch {
        git push origin master
    }
    
    # Pull on server
    Write-Log "Pulling changes on server..."
    $sshCommand = @"
        cd $SERVER_PATH
        git pull
        composer install --no-interaction --prefer-dist --optimize-autoloader
        npm install
        npm run build
        php artisan migrate --force
        php artisan cache:clear
        php artisan config:clear
        php artisan view:clear
        sudo chown -R `$USER:www-data .
        sudo chmod -R 775 storage bootstrap/cache
"@
    
    ssh "$SERVER_USER@$SERVER_IP" $sshCommand
    
    Write-Log "Code synchronization complete!"
}

# Backup database
function Backup-Database {
    param([string]$Location)
    
    Write-Log "Creating $Location database backup..."
    
    $timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
    
    if ($Location -eq "local") {
        mysqldump -u$LOCAL_DB_USER -p$LOCAL_DB_PASS $LOCAL_DB_NAME > "backup_local_$timestamp.sql"
    } else {
        ssh "$SERVER_USER@$SERVER_IP" "mysqldump -u$SERVER_DB_USER -p$SERVER_DB_PASS $SERVER_DB_NAME > backup_server_$timestamp.sql"
    }
    
    Write-Log "$Location database backup created!"
}

# Sync database to server
function Sync-DbToServer {
    Write-Log "Syncing database to server..."
    
    # Create backup first
    Backup-Database "server"
    
    # Create local dump
    Write-Log "Creating local database dump..."
    mysqldump -u$LOCAL_DB_USER -p$LOCAL_DB_PASS $LOCAL_DB_NAME > temp_local_dump.sql
    
    # Transfer and import
    Write-Log "Transferring and importing to server..."
    scp temp_local_dump.sql "$SERVER_USER@$SERVER_IP`:~/"
    
    $sshCommand = @"
        mysql -u$SERVER_DB_USER -p$SERVER_DB_PASS $SERVER_DB_NAME < temp_local_dump.sql
        rm temp_local_dump.sql
"@
    
    ssh "$SERVER_USER@$SERVER_IP" $sshCommand
    
    # Clean up
    Remove-Item temp_local_dump.sql
    
    Write-Log "Database synced to server!"
}

# Sync database from server
function Sync-DbFromServer {
    Write-Log "Syncing database from server..."
    
    # Create backup first
    Backup-Database "local"
    
    # Create server dump and transfer
    Write-Log "Creating server database dump..."
    ssh "$SERVER_USER@$SERVER_IP" "mysqldump -u$SERVER_DB_USER -p$SERVER_DB_PASS $SERVER_DB_NAME > temp_server_dump.sql"
    
    Write-Log "Transferring and importing to local..."
    scp "$SERVER_USER@$SERVER_IP`:~/temp_server_dump.sql" ./
    mysql -u$LOCAL_DB_USER -p$LOCAL_DB_PASS $LOCAL_DB_NAME < temp_server_dump.sql
    
    # Clean up
    Remove-Item temp_server_dump.sql
    ssh "$SERVER_USER@$SERVER_IP" "rm temp_server_dump.sql"
    
    Write-Log "Database synced from server!"
}

# Deploy everything
function Deploy-All {
    Write-Log "Starting complete deployment..."
    
    Sync-Code
    
    $response = Read-Host "Do you want to sync database to server? (y/N)"
    if ($response -eq "y" -or $response -eq "Y") {
        Sync-DbToServer
    }
    
    # Restart services
    Write-Log "Restarting services..."
    $sshCommand = @"
        sudo systemctl restart energy-monitor.service
        sudo systemctl restart energy-monitor-modbus.service
        sudo systemctl restart nginx
"@
    
    ssh "$SERVER_USER@$SERVER_IP" $sshCommand
    
    Write-Log "Complete deployment finished!"
}

# Check server status
function Get-Status {
    Write-Log "Checking server status..."
    
    $sshCommand = @"
        echo '=== Service Status ==='
        sudo systemctl status energy-monitor.service --no-pager -l
        echo ''
        sudo systemctl status energy-monitor-modbus.service --no-pager -l
        echo ''
        sudo systemctl status nginx --no-pager -l
        echo ''
        echo '=== Disk Usage ==='
        df -h $SERVER_PATH
        echo ''
        echo '=== Memory Usage ==='
        free -h
"@
    
    ssh "$SERVER_USER@$SERVER_IP" $sshCommand
}

# View server logs
function Get-Logs {
    Write-Log "Viewing server logs..."
    
    $sshCommand = @"
        echo '=== Laravel Logs (last 50 lines) ==='
        tail -50 $SERVER_PATH/storage/logs/laravel.log
        echo ''
        echo '=== Energy Monitor Service Logs ==='
        sudo journalctl -u energy-monitor.service --no-pager -l -n 20
        echo ''
        echo '=== Modbus Service Logs ==='
        sudo journalctl -u energy-monitor-modbus.service --no-pager -l -n 20
"@
    
    ssh "$SERVER_USER@$SERVER_IP" $sshCommand
}

# Main execution
if ($Help -or (-not $DeployAll -and -not $SyncCode -and -not $SyncDbToServer -and -not $SyncDbFromServer -and -not $BackupAll -and -not $Status -and -not $Logs)) {
    Show-Help
} elseif ($DeployAll) {
    Deploy-All
} elseif ($SyncCode) {
    Sync-Code
} elseif ($SyncDbToServer) {
    Sync-DbToServer
} elseif ($SyncDbFromServer) {
    Sync-DbFromServer
} elseif ($BackupAll) {
    Backup-Database "local"
    Backup-Database "server"
} elseif ($Status) {
    Get-Status
} elseif ($Logs) {
    Get-Logs
}