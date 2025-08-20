# Production Energy Monitor Synchronization Script
param(
    [switch]$SyncCode,
    [switch]$SyncDatabase,
    [switch]$DeployAll,
    [switch]$Status,
    [switch]$Logs,
    [switch]$Help
)

# Configuration
$SERVER_USER = "root"
$SERVER_IP = "165.22.112.94"
$SERVER_PATH = "/MBusFilamentPHP"
$GITHUB_REPO = "https://github.com/asifremanbd/MBusFilamentPHP.git"

# Database configuration
$LOCAL_DB_NAME = "energy_monitor"
$LOCAL_DB_USER = "root"
$LOCAL_DB_PASS = ""  # Empty password from .env file
$SERVER_DB_NAME = "energy_monitor"
$SERVER_DB_USER = "root"
$SERVER_DB_PASS = "2tDEoBWefYLp.PYyPF"

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

function Show-Help {
    Write-Host "Production Energy Monitor Sync Script"
    Write-Host "Usage: .\production-sync.ps1 [options]"
    Write-Host ""
    Write-Host "Options:"
    Write-Host "  -SyncCode          Sync code only (recommended for daily use)"
    Write-Host "  -SyncDatabase      Sync database to server"
    Write-Host "  -DeployAll         Complete deployment (code + database)"
    Write-Host "  -Status            Check server status"
    Write-Host "  -Logs              View server logs"
    Write-Host "  -Help              Show this help"
    Write-Host ""
    Write-Host "Daily workflow:"
    Write-Host "  1. Make changes locally and test"
    Write-Host "  2. Run: .\production-sync.ps1 -SyncCode"
    Write-Host "  3. If database changes: .\production-sync.ps1 -SyncDatabase"
    Write-Host ""
    Write-Host "Examples:"
    Write-Host "  .\production-sync.ps1 -SyncCode"
    Write-Host "  .\production-sync.ps1 -DeployAll"
    Write-Host "  .\production-sync.ps1 -Status"
}

function Test-ServerConnection {
    try {
        $result = ssh "$SERVER_USER@$SERVER_IP" "echo 'OK'"
        return $result -eq "OK"
    }
    catch {
        return $false
    }
}

function Sync-Code {
    Write-Log "Starting code synchronization..."
    
    # Check local git status
    $gitStatus = git status --porcelain
    if ($gitStatus) {
        Write-Warning "You have uncommitted changes:"
        git status --short
        $response = Read-Host "Commit them now? (y/N)"
        if ($response -eq "y" -or $response -eq "Y") {
            $commitMessage = Read-Host "Enter commit message"
            git add .
            git commit -m $commitMessage
        } else {
            Write-Warning "Proceeding without committing changes..."
        }
    }
    
    # Push to GitHub
    Write-Log "Pushing to GitHub..."
    try {
        git push origin master
        Write-Log "Successfully pushed to GitHub!"
    } catch {
        Write-Error "Failed to push to GitHub: $_"
        return $false
    }
    
    # Test connection
    if (-not (Test-ServerConnection)) {
        Write-Error "Cannot connect to server. Check your connection."
        return $false
    }
    
    # Sync on server
    Write-Log "Syncing on server..."
    try {
        ssh "$SERVER_USER@$SERVER_IP" "cd $SERVER_PATH && ./simple-sync.sh"
        Write-Log "Code sync completed successfully!"
        return $true
    }
    catch {
        Write-Error "Server sync failed: $_"
        return $false
    }
}

function Sync-Database {
    Write-Log "Starting database synchronization..."
    
    if (-not (Test-ServerConnection)) {
        Write-Error "Cannot connect to server."
        return $false
    }
    
    # Create local backup
    Write-Log "Creating local database backup..."
    $timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
    try {
        mysqldump -u$LOCAL_DB_USER -p$LOCAL_DB_PASS $LOCAL_DB_NAME > "backup_local_$timestamp.sql"
        Write-Log "Local backup created: backup_local_$timestamp.sql"
    } catch {
        Write-Error "Failed to create local backup: $_"
        return $false
    }
    
    # Create server backup
    Write-Log "Creating server database backup..."
    try {
        ssh "$SERVER_USER@$SERVER_IP" "mysqldump -u$SERVER_DB_USER -p$SERVER_DB_PASS $SERVER_DB_NAME > backup_server_$timestamp.sql"
        Write-Log "Server backup created"
    } catch {
        Write-Warning "Could not create server backup: $_"
    }
    
    # Transfer and import
    Write-Log "Transferring database to server..."
    try {
        scp "backup_local_$timestamp.sql" "$SERVER_USER@$SERVER_IP`:~/"
        ssh "$SERVER_USER@$SERVER_IP" "mysql -u$SERVER_DB_USER -p$SERVER_DB_PASS $SERVER_DB_NAME < backup_local_$timestamp.sql && rm backup_local_$timestamp.sql"
        Write-Log "Database synchronized successfully!"
        return $true
    }
    catch {
        Write-Error "Database sync failed: $_"
        return $false
    }
}

function Get-ServerStatus {
    Write-Log "Checking server status..."
    
    if (-not (Test-ServerConnection)) {
        Write-Error "Cannot connect to server."
        return
    }
    
    # Use simple commands to avoid line ending issues
    Write-Host "=== System Info ===" -ForegroundColor Cyan
    ssh "$SERVER_USER@$SERVER_IP" "uptime"
    ssh "$SERVER_USER@$SERVER_IP" "df -h $SERVER_PATH"
    
    Write-Host "`n=== Git Status ===" -ForegroundColor Cyan
    ssh "$SERVER_USER@$SERVER_IP" "cd $SERVER_PATH && git status -s"
    
    Write-Host "`n=== Recent Commits ===" -ForegroundColor Cyan
    ssh "$SERVER_USER@$SERVER_IP" "cd $SERVER_PATH && git log --oneline -5"
}

function Get-ServerLogs {
    Write-Log "Viewing server logs..."
    
    if (-not (Test-ServerConnection)) {
        Write-Error "Cannot connect to server."
        return
    }
    
    Write-Host "=== Laravel Logs (last 20 lines) ===" -ForegroundColor Cyan
    ssh "$SERVER_USER@$SERVER_IP" "if [ -f $SERVER_PATH/energy-monitor/storage/logs/laravel.log ]; then tail -20 $SERVER_PATH/energy-monitor/storage/logs/laravel.log; else echo 'No Laravel logs found'; fi"
    
    Write-Host "`n=== System Logs ===" -ForegroundColor Cyan
    ssh "$SERVER_USER@$SERVER_IP" "tail -10 /var/log/syslog"
}

function Deploy-All {
    Write-Log "Starting complete deployment..."
    
    if (Sync-Code) {
        $response = Read-Host "Sync database as well? (y/N)"
        if ($response -eq "y" -or $response -eq "Y") {
            Sync-Database
        }
        Write-Log "Complete deployment finished!"
    } else {
        Write-Error "Code sync failed. Deployment aborted."
    }
}

# Main execution
switch ($true) {
    $Help { Show-Help }
    $SyncCode { Sync-Code }
    $SyncDatabase { Sync-Database }
    $DeployAll { Deploy-All }
    $Status { Get-ServerStatus }
    $Logs { Get-ServerLogs }
    default { Show-Help }
}