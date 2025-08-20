# Energy Monitor Product Overview

Energy Monitor is a comprehensive energy monitoring system that provides real-time monitoring and management of energy consumption across multiple devices and gateways.

## Core Features

- **Real-time Energy Monitoring**: Track energy consumption from Modbus TCP devices
- **Dashboard Interface**: Built with Laravel Filament for intuitive data visualization
- **Gateway Management**: Manage multiple energy monitoring gateways and their devices
- **Alert System**: Configurable alerts for energy usage anomalies and thresholds
- **User Management**: Role-based access control with admin and user permissions
- **RTU Integration**: Support for Remote Terminal Units with register-based data collection
- **API Integration**: RESTful API for external system integration

## Architecture

The system consists of three main components:

1. **Laravel Web Application** (`energy-monitor/`): Main web interface and API
2. **Python Modbus Service** (`python-modbus-service/`): Polls Modbus devices and sends data to Laravel API
3. **Database Synchronization Tools**: PowerShell and Bash scripts for production deployment

## Target Users

- Energy managers monitoring industrial facilities
- System administrators managing energy infrastructure
- Operators requiring real-time energy consumption data
- Maintenance teams tracking device performance

## Key Business Value

- Reduces energy costs through monitoring and alerting
- Provides historical data for energy usage analysis
- Enables proactive maintenance through device monitoring
- Supports compliance reporting for energy management standards