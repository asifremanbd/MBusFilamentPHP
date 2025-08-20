# Deploy Devices to Production Server
# This script adds all devices from your table to the production database

param(
    [string]$ServerIP = "165.22.112.94",
    [string]$Username = "root",
    [string]$KeyPath = "~/.ssh/id_rsa"
)

Write-Host "Deploying devices to production server..." -ForegroundColor Green

# Copy the device addition script to production
Write-Host "Copying device script to production..." -ForegroundColor Yellow
scp add_all_devices.php ${Username}@${ServerIP}:/root/

# Copy the updated Modbus configuration
Write-Host "Copying updated Modbus config..." -ForegroundColor Yellow
scp updated_modbus_config.json ${Username}@${ServerIP}:/root/

# Copy verification script
Write-Host "Copying verification script..." -ForegroundColor Yellow
scp verify_production_devices.php ${Username}@${ServerIP}:/root/

# Execute the deployment on production server
Write-Host "Executing deployment on production server..." -ForegroundColor Yellow

$deployScript = @"
#!/bin/bash
set -e

echo "Starting device deployment on production server..."

# Navigate to the energy monitor directory
cd /home/ubuntu/energy-monitor

# Run the device addition script
echo "Adding devices to database..."
php /home/ubuntu/add_all_devices.php

# Backup current Modbus config
echo "Backing up current Modbus configuration..."
cp /home/ubuntu/python-modbus-service/config.json /home/ubuntu/python-modbus-service/config.json.backup

# Update Modbus service configuration
echo "Updating Modbus service configuration..."
cp /home/ubuntu/updated_modbus_config.json /home/ubuntu/python-modbus-service/config.json

# Restart Modbus service if it's running
echo "Restarting Modbus service..."
if pgrep -f "python.*modbus" > /dev/null; then
    pkill -f "python.*modbus"
    sleep 2
fi

# Start the Modbus service
cd /home/ubuntu/python-modbus-service
nohup python3 modbus_service.py > modbus.log 2>&1 &

echo "Modbus service restarted with PID: \$!"

# Restart Laravel queue workers if running
echo "Restarting Laravel queue workers..."
cd /home/ubuntu/energy-monitor
if pgrep -f "artisan.*queue" > /dev/null; then
    pkill -f "artisan.*queue"
    sleep 2
fi

# Start queue worker
nohup php artisan queue:work --daemon > queue.log 2>&1 &
echo "Queue worker started with PID: \$!"

# Clear Laravel cache
echo "Clearing Laravel cache..."
php artisan cache:clear
php artisan config:clear
php artisan view:clear

# Check database connection and show device count
echo "Verifying deployment..."
php artisan tinker --execute="
echo 'Total devices: ' . App\Models\Device::count() . PHP_EOL;
echo 'Total registers: ' . App\Models\Register::count() . PHP_EOL;
echo 'Recent devices:' . PHP_EOL;
App\Models\Device::latest()->take(5)->get(['id', 'name', 'slave_id', 'location_tag'])->each(function(\$device) {
    echo '- ' . \$device->name . ' (Slave ID: ' . \$device->slave_id . ') at ' . \$device->location_tag . PHP_EOL;
});
"

echo "Deployment completed successfully!"
echo "You can now access the web application to see your devices."
"@

# Write the deployment script to a temporary file
$deployScript | Out-File -FilePath "temp_deploy.sh" -Encoding UTF8

# Copy and execute the deployment script on the server
scp -i $KeyPath temp_deploy.sh ${Username}@${ServerIP}:/home/ubuntu/
ssh -i $KeyPath ${Username}@${ServerIP} "chmod +x /home/ubuntu/temp_deploy.sh && /home/ubuntu/temp_deploy.sh"

# Clean up temporary file
Remove-Item "temp_deploy.sh" -Force

Write-Host "`nDeployment completed!" -ForegroundColor Green
Write-Host "Your devices have been added to the production database." -ForegroundColor Cyan
Write-Host "The Modbus service has been updated and restarted." -ForegroundColor Cyan
Write-Host "`nNext steps:" -ForegroundColor Yellow
Write-Host "1. Access your web application to verify the devices are visible" -ForegroundColor White
Write-Host "2. Check that data is being collected from the Modbus devices" -ForegroundColor White
Write-Host "3. Configure any additional IP addresses for your actual device locations" -ForegroundColor White

# Show connection command for manual verification
Write-Host "`nTo manually verify on the server:" -ForegroundColor Yellow
Write-Host "ssh -i $KeyPath ${Username}@${ServerIP}" -ForegroundColor Cyan