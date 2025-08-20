#!/bin/bash

# Deploy and run RTU connection test on production server
# Usage: ./deploy_rtu_test.sh [server_ip] [username]

set -e

# Configuration
SERVER_IP=${1:-"your-production-server-ip"}
USERNAME=${2:-"ubuntu"}
REMOTE_PATH="/tmp/rtu_test"
LOCAL_TEST_FILE="test_rtu_production.py"

echo "=========================================="
echo "RTU Connection Test - Production Deployment"
echo "=========================================="
echo "Server: $USERNAME@$SERVER_IP"
echo "Remote path: $REMOTE_PATH"
echo "=========================================="

# Check if test file exists
if [ ! -f "$LOCAL_TEST_FILE" ]; then
    echo "❌ Error: $LOCAL_TEST_FILE not found"
    exit 1
fi

echo "📤 Uploading test script to production server..."

# Create remote directory and upload test script
ssh $USERNAME@$SERVER_IP "mkdir -p $REMOTE_PATH"
scp $LOCAL_TEST_FILE $USERNAME@$SERVER_IP:$REMOTE_PATH/

echo "✅ Test script uploaded successfully"

echo "🔧 Installing dependencies on production server..."

# Install Python dependencies if needed
ssh $USERNAME@$SERVER_IP << 'EOF'
    # Check if pymodbus is installed
    if ! python3 -c "import pymodbus" 2>/dev/null; then
        echo "Installing pymodbus..."
        pip3 install pymodbus --user
    else
        echo "pymodbus already installed"
    fi
EOF

echo "✅ Dependencies ready"

echo "🧪 Running RTU connection test on production server..."

# Run the test and capture output
ssh $USERNAME@$SERVER_IP "cd $REMOTE_PATH && python3 test_rtu_production.py" || {
    echo "❌ Test execution failed or found issues"
    echo "Check the output above for details"
}

echo "🧹 Cleaning up..."
ssh $USERNAME@$SERVER_IP "rm -rf $REMOTE_PATH"

echo "✅ RTU connection test completed"
echo "=========================================="