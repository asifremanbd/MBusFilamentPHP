# Python Modbus Polling Service

A Python service that polls Modbus TCP devices and sends readings to a Laravel API endpoint.

## Features

- **Modbus TCP Support**: Connects to Modbus TCP devices via IP address
- **Multiple Data Types**: Supports float, int, uint16, uint32 data types
- **Configurable Registers**: JSON-based configuration for devices and registers
- **Scheduled Polling**: Runs every 30 minutes using APScheduler
- **API Integration**: Sends readings to Laravel `/api/readings` endpoint
- **Comprehensive Logging**: Detailed logs for monitoring and debugging
- **Error Handling**: Robust error handling with retry logic

## Installation

### 1. Install Python Dependencies

```bash
cd python-modbus-service
pip install -r requirements.txt
```

### 2. Configure Environment Variables

Create a `.env` file in the `python-modbus-service` directory:

```env
# Laravel API Configuration
LARAVEL_API_URL=http://localhost:8000/api/readings

# Modbus Configuration
MODBUS_CONFIG=config.json

# Optional: Logging Level
LOG_LEVEL=INFO
```

### 3. Configure Devices

Edit `config.json` to match your Modbus devices:

```json
[
  {
    "device_id": 1,
    "ip": "192.168.100.10",
    "port": 502,
    "slave_id": 1,
    "timeout": 10,
    "registers": [
      {
        "address": 40001,
        "parameter": "Voltage (L-N)",
        "data_type": "float",
        "scale": 1.0,
        "unit": "V",
        "description": "Line to Neutral Voltage"
      }
    ]
  }
]
```

## Usage

### Single Polling Cycle

Run a single polling cycle:

```bash
python poller.py
```

### Scheduled Polling

Run the scheduler for continuous polling every 30 minutes:

```bash
python scheduler.py
```

### Using Environment Variables

```bash
# Set custom API URL
export LARAVEL_API_URL=http://your-laravel-app.com/api/readings

# Set custom config file
export MODBUS_CONFIG=my_devices.json

# Run the service
python scheduler.py
```

## Configuration

### Device Configuration

Each device in `config.json` has the following properties:

- **device_id**: Unique identifier (must match Laravel database)
- **ip**: IP address of the Modbus device
- **port**: Modbus port (default: 502)
- **slave_id**: Modbus slave ID
- **timeout**: Connection timeout in seconds
- **registers**: Array of register configurations

### Register Configuration

Each register has the following properties:

- **address**: Modbus register address
- **parameter**: Parameter name (must match Laravel database)
- **data_type**: Data type (`float`, `int`, `uint16`, `uint32`)
- **scale**: Scale factor to apply to the value
- **unit**: Unit of measurement
- **description**: Human-readable description

### Data Types

- **float**: 32-bit floating point (requires 2 registers)
- **int**: 16-bit signed integer
- **uint16**: 16-bit unsigned integer
- **uint32**: 32-bit unsigned integer (requires 2 registers)

## API Integration

The service sends readings to the Laravel API in this format:

```json
{
  "device_id": 1,
  "parameter": "Voltage (L-N)",
  "value": 228.6,
  "unit": "V",
  "timestamp": "2025-07-08T16:00:00Z",
  "register_address": 40001,
  "data_type": "float",
  "description": "Line to Neutral Voltage"
}
```

## Logging

The service creates two log files:

- `modbus_poller.log`: Individual polling cycles
- `scheduler.log`: Scheduler operations

Log levels can be set via environment variable:

```bash
export LOG_LEVEL=DEBUG  # More detailed logging
```

## Error Handling

The service includes robust error handling:

- **Connection Failures**: Logs connection errors and continues with other devices
- **Register Read Errors**: Logs individual register failures and continues
- **API Errors**: Logs API communication errors
- **Configuration Errors**: Validates configuration on startup

## Troubleshooting

### Common Issues

1. **Connection Refused**
   - Check if the Modbus device is accessible
   - Verify IP address and port
   - Check firewall settings

2. **Register Read Errors**
   - Verify register addresses
   - Check if device supports the register
   - Verify data type configuration

3. **API Communication Errors**
   - Check Laravel API endpoint
   - Verify network connectivity
   - Check API authentication

### Debug Mode

Enable debug logging:

```bash
export LOG_LEVEL=DEBUG
python poller.py
```

### Test Configuration

Test your configuration without sending to API:

```bash
# Modify poller.py to add test mode
python poller.py --test
```

## Production Deployment

### Systemd Service (Linux)

Create `/etc/systemd/system/modbus-poller.service`:

```ini
[Unit]
Description=Modbus Polling Service
After=network.target

[Service]
Type=simple
User=modbus
WorkingDirectory=/opt/modbus-poller
Environment=PATH=/opt/modbus-poller/venv/bin
ExecStart=/opt/modbus-poller/venv/bin/python scheduler.py
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

Enable and start the service:

```bash
sudo systemctl enable modbus-poller
sudo systemctl start modbus-poller
```

### Windows Service

Use a tool like NSSM to create a Windows service:

```bash
nssm install ModbusPoller "C:\Python39\python.exe" "C:\modbus-poller\scheduler.py"
nssm start ModbusPoller
```

## Security Considerations

- Use HTTPS for API communication in production
- Implement API authentication
- Restrict network access to Modbus devices
- Use dedicated service accounts
- Monitor logs for suspicious activity

## Performance

- Each device is polled sequentially
- Connection timeouts prevent hanging
- Failed devices don't block others
- Logs are rotated to prevent disk space issues

## Monitoring

Monitor the service using:

- Log files: `modbus_poller.log`, `scheduler.log`
- System metrics: CPU, memory, network
- API response times
- Device connectivity status

## Support

For issues and questions:

1. Check the logs for error messages
2. Verify configuration syntax
3. Test network connectivity
4. Review API endpoint status 

## **🎯 NEXT STEPS - Complete Database Setup & API Security**

You're currently at **Step 9** with these immediate tasks:

### **1. Database Configuration (URGENT)**
From the conversation summary, you need to:

1. **Create `.env` file** in the `energy-monitor/` directory
2. **Configure database settings** for your XAMPP MySQL:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=energy_monitor
   DB_USERNAME=root
   DB_PASSWORD=asifreman
   ```

3. **Create database** in phpMyAdmin called `energy_monitor`

### **2. Run Database Migrations**
Once the database is configured:
```bash
cd energy-monitor
php artisan migrate
php artisan db:seed
```

### **3. Complete API Security (Step 9)**
- Generate API token for Python service
- Update Python service with authentication
- Test secured API connection

### **4. Final Polish (Step 10)**
- User profile management
- Alert resolution features
- Mobile responsiveness

## **🚀 IMMEDIATE ACTION NEEDED**

Would you like me to:

1. **Help you create the `.env` file** with proper database configuration?
2. **Guide you through the database setup** in phpMyAdmin?
3. **Run the migrations** once database is ready?
4. **Complete the API security setup**?

Which would you like to tackle first? The database configuration is the most critical blocker right now. 