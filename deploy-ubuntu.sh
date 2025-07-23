#!/bin/bash

# Energy Monitor Deployment Script for Ubuntu
# This script helps deploy the Energy Monitor application on Ubuntu

echo "===== Energy Monitor Deployment Script ====="
echo "This script will help you deploy the Energy Monitor application on Ubuntu"

# Check if git is installed
if ! command -v git &> /dev/null; then
    echo "Git is not installed. Installing git..."
    sudo apt update
    sudo apt install -y git
fi

# Check if PHP is installed
if ! command -v php &> /dev/null; then
    echo "PHP is not installed. Installing PHP and required extensions..."
    sudo apt update
    sudo apt install -y php php-cli php-fpm php-json php-common php-mysql php-zip php-gd php-mbstring php-curl php-xml php-pear php-bcmath
fi

# Check if Composer is installed
if ! command -v composer &> /dev/null; then
    echo "Composer is not installed. Installing composer..."
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer
    php -r "unlink('composer-setup.php');"
fi

# Check if Node.js and npm are installed
if ! command -v node &> /dev/null; then
    echo "Node.js is not installed. Installing Node.js and npm..."
    sudo apt update
    sudo apt install -y nodejs npm
fi

# Check if Python is installed
if ! command -v python3 &> /dev/null; then
    echo "Python is not installed. Installing Python and pip..."
    sudo apt update
    sudo apt install -y python3 python3-pip
fi

# Setup application directory
echo "Setting up application directory..."
APP_DIR="energy-monitor"

# Check if the directory exists
if [ -d "$APP_DIR" ]; then
    echo "Directory $APP_DIR already exists. Updating from git..."
    cd $APP_DIR
    git pull
else
    echo "Cloning repository..."
    git clone https://github.com/yourusername/energy-monitor.git
    cd $APP_DIR
fi

# Install PHP dependencies
echo "Installing PHP dependencies..."
composer install --no-interaction --prefer-dist --optimize-autoloader

# Install Node.js dependencies and build assets
echo "Installing Node.js dependencies and building assets..."
npm install
npm run build

# Setup environment file if it doesn't exist
if [ ! -f ".env" ]; then
    echo "Setting up environment file..."
    cp .env.example .env
    php artisan key:generate
    
    # Ask for database configuration
    echo "Please enter your database configuration:"
    read -p "Database name (default: energy_monitor): " db_name
    db_name=${db_name:-energy_monitor}
    
    read -p "Database user (default: root): " db_user
    db_user=${db_user:-root}
    
    read -p "Database password: " db_password
    
    # Update .env file with database configuration
    sed -i "s/DB_DATABASE=.*/DB_DATABASE=$db_name/" .env
    sed -i "s/DB_USERNAME=.*/DB_USERNAME=$db_user/" .env
    sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=$db_password/" .env
fi

# Run database migrations
echo "Running database migrations..."
php artisan migrate

# Set up Python modbus service
echo "Setting up Python modbus service..."
cd python-modbus-service
pip3 install -r requirements.txt
cd ..

# Set proper permissions
echo "Setting proper permissions..."
sudo chown -R $USER:www-data .
sudo chmod -R 775 storage bootstrap/cache

# Create a simple startup script
echo "Creating startup script..."
cat > start-app.sh << 'EOL'
#!/bin/bash
cd "$(dirname "$0")"
php artisan serve --host=0.0.0.0 &
cd python-modbus-service
python3 scheduler.py &
echo "Energy Monitor application started!"
EOL

chmod +x start-app.sh

echo "===== Deployment Complete ====="
echo "You can start the application by running: ./start-app.sh"
echo "Access the application at: http://your-server-ip:8000"