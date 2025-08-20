#!/usr/bin/env python3
"""
Quick RTU Connection Test
Simple test to diagnose RTU communication issues
"""

import socket
import sys
import time
from datetime import datetime

def log(message):
    print(f"[{datetime.now().strftime('%H:%M:%S')}] {message}")

def test_rtu_connection():
    """Test RTU connection with minimal dependencies"""
    
    # RTU Configuration
    ip = "192.168.1.1"
    port = 502
    timeout = 10
    
    log(f"Testing RTU at {ip}:{port}")
    log("=" * 50)
    
    # Test 1: Basic socket connection
    log("1. Testing TCP socket connection...")
    try:
        sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        sock.settimeout(timeout)
        result = sock.connect_ex((ip, port))
        sock.close()
        
        if result == 0:
            log("   ✅ TCP connection successful")
            tcp_ok = True
        else:
            log(f"   ❌ TCP connection failed (error code: {result})")
            tcp_ok = False
    except Exception as e:
        log(f"   ❌ TCP connection error: {e}")
        tcp_ok = False
    
    # Test 2: Try Modbus if available
    log("2. Testing Modbus communication...")
    modbus_ok = False
    
    try:
        from pymodbus.client import ModbusTcpClient
        
        client = ModbusTcpClient(host=ip, port=port, timeout=timeout)
        
        if client.connect():
            log("   ✅ Modbus TCP connection established")
            
            # Try reading a basic register
            try:
                result = client.read_holding_registers(address=40001, count=2, slave=1)
                if not result.isError():
                    log(f"   ✅ Register read successful: {result.registers}")
                    modbus_ok = True
                else:
                    log(f"   ❌ Register read failed: {result}")
            except Exception as e:
                log(f"   ❌ Register read error: {e}")
            
            client.close()
        else:
            log("   ❌ Modbus TCP connection failed")
            
    except ImportError:
        log("   ⚠️  pymodbus not available - install with: pip install pymodbus")
    except Exception as e:
        log(f"   ❌ Modbus test error: {e}")
    
    # Test 3: Network diagnostics
    log("3. Network diagnostics...")
    try:
        import subprocess
        
        # Try ping (cross-platform)
        ping_cmd = ['ping', '-n', '1', ip] if sys.platform == 'win32' else ['ping', '-c', '1', ip]
        result = subprocess.run(ping_cmd, capture_output=True, text=True, timeout=10)
        
        if result.returncode == 0:
            log("   ✅ Ping successful")
        else:
            log("   ❌ Ping failed")
            
    except Exception as e:
        log(f"   ❌ Network diagnostic error: {e}")
    
    # Summary
    log("=" * 50)
    log("SUMMARY:")
    
    if tcp_ok and modbus_ok:
        log("✅ RTU is responding correctly")
        log("   → Check application configuration and logs")
        log("   → Verify data parsing logic")
        return True
    elif tcp_ok and not modbus_ok:
        log("⚠️  TCP connection works but Modbus communication failed")
        log("   → Check Modbus slave ID (try 1, 2, or 247)")
        log("   → Verify register addresses")
        log("   → Check RTU Modbus TCP configuration")
        return False
    else:
        log("❌ No connection to RTU")
        log("   → Check network connectivity")
        log("   → Verify RTU IP address and port")
        log("   → Check if RTU is powered on")
        return False

if __name__ == "__main__":
    success = test_rtu_connection()
    sys.exit(0 if success else 1)