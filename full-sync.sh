#!/bin/bash

# Complete Energy Monitor Synchronization Script
# Handles code, database, and configuration sync

# Configuration - Update these values
GITHUB_REPO="https://github.com/asifremanbd/MBusFilamentPHP.git"
SERVER_USER="root"
SERVER_IP="165.22.112.94"
SERVER_PATH="/MBusFilamentPHP"

# Database configuration
LOCAL_DB_NAME="energy_monitor"
LOCAL_DB_USER="root"
LOCAL_DB_PASS="your_local_password"
SERVER_DB_NAME="energy_monitor"
SERVER_DB_USER="root"
SERVER_DB_PASS="2tDEoBWefYLp.PYyPF"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Logging function
log() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')] $1${NC}"
}

warn() {
    echo -e "${YELLOW}[WARNING] $1${NC}"
}

error() {
    echo -e "${RED}[ERROR] $1${NC}"
}

# Show help
show_help() {
    echo "Complete Energy Monitor Sync Script"
    echo "Usage: $0 [options]"
    echo ""
    echo "Options:"
    echo "  --deploy-all        Complete deployment (code + database)"
    echo "  --sync-code         Sync code only (git push + server pull)"
    echo "  --sync-db-to-server Push local database to server"
    echo "  --sync-db-from-server Pull server database to local"
    echo "  --backup-all        Create backups of both local and server"
    echo "  --status            Check status of services on server"
    echo "  --logs              View server logs"
    echo ""
    echo "Development workflow:"
    echo "  1. Make changes locally"
    echo "  2. Test locally"
    echo "  3. Run: $0 --sync-code"
    echo "  4. If database changes: $0 --sync-db-to-server"
    echo ""
}

# Check if git repo is clean
check_git_status() {
    if [[ -n $(git status --porcelain) ]]; then
        warn "You have uncommitted changes. Please commit or stash them first."
        git status --short
        return 1
    fi
    return 0
}

# Sync code to server
sync_code() {
    log "Starting code synchronization..."
    
    # Check git status
    if ! check_git_status; then
        error "Please commit your changes before syncing."
        return 1
    fi
    
    # Push to GitHub
    log "Pushing to GitHub..."
    git push origin main || git push origin master
    
    # Pull on server
    log "Pulling changes on server..."
    ssh $SERVER_USER@$SERVER_IP "
        cd $SERVER_PATH
        git pull
        composer install --no-interaction --prefer-dist --optimize-autoloader
        npm install
        npm run build
        php artisan migrate --force
        php artisan cache:clear
        php artisan config:clear
        php artisan view:clear
        sudo chown -R $USER:www-data .
        sudo chmod -R 775 storage bootstrap/cache
    "
    
    log "Code synchronization complete!"
}

# Backup database
backup_database() {
    local location=$1
    log "Creating $location database backup..."
    
    if [[ "$location" == "local" ]]; then
        mysqldump -u$LOCAL_DB_USER -p$LOCAL_DB_PASS $LOCAL_DB_NAME > "backup_local_$(date +%Y%m%d_%H%M%S).sql"
    else
        ssh $SERVER_USER@$SERVER_IP "mysqldump -u$SERVER_DB_USER -p$SERVER_DB_PASS $SERVER_DB_NAME > backup_server_$(date +%Y%m%d_%H%M%S).sql"
    fi
    
    log "$location database backup created!"
}

# Sync database to server
sync_db_to_server() {
    log "Syncing database to server..."
    
    # Create backup first
    backup_database "server"
    
    # Create local dump
    log "Creating local database dump..."
    mysqldump -u$LOCAL_DB_USER -p$LOCAL_DB_PASS $LOCAL_DB_NAME > temp_local_dump.sql
    
    # Transfer and import
    log "Transferring and importing to server..."
    scp temp_local_dump.sql $SERVER_USER@$SERVER_IP:~/
    ssh $SERVER_USER@$SERVER_IP "
        mysql -u$SERVER_DB_USER -p$SERVER_DB_PASS $SERVER_DB_NAME < temp_local_dump.sql
        rm temp_local_dump.sql
    "
    
    # Clean up
    rm temp_local_dump.sql
    
    log "Database synced to server!"
}

# Sync database from server
sync_db_from_server() {
    log "Syncing database from server..."
    
    # Create backup first
    backup_database "local"
    
    # Create server dump and transfer
    log "Creating server database dump..."
    ssh $SERVER_USER@$SERVER_IP "mysqldump -u$SERVER_DB_USER -p$SERVER_DB_PASS $SERVER_DB_NAME > temp_server_dump.sql"
    
    log "Transferring and importing to local..."
    scp $SERVER_USER@$SERVER_IP:~/temp_server_dump.sql ./
    mysql -u$LOCAL_DB_USER -p$LOCAL_DB_PASS $LOCAL_DB_NAME < temp_server_dump.sql
    
    # Clean up
    rm temp_server_dump.sql
    ssh $SERVER_USER@$SERVER_IP "rm temp_server_dump.sql"
    
    log "Database synced from server!"
}

# Deploy everything
deploy_all() {
    log "Starting complete deployment..."
    
    sync_code
    if [[ $? -eq 0 ]]; then
        read -p "Do you want to sync database to server? (y/N): " -n 1 -r
        echo
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            sync_db_to_server
        fi
        
        # Restart services
        log "Restarting services..."
        ssh $SERVER_USER@$SERVER_IP "
            sudo systemctl restart energy-monitor.service
            sudo systemctl restart energy-monitor-modbus.service
            sudo systemctl restart nginx
        "
        
        log "Complete deployment finished!"
    else
        error "Code sync failed. Deployment aborted."
    fi
}

# Check server status
check_status() {
    log "Checking server status..."
    
    ssh $SERVER_USER@$SERVER_IP "
        echo '=== Service Status ==='
        sudo systemctl status energy-monitor.service --no-pager -l
        echo ''
        sudo systemctl status energy-monitor-modbus.service --no-pager -l
        echo ''
        sudo systemctl status nginx --no-pager -l
        echo ''
        echo '=== Disk Usage ==='
        df -h $SERVER_PATH
        echo ''
        echo '=== Memory Usage ==='
        free -h
    "
}

# View server logs
view_logs() {
    log "Viewing server logs..."
    
    ssh $SERVER_USER@$SERVER_IP "
        echo '=== Laravel Logs (last 50 lines) ==='
        tail -50 $SERVER_PATH/storage/logs/laravel.log
        echo ''
        echo '=== Energy Monitor Service Logs ==='
        sudo journalctl -u energy-monitor.service --no-pager -l -n 20
        echo ''
        echo '=== Modbus Service Logs ==='
        sudo journalctl -u energy-monitor-modbus.service --no-pager -l -n 20
    "
}

# Parse arguments
case "$1" in
    --deploy-all)
        deploy_all
        ;;
    --sync-code)
        sync_code
        ;;
    --sync-db-to-server)
        sync_db_to_server
        ;;
    --sync-db-from-server)
        sync_db_from_server
        ;;
    --backup-all)
        backup_database "local"
        backup_database "server"
        ;;
    --status)
        check_status
        ;;
    --logs)
        view_logs
        ;;
    *)
        show_help
        ;;
esac