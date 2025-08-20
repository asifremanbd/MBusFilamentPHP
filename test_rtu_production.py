#!/usr/bin/env python3
"""
Production RTU Connection Test
Test RTU gateway connection from production server environment
"""

import socket
import subprocess
import sys
import time
import json
from datetime import datetime

def log_message(message, level="INFO"):
    """Log message with timestamp"""
    timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    print(f"[{timestamp}] [{level}] {message}")

def test_ping(ip):
    """Test basic network connectivity"""
    log_message(f"Testing ping to {ip}...")
    try:
        # Linux ping command for production server
        result = subprocess.run(['ping', '-c', '4', ip], 
                              capture_output=True, text=True, timeout=30)
        if result.returncode == 0:
            log_message(f"✅ Ping successful to {ip}", "SUCCESS")
            return True
        else:
            log_message(f"❌ Ping failed to {ip}", "ERROR")
            log_message(f"Output: {result.stdout}", "DEBUG")
            return False
    except Exception as e:
        log_message(f"❌ Ping test error: {e}", "ERROR")
        return False

def test_tcp_port(ip, port, timeout=10):
    """Test if TCP port is open"""
    log_message(f"Testing TCP connection to {ip}:{port}...")
    try:
        sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        sock.settimeout(timeout)
        result = sock.connect_ex((ip, port))
        sock.close()
        
        if result == 0:
            log_message(f"✅ TCP port {port} is open on {ip}", "SUCCESS")
            return True
        else:
            log_message(f"❌ TCP port {port} is closed or filtered on {ip}", "ERROR")
            return False
    except Exception as e:
        log_message(f"❌ TCP port test error: {e}", "ERROR")
        return False

def test_modbus_connection(ip, port=502, slave_id=1, timeout=15):
    """Test Modbus TCP connection"""
    log_message(f"Testing Modbus connection to {ip}:{port} (Slave ID: {slave_id})...")
    
    try:
        from pymodbus.client import ModbusTcpClient
        from pymodbus.exceptions import ModbusException, ConnectionException
        
        client = ModbusTcpClient(host=ip, port=port, timeout=timeout)
        
        if not client.connect():
            log_message("❌ Failed to establish Modbus TCP connection", "ERROR")
            return False
        
        log_message("✅ Modbus TCP connection established", "SUCCESS")
        
        # Test reading a basic register
        test_registers = [
            (40001, "Voltage L1"),
            (40007, "Current L1"), 
            (40013, "Active Power"),
            (40021, "Total Energy")
        ]
        
        successful_reads = 0
        
        for address, description in test_registers:
            try:
                log_message(f"Testing register {address} ({description})...")
                result = client.read_holding_registers(address=address, count=2, slave=slave_id)
                
                if result.isError():
                    log_message(f"  ❌ Error reading register {address}: {result}", "ERROR")
                else:
                    log_message(f"  ✅ Successfully read register {address}: {result.registers}", "SUCCESS")
                    successful_reads += 1
                
                time.sleep(0.1)
                
            except Exception as e:
                log_message(f"  ❌ Error reading register {address}: {e}", "ERROR")
        
        client.close()
        
        if successful_reads > 0:
            log_message(f"✅ Modbus test completed: {successful_reads}/{len(test_registers)} registers read", "SUCCESS")
            return True
        else:
            log_message("❌ No registers could be read", "ERROR")
            return False
            
    except ImportError:
        log_message("❌ pymodbus library not available", "ERROR")
        return False
    except Exception as e:
        log_message(f"❌ Modbus connection error: {e}", "ERROR")
        return False

def check_network_interface():
    """Check network interface configuration"""
    log_message("Checking network interface configuration...")
    try:
        # Get network interface info
        result = subprocess.run(['ip', 'addr', 'show'], 
                              capture_output=True, text=True, timeout=10)
        log_message("Network interfaces:")
        for line in result.stdout.split('\n'):
            if 'inet ' in line and '127.0.0.1' not in line:
                log_message(f"  {line.strip()}")
        
        # Check routing table
        result = subprocess.run(['ip', 'route'], 
                              capture_output=True, text=True, timeout=10)
        log_message("Routing table:")
        for line in result.stdout.split('\n')[:5]:  # Show first 5 routes
            if line.strip():
                log_message(f"  {line.strip()}")
                
        return True
    except Exception as e:
        log_message(f"❌ Network interface check error: {e}", "ERROR")
        return False

def main():
    """Run production RTU connection test"""
    
    # RTU Configuration from config.json
    rtu_config = {
        "ip": "192.168.1.1",
        "port": 502,
        "slave_id": 1,
        "timeout": 15
    }
    
    log_message("=" * 60)
    log_message("PRODUCTION RTU CONNECTION TEST")
    log_message("=" * 60)
    log_message(f"Target RTU: {rtu_config['ip']}:{rtu_config['port']}")
    log_message(f"Slave ID: {rtu_config['slave_id']}")
    log_message("=" * 60)
    
    # Test results
    results = {}
    
    # Test 1: Network interface check
    results['network_interface'] = check_network_interface()
    
    # Test 2: Basic ping
    results['ping'] = test_ping(rtu_config['ip'])
    
    # Test 3: TCP port connectivity
    results['tcp_port'] = test_tcp_port(rtu_config['ip'], rtu_config['port'])
    
    # Test 4: Modbus connection
    results['modbus'] = test_modbus_connection(
        rtu_config['ip'], 
        rtu_config['port'], 
        rtu_config['slave_id'], 
        rtu_config['timeout']
    )
    
    # Summary
    log_message("=" * 60)
    log_message("TEST SUMMARY")
    log_message("=" * 60)
    
    total_tests = len(results)
    passed_tests = sum(1 for result in results.values() if result)
    
    for test_name, result in results.items():
        status = "✅ PASS" if result else "❌ FAIL"
        log_message(f"{test_name.upper()}: {status}")
    
    log_message(f"Overall: {passed_tests}/{total_tests} tests passed")
    
    # Recommendations
    log_message("=" * 60)
    log_message("RECOMMENDATIONS")
    log_message("=" * 60)
    
    if not results['ping']:
        log_message("🔧 Network connectivity issues:")
        log_message("   - Check if RTU is powered on")
        log_message("   - Verify network cables and switches")
        log_message("   - Check if production server can reach RTU network")
    
    if results['ping'] and not results['tcp_port']:
        log_message("🔧 TCP port issues:")
        log_message("   - Modbus TCP service may be disabled on RTU")
        log_message("   - Check RTU firewall settings")
        log_message("   - Verify Modbus TCP is enabled in RTU configuration")
    
    if results['tcp_port'] and not results['modbus']:
        log_message("🔧 Modbus protocol issues:")
        log_message("   - Check slave ID configuration (currently set to 1)")
        log_message("   - Verify register addresses in RTU documentation")
        log_message("   - Check Modbus TCP timeout settings")
    
    if all(results.values()):
        log_message("✅ All tests passed - RTU should be communicating properly")
        log_message("   - Check application logs for specific errors")
        log_message("   - Verify data parsing and storage logic")
    
    # Return exit code based on critical tests
    critical_tests = ['ping', 'tcp_port', 'modbus']
    critical_passed = all(results.get(test, False) for test in critical_tests)
    
    return 0 if critical_passed else 1

if __name__ == "__main__":
    exit_code = main()
    sys.exit(exit_code)