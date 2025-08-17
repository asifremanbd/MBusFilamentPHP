# Energy Monitor Synchronization Setup Guide

This guide will help you set up complete synchronization between your local development environment and Ubuntu production server.

## Prerequisites

### Local Environment (Windows)
- Git installed and configured
- MySQL/MariaDB running locally
- SSH client (built into Windows 10/11)
- PowerShell or Git Bash

### Ubuntu Server
- SSH access configured
- MySQL/MariaDB installed
- Web server (Nginx) configured
- PHP and Node.js installed

## Step 1: Configure SSH Key Authentication

1. **Generate SSH key pair** (if you don't have one):
   ```powershell
   ssh-keygen -t rsa -b 4096 -C "your-email@example.com"
   ```

2. **Copy public key to server**:
   ```powershell
   ssh-copy-id ubuntu@your-server-ip
   ```
   
   Or manually copy the content of `~/.ssh/id_rsa.pub` to `~/.ssh/authorized_keys` on the server.

3. **Test SSH connection**:
   ```powershell
   ssh ubuntu@your-server-ip
   ```

## Step 2: Configure Synchronization Scripts

### Update Configuration Variables

Edit the following files and update the configuration variables:

#### In `full-sync.ps1` (PowerShell version):
```powershell
$GITHUB_REPO = "https://github.com/yourusername/energy-monitor.git"
$SERVER_USER = "ubuntu"
$SERVER_IP = "your-server-ip"
$SERVER_PATH = "/var/www/energy-monitor"

# Database configuration
$LOCAL_DB_NAME = "energy_monitor"
$LOCAL_DB_USER = "root"
$LOCAL_DB_PASS = "your_local_password"
$SERVER_DB_NAME = "energy_monitor"
$SERVER_DB_USER = "energy_monitor_user"
$SERVER_DB_PASS = "your_server_password"
```

#### In `full-sync.sh` (Bash version):
```bash
GITHUB_REPO="https://github.com/yourusername/energy-monitor.git"
SERVER_USER="ubuntu"
SERVER_IP="your-server-ip"
SERVER_PATH="/var/www/energy-monitor"

# Database configuration
LOCAL_DB_NAME="energy_monitor"
LOCAL_DB_USER="root"
LOCAL_DB_PASS="your_local_password"
SERVER_DB_NAME="energy_monitor"
SERVER_DB_USER="energy_monitor_user"
SERVER_DB_PASS="your_server_password"
```

### Make Scripts Executable

#### Windows (PowerShell):
```powershell
# Allow script execution (run as Administrator)
Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser
```

#### Linux/Git Bash:
```bash
chmod +x full-sync.sh
chmod +x sync-database.sh
chmod +x sync-to-ubuntu.sh
```

## Step 3: Database Setup

### Local Database Setup
1. Create local database:
   ```sql
   CREATE DATABASE energy_monitor;
   CREATE USER 'energy_monitor_user'@'localhost' IDENTIFIED BY 'your_local_password';
   GRANT ALL PRIVILEGES ON energy_monitor.* TO 'energy_monitor_user'@'localhost';
   FLUSH PRIVILEGES;
   ```

### Server Database Setup
1. SSH into your server and create database:
   ```bash
   ssh ubuntu@your-server-ip
   sudo mysql
   ```
   
2. Run SQL commands:
   ```sql
   CREATE DATABASE energy_monitor;
   CREATE USER 'energy_monitor_user'@'localhost' IDENTIFIED BY 'your_server_password';
   GRANT ALL PRIVILEGES ON energy_monitor.* TO 'energy_monitor_user'@'localhost';
   FLUSH PRIVILEGES;
   EXIT;
   ```

## Step 4: Initial Deployment

### First-Time Setup
1. **Deploy application to server**:
   ```powershell
   .\full-sync.ps1 -DeployAll
   ```
   
   Or using bash:
   ```bash
   ./full-sync.sh --deploy-all
   ```

2. **Configure environment files** on server:
   ```bash
   ssh ubuntu@your-server-ip
   cd /var/www/energy-monitor
   cp .env.example .env
   nano .env  # Edit database and other settings
   php artisan key:generate
   php artisan migrate
   ```

## Step 5: Daily Development Workflow

### Recommended Workflow

1. **Make changes locally**
   - Edit code in your local environment
   - Test changes locally
   - Commit changes to Git

2. **Sync code to server**:
   ```powershell
   .\full-sync.ps1 -SyncCode
   ```

3. **If you made database changes**:
   ```powershell
   .\full-sync.ps1 -SyncDbToServer
   ```

4. **Check server status**:
   ```powershell
   .\full-sync.ps1 -Status
   ```

5. **View logs if needed**:
   ```powershell
   .\full-sync.ps1 -Logs
   ```

### Alternative: Complete Deployment
For major changes, use complete deployment:
```powershell
.\full-sync.ps1 -DeployAll
```

## Step 6: Backup Strategy

### Regular Backups
Create backups before major changes:
```powershell
.\full-sync.ps1 -BackupAll
```

### Automated Backups
Add to your server's crontab for daily backups:
```bash
# Edit crontab
crontab -e

# Add daily backup at 2 AM
0 2 * * * mysqldump -u energy_monitor_user -p'your_server_password' energy_monitor > /home/ubuntu/backups/daily_backup_$(date +\%Y\%m\%d).sql
```

## Step 7: Monitoring and Maintenance

### Check Service Status
```powershell
.\full-sync.ps1 -Status
```

### View Application Logs
```powershell
.\full-sync.ps1 -Logs
```

### Manual Server Commands
```bash
# SSH into server
ssh ubuntu@your-server-ip

# Check services
sudo systemctl status energy-monitor.service
sudo systemctl status energy-monitor-modbus.service
sudo systemctl status nginx

# Restart services if needed
sudo systemctl restart energy-monitor.service
sudo systemctl restart energy-monitor-modbus.service
sudo systemctl restart nginx

# View logs
tail -f /var/www/energy-monitor/storage/logs/laravel.log
sudo journalctl -u energy-monitor.service -f
```

## Troubleshooting

### Common Issues

1. **SSH Connection Issues**
   - Verify SSH key is properly configured
   - Check server IP and username
   - Ensure SSH service is running on server

2. **Database Connection Issues**
   - Verify database credentials
   - Check if MySQL is running on both local and server
   - Ensure database user has proper permissions

3. **Permission Issues on Server**
   - Run: `sudo chown -R $USER:www-data /var/www/energy-monitor`
   - Run: `sudo chmod -R 775 /var/www/energy-monitor/storage /var/www/energy-monitor/bootstrap/cache`

4. **Git Issues**
   - Ensure all changes are committed before syncing
   - Check if you have push permissions to the repository

### Getting Help

1. Check service logs: `.\full-sync.ps1 -Logs`
2. Check server status: `.\full-sync.ps1 -Status`
3. SSH into server for manual debugging: `ssh ubuntu@your-server-ip`

## Security Notes

- Never commit database passwords to Git
- Use environment variables for sensitive configuration
- Regularly update server packages: `sudo apt update && sudo apt upgrade`
- Monitor server logs for suspicious activity
- Use strong passwords for database users
- Consider setting up SSL certificates for production use