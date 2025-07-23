# Energy Monitor Deployment Guide

This guide explains how to deploy the Energy Monitor application to GitHub and sync it with an Ubuntu server.

## Prerequisites

- Git installed on your local machine
- GitHub account
- SSH access to your Ubuntu server
- Basic knowledge of Linux commands

## Deployment Steps

### 1. Push to GitHub

Follow the instructions in `github-setup.md` to create a GitHub repository and push your code to it.

### 2. Deploy to Ubuntu Server

You have two options for deploying to Ubuntu:

#### Option 1: Manual Deployment

Use the `deploy-ubuntu.sh` script included in the repository:

1. Copy the script to your Ubuntu server
2. Make it executable: `chmod +x deploy-ubuntu.sh`
3. Run the script: `./deploy-ubuntu.sh`

This script will:
- Install all required dependencies
- Clone the repository
- Set up the application
- Configure the environment
- Set up services

#### Option 2: Sync from Local Machine

Use the `sync-to-ubuntu.sh` script to sync your local repository with your Ubuntu server:

1. Edit the script to update the configuration variables:
   - `GITHUB_REPO`: Your GitHub repository URL
   - `SERVER_USER`: Your Ubuntu server username
   - `SERVER_IP`: Your Ubuntu server IP address
   - `SERVER_PATH`: The path where you want to deploy the application

2. Make the script executable:
   ```
   chmod +x sync-to-ubuntu.sh
   ```

3. For initial setup:
   ```
   ./sync-to-ubuntu.sh --setup
   ```

4. For updating an existing installation:
   ```
   ./sync-to-ubuntu.sh --update
   ```

5. To update and restart services:
   ```
   ./sync-to-ubuntu.sh --update --restart
   ```

## Post-Deployment Configuration

After deploying the application, you need to:

1. Configure the database:
   - Create a MySQL database
   - Update the `.env` file with your database credentials
   - Run migrations: `php artisan migrate`

2. Configure the web server:
   - Update the Nginx configuration with your domain name
   - Set up SSL certificates if needed

3. Configure the Python modbus service:
   - Update the configuration in `python-modbus-service/config.json`

## Troubleshooting

If you encounter issues during deployment:

1. Check the logs:
   - Laravel logs: `storage/logs/laravel.log`
   - Nginx logs: `/var/log/nginx/error.log`
   - PHP-FPM logs: `/var/log/php-fpm/error.log`
   - Systemd logs: `journalctl -u energy-monitor.service`

2. Verify permissions:
   - The `storage` and `bootstrap/cache` directories should be writable by the web server
   - Run: `sudo chown -R $USER:www-data .` and `sudo chmod -R 775 storage bootstrap/cache`

3. Check services status:
   - `sudo systemctl status energy-monitor.service`
   - `sudo systemctl status energy-monitor-modbus.service`
   - `sudo systemctl status nginx`