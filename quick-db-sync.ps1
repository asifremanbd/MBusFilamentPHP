# Quick Database Sync Script
# Simple interface for common database sync operations

param(
    [switch]$Push,
    [switch]$Pull,
    [switch]$Test,
    [switch]$Help
)

function Write-Log {
    param([string]$Message)
    Write-Host "[$(Get-Date -Format 'HH:mm:ss')] $Message" -ForegroundColor Green
}

function Show-Help {
    Write-Host "Quick Database Sync"
    Write-Host "Usage: .\quick-db-sync.ps1 [option]"
    Write-Host ""
    Write-Host "Options:"
    Write-Host "  -Push    Push local database to server"
    Write-Host "  -Pull    Pull server database to local"
    Write-Host "  -Test    Test database connections"
    Write-Host "  -Help    Show this help"
    Write-Host ""
    Write-Host "Examples:"
    Write-Host "  .\quick-db-sync.ps1 -Test"
    Write-Host "  .\quick-db-sync.ps1 -Push"
    Write-Host "  .\quick-db-sync.ps1 -Pull"
}

# Main execution
switch ($true) {
    $Push {
        Write-Log "Pushing database to server..."
        .\simple-db-sync.ps1 -Push
    }
    $Pull {
        Write-Log "Pulling database from server..."
        .\simple-db-sync.ps1 -Pull
    }
    $Test {
        Write-Log "Testing database connections..."
        .\simple-db-sync.ps1 -Test
    }
    default {
        Show-Help
    }
}