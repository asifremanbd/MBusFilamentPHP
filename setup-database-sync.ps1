# Database Synchronization Setup Script
# This script will help you configure database synchronization between local and server

param(
    [switch]$Setup,
    [switch]$TestLocal,
    [switch]$TestServer,
    [switch]$SyncToServer,
    [switch]$SyncFromServer,
    [switch]$Help
)

# Configuration - Update these values as needed
$SERVER_USER = "root"
$SERVER_IP = "165.22.112.94"
$SERVER_PATH = "/MBusFilamentPHP"

# Database configuration
$LOCAL_DB_NAME = "energy_monitor"
$LOCAL_DB_USER = "root"
$LOCAL_DB_PASS = ""  # Empty password from your .env file
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
    Write-Host "Database Synchronization Setup Script"
    Write-Host "Usage: .\setup-database-sync.ps1 [options]"
    Write-Host ""
    Write-Host "Options:"
    Write-Host "  -Setup              Initial setup and configuration check"
    Write-Host "  -TestLocal          Test local database connection"
    Write-Host "  -TestServer         Test server database connection"
    Write-Host "  -SyncToServer       Sync local database to server"
    Write-Host "  -SyncFromServer     Sync server database to local"
    Write-Host "  -Help               Show this help"
    Write-Host ""
    Write-Host "Setup workflow:"
    Write-Host "  1. Run: .\setup-database-sync.ps1 -Setup"
    Write-Host "  2. Test connections: .\setup-database-sync.ps1 -TestLocal"
    Write-Host "  3. Test server: .\setup-database-sync.ps1 -TestServer"
    Write-Host "  4. Sync as needed: .\setup-database-sync.ps1 -SyncToServer"
}

function Test-LocalDatabase {
    Write-Log "Testing local database connection..."
    
    try {
        if ($LOCAL_DB_PASS -eq "") {
            $result = mysql -u$LOCAL_DB_USER -e "SELECT 'Connection successful' as status; SHOW DATABASES;" 2>&1
        } else {
            $result = mysql -u$LOCAL_DB_USER -p$LOCAL_DB_PASS -e "SELECT 'Connection successful' as status; SHOW DATABASES;" 2>&1
        }
        
        if ($LASTEXITCODE -eq 0) {
            Write-Log "Local database connection successful!"
            Write-Host $result
            
            # Check if energy_monitor database exists
            if ($LOCAL_DB_PASS -eq "") {
                $dbExists = mysql -u$LOCAL_DB_USER -e "SHOW DATABASES LIKE '$LOCAL_DB_NAME';" 2>&1
            } else {
                $dbExists = mysql -u$LOCAL_DB_USER -p$LOCAL_DB_PASS -e "SHOW DATABASES LIKE '$LOCAL_DB_NAME';" 2>&1
            }
            
            if ($dbExists -match $LOCAL_DB_NAME) {
                Write-Log "Database '$LOCAL_DB_NAME' exists locally"
            } else {
                Write-Warning "Database '$LOCAL_DB_NAME' does not exist locally"
                Write-Host "You can create it with: CREATE DATABASE $LOCAL_DB_NAME;"
            }
            return $true
        } else {
            Write-Error "Local database connection failed"
            return $false
        }
    }
    catch {
        Write-Error "Local database test failed: $_"
        return $false
    }
}

function Test-ServerDatabase {
    Write-Log "Testing server database connection..."
    
    try {
        $sshCommand = "mysql -u$SERVER_DB_USER -p$SERVER_DB_PASS -e 'SELECT `"Connection successful`" as status; SHOW DATABASES;'"
        $result = ssh "$SERVER_USER@$SERVER_IP" $sshCommand
        
        if ($LASTEXITCODE -eq 0) {
            Write-Log "Server database connection successful!"
            Write-Host $result
            
            # Check if energy_monitor database exists on server
            $dbCheckCommand = "mysql -u$SERVER_DB_USER -p$SERVER_DB_PASS -e 'SHOW DATABASES LIKE `"$SERVER_DB_NAME`";'"
            $dbExists = ssh "$SERVER_USER@$SERVER_IP" $dbCheckCommand
            
            if ($dbExists -match $SERVER_DB_NAME) {
                Write-Log "Database '$SERVER_DB_NAME' exists on server"
            } else {
                Write-Warning "Database '$SERVER_DB_NAME' does not exist on server"
                Write-Host "You can create it by SSH'ing to server and running: CREATE DATABASE $SERVER_DB_NAME;"
            }
            return $true
        } else {
            Write-Error "Server database connection failed"
            return $false
        }
    }
    catch {
        Write-Error "Server database test failed: $_"
        return $false
    }
}

