#!/bin/bash

# Teltonika RTU Connection Test Deployment Script
# This script deploys the updated configuration and tests the Teltonika RTU connection

echo "===== Teltonika RTU Connection Test Deployment ====="
echo "Deploying updated Modbus configuration and testing Teltonika RTU at 192.168.1.1"

# Check if we're in the right directory
if [ ! -f "python-modbus-service/config.json" ]; then
    echo "❌ Error: python-modbus-service/config.json not found"
    echo "Please run this script from the energy-monitor root directory"
    exit 1
fi

# Backup existing configuration
echo "📁 Creating backup of existing configuration..."
cp python-modbus-service/config.json python-modbus-service/config.json.backup.$(date +%Y%m%d_%H%M%S)

# Check if Python dependencies are installed
echo "🔍 Checking Python dependencies..."
cd python-modbus-service

if ! python3 -c "import pymodbus" 2>/dev/null; then
    echo "📦 Installing Python dependencies..."
    pip3 install -r requirements.txt
fi

# Test the Teltonika RTU connection
echo "🔌 Testing Teltonika RTU connection..."
python3 test_teltonika_connection.py

if [ $? -eq 0 ]; then
    echo "✅ Teltonika RTU connection test successful!"
    
    # Run a single poll to test the full configuration
    echo "📊 Testing full Modbus polling with new configuration..."
    python3 poller.py
    
    if [ $? -eq 0 ]; then
        echo "✅ Full Modbus polling test successful!"
        echo "🚀 Ready to start scheduled polling"
        
        # Ask if user wants to start the scheduler
        read -p "Do you want to start the Modbus scheduler? (y/n): " start_scheduler
        if [ "$start_scheduler" = "y" ] || [ "$start_scheduler" = "Y" ]; then
            echo "🔄 Starting Modbus scheduler..."
            nohup python3 scheduler.py > scheduler.log 2>&1 &
            echo "✅ Scheduler started in background (PID: $!)"
            echo "📋 Check scheduler.log for ongoing status"
        fi
    else
        echo "❌ Full Modbus polling test failed"
        echo "Check the configuration and try again"
    fi
else
    echo "❌ Teltonika RTU connection test failed"
    echo "Please check:"
    echo "  - VPN connection to gateway"
    echo "  - Teltonika RTU IP address (192.168.1.1)"
    echo "  - Modbus TCP port (502)"
    echo "  - Network routing"
fi

cd ..

echo "===== Deployment Complete ====="