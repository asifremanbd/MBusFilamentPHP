# Improved Energy Monitor Synchronization Script
param(
    [switch]$SyncCode,
    [switch]$TestConnection,
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
    Write-Host "Improved Energy Monitor Sync Script"
    Write-Host "Usage: .\improved-sync.ps1 [options]"
    Write-Host ""
    Write-Host "Options:"
    Write-Host "  -TestConnection     Test SSH connection to server"
    Write-Host "  -SyncCode          Complete code synchronization"
    Write-Host "  -Help              Show this help"
    Write-Host ""
    Write-Host "Examples:"
    Write-Host "  .\improved-sync.ps1 -TestConnection"
    Write-Host "  .\improved-sync.ps1 -SyncCode"
}

function Test-ServerConnection {
    Write-Log "Testing connection to server $SERVER_IP..."
    
    try {
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

function Sync-CodeComplete {
    Write-Log "Starting complete code synchronization..."
    
    # Check local git status
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
            Write-Host "Proceeding without committing local changes..." -ForegroundColor Yellow
        }
    }
    
    # Push to GitHub
    Write-Log "Pushing local changes to GitHub..."
    try {
        git push origin master
        Write-Log "Successfully pushed to GitHub!"
    } catch {
        Write-Error "Failed to push to GitHub. Please check your repository setup."
        return
    }
    
    # Test connection
    if (-not (Test-ServerConnection)) {
        Write-Error "Cannot connect to server. Aborting sync."
        return
    }
    
    # Sync on server using our simple sync script
    Write-Log "Syncing code on server..."
    
    try {
        ssh "$SERVER_USER@$SERVER_IP" "cd $SERVER_PATH && ./simple-sync.sh"
        Write-Log "Server sync completed successfully!"
    }
    catch {
        Write-Error "Failed to sync on server: $_"
        return
    }
    
    Write-Log "Complete synchronization finished successfully!"
}

# Main execution
if ($Help -or (-not $TestConnection -and -not $SyncCode)) {
    Show-Help
} elseif ($TestConnection) {
    Test-ServerConnection
} elseif ($SyncCode) {
    Sync-CodeComplete
}