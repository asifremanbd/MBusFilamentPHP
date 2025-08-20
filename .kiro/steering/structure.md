# Project Structure & Organization

## Root Directory Structure

```
├── energy-monitor/           # Main Laravel application
├── python-modbus-service/    # Python Modbus polling service
├── backups/                  # Database backup files
├── .kiro/                    # Kiro AI assistant configuration
├── .git/                     # Git repository data
└── [deployment scripts]     # Various PowerShell and Bash scripts
```

## Laravel Application (`energy-monitor/`)

### Standard Laravel Structure
```
energy-monitor/
├── app/
│   ├── Filament/            # Filament admin panel components
│   │   ├── Pages/           # Custom admin pages
│   │   ├── Resources/       # CRUD resources
│   │   └── Widgets/         # Dashboard widgets
│   ├── Http/
│   │   ├── Controllers/     # API and web controllers
│   │   └── Middleware/      # Custom middleware
│   ├── Models/              # Eloquent models
│   └── Services/            # Business logic services
├── database/
│   ├── factories/           # Model factories for testing
│   ├── migrations/          # Database schema migrations
│   └── seeders/             # Database seeders
├── resources/
│   ├── views/               # Blade templates
│   ├── js/                  # JavaScript assets
│   └── css/                 # CSS assets
├── routes/
│   ├── web.php              # Web routes
│   └── api.php              # API routes
├── tests/
│   ├── Unit/                # Unit tests
│   └── Feature/             # Feature tests
├── config/                  # Configuration files
├── storage/                 # File storage and logs
└── public/                  # Web-accessible files
```

### Key Application Components

#### Models
- **Gateway**: Represents energy monitoring gateways
- **Device**: Individual devices connected to gateways
- **Register**: Modbus register configurations
- **Reading**: Energy consumption readings
- **User**: System users with role-based permissions
- **Alert**: Alert rules and notifications

#### Services
- **RTUDataService**: Handles RTU (Remote Terminal Unit) data processing
- **ModbusService**: Manages Modbus communication
- **AlertService**: Processes and sends alerts

#### Filament Resources
- Gateway management interface
- Device configuration panels
- Reading visualization dashboards
- User management with RBAC
- Alert rule configuration

## Python Service (`python-modbus-service/`)

```
python-modbus-service/
├── poller.py               # Main polling logic
├── scheduler.py            # Scheduled task runner
├── config.json             # Device and register configuration
├── requirements.txt        # Python dependencies
├── .env                    # Environment variables
├── test_*.py              # Testing scripts
└── *.log                  # Log files
```

### Configuration Files
- **config.json**: Defines Modbus devices, IP addresses, registers, and data types
- **.env**: API endpoints and connection settings
- **requirements.txt**: Python package dependencies

## Deployment Scripts

### Database Synchronization
- **quick-db-sync.ps1**: Simple database sync interface
- **setup-database-sync.ps1**: Comprehensive sync setup
- **production-sync.ps1**: Full production deployment
- **full-sync.ps1/sh**: Complete synchronization scripts

### Application Deployment
- **deploy-ubuntu.sh**: Ubuntu server deployment
- **sync-to-ubuntu.sh**: Code synchronization to server
- **start-app.bat**: Windows application startup
- **quick-start.bat**: Quick development startup

### Testing & Diagnostics
- **test-*.py**: Python connection and device tests
- **check-mysql.ps1**: Database connectivity tests
- **verify_production_devices.php**: Production device verification

## Configuration Management

### Environment Files
- **energy-monitor/.env**: Laravel application configuration
- **python-modbus-service/.env**: Python service configuration
- **.env** (root): Global environment settings

### Key Configuration Areas
1. **Database**: Connection strings, credentials
2. **API**: Endpoints, authentication tokens
3. **Modbus**: Device IPs, register mappings, polling intervals
4. **Deployment**: Server credentials, paths, sync settings

## Development Workflow

### Local Development
1. Work in `energy-monitor/` for web application changes
2. Modify `python-modbus-service/` for device communication
3. Use deployment scripts for testing and production sync
4. Store backups in `backups/` directory

### File Naming Conventions
- **PowerShell scripts**: kebab-case with `.ps1` extension
- **Bash scripts**: kebab-case with `.sh` extension
- **PHP files**: PascalCase for classes, snake_case for functions
- **Python files**: snake_case throughout
- **Configuration**: lowercase with underscores or hyphens

### Testing Structure
- **Laravel tests**: Follow PSR-4 autoloading in `tests/` directory
- **Python tests**: Prefix with `test_` for easy identification
- **Integration tests**: Named by functionality (e.g., `test_rtu_production.py`)

## Data Flow Architecture

1. **Python Service** polls Modbus devices → sends data to **Laravel API**
2. **Laravel Application** stores readings → triggers alerts → updates dashboard
3. **Filament Interface** provides real-time visualization and management
4. **Database Sync Scripts** maintain production/development parity