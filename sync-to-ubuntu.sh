#!/bin/bash

# Energy Monitor Sync Script
# This script helps sync your GitHub repository with your Ubuntu server

# Configuration
GITHUB_REPO="https://github.com/yourusername/energy-monitor.git"
SERVER_USER="ubuntu"
SERVER_IP="your-server-ip"
SERVER_PATH="/var/www/energy-monitor"

# Display help information
show_help() {
    echo "Energy Monitor Sync Script"
    echo "Usage: $0 [options]"
    echo ""
    echo "Options:"
    echo "  -h, --help       Show this help message"
    echo "  -s, --setup      Initial setup (clone repository and install dependencies)"
    echo "  -u, --update     Update existing installation (pull latest changes)"
    echo "  -r, --restart    Restart services after update"
    echo ""
    echo "Example:"
    echo "  $0 --setup       # First-time setup"
    echo "  $0 --update      # Update existing installation"
    echo "  $0 --update --restart  # Update and restart services"
}

# Parse command line arguments
SETUP=false
UPDATE=false
RESTART=false

while [[ $# -gt 0 ]]; do
    key="$1"
    case $key in
        -h|--help)
            show_help
            exit 0
            ;;
        -s|--setup)
            SETUP=true
            shift
            ;;
        -u|--update)
            UPDATE=true
            shift
            ;;
        -r|--restart)
            RESTART=true
            shift
            ;;
        *)
            echo "Unknown option: $key"
            show_help
            exit 1
            ;;
    esac
done

# If no options provided, show help
if [[ "$SETUP" == false && "$UPDATE" == false ]]; then
    show_help
    exit 0
fi

# Initial setup
if [[ "$SETUP" == true ]]; then
    echo "=== Performing initial setup ==="
    
    # Create SSH command to run on the server
    ssh_command="
    echo 'Setting up Energy Monitor on Ubuntu server...'
    
    # Install required packages if not already installed
    sudo apt update
    sudo apt install -y git nginx mysql-server php php-cli php-fpm php-json php-common php-mysql php-zip php-gd php-mbstring php-curl php-xml php-pear php-bcmath python3 python3-pip
    
    # Clone the repository
    if [ ! -d '$SERVER_PATH' ]; then
        sudo mkdir -p '$SERVER_PATH'
        sudo chown $USER:$USER '$SERVER_PATH'
        git clone $GITHUB_REPO '$SERVER_PATH'
    else
        echo 'Directory already exists. Skipping clone.'
    fi
    
    # Set up the application
    cd '$SERVER_PATH'
    
    # Install Composer if not installed
    if ! command -v composer &> /dev/null; then
        php -r \"copy('https://getcomposer.org/installer', 'composer-setup.php');\"
        php composer-setup.php --install-dir=/usr/local/bin --filename=composer
        php -r \"unlink('composer-setup.php');\"
    fi
    
    # Install PHP dependencies
    composer install --no-interaction --prefer-dist --optimize-autoloader
    
    # Install Node.js dependencies and build assets
    npm install
    npm run build
    
    # Set up environment file if it doesn't exist
    if [ ! -f '.env' ]; then
        cp .env.example .env
        php artisan key:generate
        
        # Configure database settings (you'll need to edit this manually)
        echo 'Please configure your database settings in .env file'
    fi
    
    # Set proper permissions
    sudo chown -R $USER:www-data .
    sudo chmod -R 775 storage bootstrap/cache
    
    # Set up Python modbus service
    cd python-modbus-service
    pip3 install -r requirements.txt
    cd ..
    
    # Create Nginx configuration
    sudo tee /etc/nginx/sites-available/energy-monitor.conf > /dev/null << 'EOL'
server {
    listen 80;
    server_name your-domain.com;
    root $SERVER_PATH/public;

    add_header X-Frame-Options \"SAMEORIGIN\";
    add_header X-Content-Type-Options \"nosniff\";

    index index.php;

    charset utf-8;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \\.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\\.(?!well-known).* {
        deny all;
    }
}
EOL
    
    # Enable the site
    sudo ln -sf /etc/nginx/sites-available/energy-monitor.conf /etc/nginx/sites-enabled/
    sudo nginx -t && sudo systemctl restart nginx
    
    # Create a systemd service for the Laravel application
    sudo tee /etc/systemd/system/energy-monitor.service > /dev/null << 'EOL'
[Unit]
Description=Energy Monitor Laravel Application
After=network.target

[Service]
User=$USER
Group=www-data
WorkingDirectory=$SERVER_PATH
ExecStart=/usr/bin/php artisan serve --host=0.0.0.0 --port=8000
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
EOL
    
    # Create a systemd service for the Python modbus service
    sudo tee /etc/systemd/system/energy-monitor-modbus.service > /dev/null << 'EOL'
[Unit]
Description=Energy Monitor Modbus Service
After=network.target

[Service]
User=$USER
Group=www-data
WorkingDirectory=$SERVER_PATH/python-modbus-service
ExecStart=/usr/bin/python3 scheduler.py
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
EOL
    
    # Enable and start the services
    sudo systemctl daemon-reload
    sudo systemctl enable energy-monitor.service
    sudo systemctl enable energy-monitor-modbus.service
    sudo systemctl start energy-monitor.service
    sudo systemctl start energy-monitor-modbus.service
    
    echo 'Energy Monitor setup complete!'
    "
    
    # Execute the SSH command on the server
    ssh $SERVER_USER@$SERVER_IP "$ssh_command"
fi

# Update existing installation
if [[ "$UPDATE" == true ]]; then
    echo "=== Updating existing installation ==="
    
    # Create SSH command to run on the server
    ssh_command="
    echo 'Updating Energy Monitor on Ubuntu server...'
    
    # Pull latest changes from GitHub
    cd '$SERVER_PATH'
    git pull
    
    # Update dependencies
    composer install --no-interaction --prefer-dist --optimize-autoloader
    npm install
    npm run build
    
    # Run migrations
    php artisan migrate --force
    
    # Clear caches
    php artisan cache:clear
    php artisan config:clear
    php artisan view:clear
    
    # Update Python dependencies
    cd python-modbus-service
    pip3 install -r requirements.txt
    cd ..
    
    # Set proper permissions
    sudo chown -R $USER:www-data .
    sudo chmod -R 775 storage bootstrap/cache
    
    echo 'Energy Monitor update complete!'
    "
    
    # Execute the SSH command on the server
    ssh $SERVER_USER@$SERVER_IP "$ssh_command"
fi

# Restart services if requested
if [[ "$RESTART" == true ]]; then
    echo "=== Restarting services ==="
    
    # Create SSH command to run on the server
    ssh_command="
    echo 'Restarting Energy Monitor services...'
    
    # Restart services
    sudo systemctl restart energy-monitor.service
    sudo systemctl restart energy-monitor-modbus.service
    sudo systemctl restart nginx
    
    echo 'Services restarted!'
    "
    
    # Execute the SSH command on the server
    ssh $SERVER_USER@$SERVER_IP "$ssh_command"
fi

echo "=== Script execution complete ==="