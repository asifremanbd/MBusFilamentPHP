#!/bin/bash

# Database Synchronization Script
# Syncs database between local and Ubuntu server

# Configuration - Update these values
LOCAL_DB_NAME="energy_monitor"
LOCAL_DB_USER="root"
LOCAL_DB_PASS=""  # Empty password from .env file

SERVER_USER="root"
SERVER_IP="165.22.112.94"
SERVER_DB_NAME="energy_monitor"
SERVER_DB_USER="root"
SERVER_DB_PASS="2tDEoBWefYLp.PYyPF"

# Show help
show_help() {
    echo "Database Sync Script"
    echo "Usage: $0 [option]"
    echo ""
    echo "Options:"
    echo "  --push-to-server    Push local database to server"
    echo "  --pull-from-server  Pull server database to local"
    echo "  --backup-local      Create local database backup"
    echo "  --backup-server     Create server database backup"
    echo ""
}

# Create local backup
backup_local() {
    echo "Creating local database backup..."
    mysqldump -u$LOCAL_DB_USER -p$LOCAL_DB_PASS $LOCAL_DB_NAME > "backup_local_$(date +%Y%m%d_%H%M%S).sql"
    echo "Local backup created!"
}

# Create server backup
backup_server() {
    echo "Creating server database backup..."
    ssh $SERVER_USER@$SERVER_IP "mysqldump -u$SERVER_DB_USER -p$SERVER_DB_PASS $SERVER_DB_NAME > backup_server_$(date +%Y%m%d_%H%M%S).sql"
    echo "Server backup created!"
}

# Push local database to server
push_to_server() {
    echo "Pushing local database to server..."
    
    # Create local dump
    mysqldump -u$LOCAL_DB_USER -p$LOCAL_DB_PASS $LOCAL_DB_NAME > temp_local_dump.sql
    
    # Transfer to server and import
    scp temp_local_dump.sql $SERVER_USER@$SERVER_IP:~/
    ssh $SERVER_USER@$SERVER_IP "mysql -u$SERVER_DB_USER -p$SERVER_DB_PASS $SERVER_DB_NAME < temp_local_dump.sql && rm temp_local_dump.sql"
    
    # Clean up
    rm temp_local_dump.sql
    
    echo "Database pushed to server!"
}

# Pull server database to local
pull_from_server() {
    echo "Pulling server database to local..."
    
    # Create server dump and transfer
    ssh $SERVER_USER@$SERVER_IP "mysqldump -u$SERVER_DB_USER -p$SERVER_DB_PASS $SERVER_DB_NAME > temp_server_dump.sql"
    scp $SERVER_USER@$SERVER_IP:~/temp_server_dump.sql ./
    
    # Import to local
    mysql -u$LOCAL_DB_USER -p$LOCAL_DB_PASS $LOCAL_DB_NAME < temp_server_dump.sql
    
    # Clean up
    rm temp_server_dump.sql
    ssh $SERVER_USER@$SERVER_IP "rm temp_server_dump.sql"
    
    echo "Database pulled from server!"
}

# Parse arguments
case "$1" in
    --push-to-server)
        backup_server
        push_to_server
        ;;
    --pull-from-server)
        backup_local
        pull_from_server
        ;;
    --backup-local)
        backup_local
        ;;
    --backup-server)
        backup_server
        ;;
    *)
        show_help
        ;;
esac