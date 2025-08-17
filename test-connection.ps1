# Simple connection test script
param(
    [switch]$TestConnection,
    [switch]$SyncCode,
    [switch]$Help
)

# Configuration
$SERVER_USER = "root"
$SERVER_IP = "165.22.112.94"
$SERVER_PATH = "/MBusFilamentPHP"
$GITHUB_REPO = "https://github.com/asifremanbd/MBusFilamentPHP.git"

function Write-Log {
    param([string]$Message)
    Write-Host "[$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] $Message" -ForegroundColor Green
}

function Write-Error {
    param([string]$Message)
    Write-Host "[ERROR] $Message" -ForegroundColor Red
}

function Show-Help {
    Write-Host "Simple Connection Test Script"
    Write-Host "Usage: .\test-connection.ps1 [options]"
    Write-Host ""
    Write-Host "Options:"
    Write-Host "  -TestConnection     Test SSH connection to server"
    Write-Host "  -SyncCode          Sync code to server (git push + server pull)"
    Write-Host "  -Help              Show this help"
    Write-Host ""
    Write-Host "Examples:"
    Write-Host "  .\test-connection.ps1 -TestConnection"
    Write-Host "  .\test-connection.ps1 -SyncCode"
}

function Test-ServerConnection {
    Write-Log "Testing connection to server $SERVER_IP..."
    
    try {
        # Simple SSH command to test connection
        $result = ssh "$SERVER_USER@$SERVER_IP" "echo 'Connection successful'; whoami; pwd"
        Write-Log "Connection test successful!"
        Write-Host $result
        return $true
    }
    catch {
        Write-Error "Connection failed: $_"
        return $false
    }
}

function Sync-CodeToServer {
    Write-Log "Starting code synchronization..."
    
    # Check if we have uncommitted changes
    $gitStatus = git status --porcelain
    if ($gitStatus) {
        Write-Host "You have uncommitted changes:" -ForegroundColor Yellow
        git status --short
        $response = Read-Host "Do you want to commit them first? (y/N)"
        if ($response -eq "y" -or $response -eq "Y") {
            $commitMessage = Read-Host "Enter commit message"
            git add .
            git commit -m $commitMessage
        } else {
            Write-Host "Proceeding without committing changes..." -ForegroundColor Yellow
        }
    }
    
    # Push to GitHub
    Write-Log "Pushing to GitHub..."
    try {
        git push origin main
    } catch {
        try {
            git push origin master
        } catch {
            Write-Error "Failed to push to GitHub. Please check your repository setup."
            return
        }
    }
    
    # Test connection first
    if (-not (Test-ServerConnection)) {
        Write-Error "Cannot connect to server. Please check your credentials."
        return
    }
    
    # Sync on server
    Write-Log "Syncing code on server..."
    
    $sshCommands = @"
        echo 'Starting server sync...'
        
        # Check if directory exists, if not create and clone
        if [ ! -d '$SERVER_PATH' ]; then
            echo 'Creating directory and cloning repository...'
            mkdir -p '$SERVER_PATH'
            cd '$SERVER_PATH'
            git clone $GITHUB_REPO .
        else
            echo 'Directory exists, pulling latest changes...'
            cd '$SERVER_PATH'
            git pull origin main || git pull origin master
        fi
        
        echo 'Sync completed!'
        pwd
        ls -la
"@
    
    try {
        ssh "$SERVER_USER@$SERVER_IP" $sshCommands
        Write-Log "Code synchronization completed successfully!"
    }
    catch {
        Write-Error "Failed to sync code: $_"
    }
}

# Main execution
if ($Help -or (-not $TestConnection -and -not $SyncCode)) {
    Show-Help
} elseif ($TestConnection) {
    Test-ServerConnection
} elseif ($SyncCode) {
    Sync-CodeToServer
}