function Setup-DatabaseSync {
    Write-Log "Setting up database synchronization..."
    
    # Test SSH connection
    Write-Log "Testing SSH connection to server..."
    try {
        $sshTest = ssh "$SERVER_USER@$SERVER_IP" "echo 'SSH connection successful'"
        if ($sshTest -eq "SSH connection successful") {
            Write-Log "SSH connection successful!"
        } else {
            Write-Error "SSH connection failed"
            return
        }
    }
    catch {
        Write-Error "SSH connection failed: $_"
        return
    }
    
    # Test local database
    if (-not (Test-LocalDatabase)) {
        Write-Error "Local database setup required. Please ensure MySQL is running and accessible."
        return
    }
    
    # Test server database
    if (-not (Test-ServerDatabase)) {
        Write-Error "Server database setup required. Please check server database configuration."
        return
    }
    
    # Check if backup directory exists
    if (-not (Test-Path "backups")) {
        New-Item -ItemType Directory -Path "backups"
        Write-Log "Created backups directory"
    }
    
    Write-Log "Database synchronization setup complete!"
    Write-Log "You can now use:"
    Write-Log "  .\setup-database-sync.ps1 -SyncToServer    # Push local DB to server"
    Write-Log "  .\setup-database-sync.ps1 -SyncFromServer  # Pull server DB to local"
}

function Sync-DatabaseToServer {
    Write-Log "Syncing local database to server..."
    
    # Create timestamp for backups
    $timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
    
    # Create server backup first
    Write-Log "Creating server database backup..."
    try {
        $backupCommand = "mysqldump -u$SERVER_DB_USER -p$SERVER_DB_PASS $SERVER_DB_NAME > backup_server_$timestamp.sql"
        ssh "$SERVER_USER@$SERVER_IP" $backupCommand
        Write-Log "Server backup created: backup_server_$timestamp.sql"
    }
    catch {
        Write-Warning "Could not create server backup: $_"
    }
    
    # Create local dump
    Write-Log "Creating local database dump..."
    try {
        if ($LOCAL_DB_PASS -eq "") {
            mysqldump -u$LOCAL_DB_USER $LOCAL_DB_NAME > "backups\local_dump_$timestamp.sql"
        } else {
            mysqldump -u$LOCAL_DB_USER -p$LOCAL_DB_PASS $LOCAL_DB_NAME > "backups\local_dump_$timestamp.sql"
        }
        Write-Log "Local dump created: backups\local_dump_$timestamp.sql"
    }
    catch {
        Write-Error "Failed to create local dump: $_"
        return
    }
    
    # Transfer and import to server
    Write-Log "Transferring database to server..."
    try {
        scp "backups\local_dump_$timestamp.sql" "$SERVER_USER@$SERVER_IP`:~/"
        
        $importCommand = "mysql -u$SERVER_DB_USER -p$SERVER_DB_PASS $SERVER_DB_NAME `< local_dump_$timestamp.sql && rm local_dump_$timestamp.sql"
        ssh "$SERVER_USER@$SERVER_IP" $importCommand
        
        Write-Log "Database successfully synced to server!"
    }
    catch {
        Write-Error "Failed to sync database to server: $_"
    }
}

function Sync-DatabaseFromServer {
    Write-Log "Syncing server database to local..."
    
    # Create timestamp for backups
    $timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
    
    # Create local backup first
    Write-Log "Creating local database backup..."
    try {
        if ($LOCAL_DB_PASS -eq "") {
            mysqldump -u$LOCAL_DB_USER $LOCAL_DB_NAME > "backups\backup_local_$timestamp.sql"
        } else {
            mysqldump -u$LOCAL_DB_USER -p$LOCAL_DB_PASS $LOCAL_DB_NAME > "backups\backup_local_$timestamp.sql"
        }
        Write-Log "Local backup created: backups\backup_local_$timestamp.sql"
    }
    catch {
        Write-Warning "Could not create local backup: $_"
    }
    
    # Create server dump and transfer
    Write-Log "Creating server database dump..."
    try {
        $dumpCommand = "mysqldump -u$SERVER_DB_USER -p$SERVER_DB_PASS $SERVER_DB_NAME > server_dump_$timestamp.sql"
        ssh "$SERVER_USER@$SERVER_IP" $dumpCommand
        
        scp "$SERVER_USER@$SERVER_IP`:~/server_dump_$timestamp.sql" "backups\"
        
        # Import to local
        Write-Log "Importing server database to local..."
        if ($LOCAL_DB_PASS -eq "") {
            cmd /c "mysql -u$LOCAL_DB_USER $LOCAL_DB_NAME < backups\server_dump_$timestamp.sql"
        } else {
            cmd /c "mysql -u$LOCAL_DB_USER -p$LOCAL_DB_PASS $LOCAL_DB_NAME < backups\server_dump_$timestamp.sql"
        }
        
        # Clean up server file
        ssh "$SERVER_USER@$SERVER_IP" "rm server_dump_$timestamp.sql"
        
        Write-Log "Database successfully synced from server!"
    }
    catch {
        Write-Error "Failed to sync database from server: $_"
    }
}

# Main execution
switch ($true) {
    $Help { Show-Help }
    $Setup { Setup-DatabaseSync }
    $TestLocal { Test-LocalDatabase }
    $TestServer { Test-ServerDatabase }
    $SyncToServer { Sync-DatabaseToServer }
    $SyncFromServer { Sync-DatabaseFromServer }
    default { Show-Help }
}