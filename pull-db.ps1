# Simple Database Pull Script
Write-Host "Starting database pull from server..." -ForegroundColor Green

# Create backup directory
if (-not (Test-Path "backups")) {
    New-Item -ItemType Directory -Path "backups"
    Write-Host "Created backups directory" -ForegroundColor Cyan
}

# Create timestamp
$timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
$remoteFile = "pull_$timestamp.sql"
$localFile = "backups\$remoteFile"

# Create server dump
Write-Host "Creating server database dump..." -ForegroundColor Yellow
ssh root@165.22.112.94 "mysqldump -u energy_user -penergy_password energy_monitor > $remoteFile"

if ($LASTEXITCODE -eq 0) {
    Write-Host "Server dump created" -ForegroundColor Green
    
    # Transfer from server
    Write-Host "Transferring from server..." -ForegroundColor Yellow
    scp root@165.22.112.94:~/$remoteFile backups\
    
    if ($LASTEXITCODE -eq 0) {
        Write-Host "File transferred successfully!" -ForegroundColor Green
        
        # Show file size
        $size = (Get-Item $localFile).Length
        Write-Host "Downloaded file size: $size bytes" -ForegroundColor Cyan
        
        # Import locally
        Write-Host "Importing database locally..." -ForegroundColor Yellow
        cmd /c "mysql -u root energy_monitor < $localFile"
        
        if ($LASTEXITCODE -eq 0) {
            Write-Host "Database imported successfully!" -ForegroundColor Green
            
            # Clean up server file
            ssh root@165.22.112.94 "rm $remoteFile"
            Write-Host "Database pull completed successfully!" -ForegroundColor Green
        } else {
            Write-Host "Database import failed" -ForegroundColor Red
        }
    } else {
        Write-Host "File transfer failed" -ForegroundColor Red
    }
} else {
    Write-Host "Failed to create server dump" -ForegroundColor Red
}