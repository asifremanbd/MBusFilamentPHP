# Energy Monitor

A comprehensive energy monitoring system built with Laravel and Filament PHP.

## Features

- Real-time energy consumption monitoring
- Dashboard with customizable widgets
- Gateway management
- Alert system for energy usage anomalies
- User management with role-based permissions
- Security monitoring and logging

## Requirements

- PHP 8.1+
- Composer
- Node.js & NPM
- MySQL/PostgreSQL database
- Python (for modbus service)

## Installation

### Quick Start

1. Clone the repository:
   ```
   git clone https://github.com/yourusername/energy-monitor.git
   cd energy-monitor
   ```

2. Install PHP dependencies:
   ```
   composer install
   ```

3. Install JavaScript dependencies:
   ```
   npm install && npm run build
   ```

4. Set up environment:
   ```
   cp energy-monitor/.env.example energy-monitor/.env
   php artisan key:generate
   ```

5. Configure your database in the `.env` file

6. Run migrations:
   ```
   php artisan migrate
   ```

7. Start the application:
   ```
   php artisan serve
   ```

### Using Quick Start Scripts

- Windows: `quick-start.bat` or `start-app.bat`
- Linux: `./start-app.sh`

## License

[Your License Here]