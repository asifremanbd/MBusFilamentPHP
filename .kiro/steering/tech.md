# Technology Stack & Build System

## Core Technologies

### Backend
- **Laravel 10.x** - PHP web framework with Eloquent ORM
- **PHP 8.1+** - Server-side language
- **Filament 3.0** - Admin panel and dashboard framework
- **MySQL/PostgreSQL** - Primary database
- **Laravel Sanctum** - API authentication

### Frontend
- **Vite** - Build tool and dev server
- **Laravel Blade** - Templating engine
- **Filament Components** - UI components and widgets
- **JavaScript/CSS** - Frontend assets

### Python Services
- **Python 3.x** - Modbus polling service
- **pymodbus 3.5.4** - Modbus TCP communication
- **APScheduler 3.10.4** - Task scheduling
- **requests 2.31.0** - HTTP client for API calls

### Infrastructure
- **XAMPP** - Local development environment (Windows)
- **Ubuntu Server** - Production deployment
- **SSH** - Remote server access
- **systemd** - Service management (Linux)

## Build Commands

### Laravel Application
```bash
# Navigate to Laravel directory
cd energy-monitor

# Install PHP dependencies
composer install

# Install Node.js dependencies
npm install

# Build frontend assets
npm run build

# Development build with watch
npm run dev

# Generate application key
php artisan key:generate

# Run database migrations
php artisan migrate

# Seed database
php artisan db:seed

# Clear caches
php artisan config:clear
php artisan cache:clear
php artisan view:clear

# Start development server
php artisan serve
```

### Python Service
```bash
# Navigate to Python service directory
cd python-modbus-service

# Install Python dependencies
pip install -r requirements.txt

# Run single polling cycle
python poller.py

# Run scheduled service
python scheduler.py
```

### Quick Start Scripts
- **Windows**: `start-app.bat` or `quick-start.bat`
- **Linux**: `./start-app.sh`

## Testing

### Laravel Tests
```bash
cd energy-monitor

# Run all tests
php artisan test

# Run specific test suite
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature

# Run with coverage
php artisan test --coverage
```

### Python Tests
```bash
cd python-modbus-service

# Test configuration
python test_poller.py

# Test device connections
python test_teltonika_connection.py
```

## Database Operations

### Local Development
```bash
# Create database backup
mysqldump -u root energy_monitor > backup.sql

# Restore from backup
mysql -u root energy_monitor < backup.sql
```

### Production Sync
```powershell
# Test database connections
.\quick-db-sync.ps1 -Test

# Push local to production
.\quick-db-sync.ps1 -Push

# Pull production to local
.\quick-db-sync.ps1 -Pull

# Complete deployment
.\production-sync.ps1 -DeployAll
```

## Development Environment Setup

### Prerequisites
- PHP 8.1+
- Composer
- Node.js & NPM
- MySQL (via XAMPP on Windows)
- Python 3.x
- Git

### Environment Configuration
1. Copy `.env.example` to `.env` in `energy-monitor/` directory
2. Configure database credentials
3. Set up Python service `.env` file
4. Configure Modbus device settings in `config.json`

## Deployment

### Production Deployment
- Use `deploy-ubuntu.sh` for initial server setup
- Use `sync-to-ubuntu.sh` for ongoing deployments
- Database sync via PowerShell scripts
- Service management via systemd

### Service Configuration
- Laravel runs on port 8000 (development) or via Nginx (production)
- Python service runs as background scheduler
- MySQL on standard port 3306