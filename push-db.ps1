# Simple Database Push Script
Write-Host "Starting database push to server..." -ForegroundColor Green

# Create backup directory
if (-not (Test-Path "backups")) {
    New-Item -ItemType Directory -Path "backups"
    Write-Host "Created backups directory" -ForegroundColor Cyan
}

# Create timestamp
$timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
$dumpFile = "backups\push_$timestamp.sql"

# Create local dump
Write-Host "Creating local database dump..." -ForegroundColor Yellow
mysqldump -u root energy_monitor > $dumpFile

if (Test-Path $dumpFile) {
    Write-Host "Local dump created: $dumpFile" -ForegroundColor Green
    
    # Show file size
    $size = (Get-Item $dumpFile).Length
    Write-Host "Dump file size: $size bytes" -ForegroundColor Cyan
    
    # Transfer to server
    Write-Host "Transferring to server..." -ForegroundColor Yellow
    scp $dumpFile root@165.22.112.94:~/
    
    if ($LASTEXITCODE -eq 0) {
        Write-Host "File transferred successfully!" -ForegroundColor Green
        
        # Import on server
        Write-Host "Importing database on server..." -ForegroundColor Yellow
        ssh root@165.22.112.94 "cat push_$timestamp.sql | mysql -u energy_user -penergy_password energy_monitor"
        
        if ($LASTEXITCODE -eq 0) {
            Write-Host "Database imported successfully!" -ForegroundColor Green
            
            # Clean up server file
            ssh root@165.22.112.94 "rm push_$timestamp.sql"
            Write-Host "Database push completed successfully!" -ForegroundColor Green
        } else {
            Write-Host "Database import failed" -ForegroundColor Red
        }
    } else {
        Write-Host "File transfer failed" -ForegroundColor Red
    }
} else {
    Write-Host "Failed to create local dump" -ForegroundColor Red
}