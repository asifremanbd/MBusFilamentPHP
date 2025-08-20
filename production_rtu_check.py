#!/usr/bin/env python3
"""
Production Server RTU Check
Run this script directly on the production server to test RTU connectivity
"""

import socket
import subprocess
import sys
import time
from datetime import datetime

def log(message, level="INFO"):
    timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    print(f"[{timestamp}] {message}")

def check_network_config():
    """Check production server network configuration"""
    log("Checking production server network configuration...")
    
    try:
        # Get IP configuration
        result = subprocess.run(['ip', 'addr', 'show'], capture_output=True, text=True, timeout=10)
        log("Network interfaces:")
        for line in result.stdout.split('\n'):
            if 'inet ' in line and '127.0.0.1' not in line:
                log(f"  {line.strip()}")
        
        # Check default route
        result = subprocess.run(['ip', 'route', 'show', 'default'], capture_output=True, text=True, timeout=10)
        log(f"Default route: {result.stdout.strip()}")
        
        return True
    except Exception as e:
        log(f"Network config check failed: {e}")
        return False

def test_rtu_ping(ip):
    """Test ping to RTU"""
    log(f"Testing ping to RTU {ip}...")
    
    try:
        result = subprocess.run(['ping', '-c', '4', '-W', '5', ip], 
                              capture_output=True, text=True, timeout=30)
        
        if result.returncode == 0:
            log("✅ Ping to RTU successful")
            # Extract ping statistics
            for line in result.stdout.split('\n'):
                if 'packets transmitted' in line or 'min/avg/max' in line:
                    log(f"  {line.strip()}")
            return True
        else:
            log("❌ Ping to RTU failed")
            log(f"Error output: {result.stderr}")
            return False
            
    except Exception as e:
        log(f"Ping test error: {e}")
        return False

def test_rtu_tcp(ip, port=502):
    """Test TCP connection to RTU"""
    log(f"Testing TCP connection to {ip}:{port}...")
    
    try:
        sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        sock.settimeout(10)
        result = sock.connect_ex((ip, port))
        sock.close()
        
        if result == 0:
            log("✅ TCP connection to RTU successful")
            return True
        else:
            log(f"❌ TCP connection failed (error: {result})")
            return False
            
    except Exception as e:
        log(f"TCP test error: {e}")
        return False

def test_rtu_modbus(ip, port=502, slave_id=1):
    """Test Modbus communication with RTU"""
    log(f"Testing Modbus communication with RTU {ip}:{port} (slave {slave_id})...")
    
    try:
        from pymodbus.client import ModbusTcpClient
        
        client = ModbusTcpClient(host=ip, port=port, timeout=15)
        
        if not client.connect():
            log("❌ Modbus connection failed")
            return False
        
        log("✅ Modbus connection established")
        
        # Test reading registers from config
        test_registers = [
            (40001, "Voltage L1"),
            (40003, "Voltage L2"), 
            (40005, "Voltage L3"),
            (40007, "Current L1"),
            (40013, "Active Power"),
            (40021, "Total Energy")
        ]
        
        successful_reads = 0
        
        for address, description in test_registers:
            try:
                result = client.read_holding_registers(address=address, count=2, slave=slave_id)
                
                if result.isError():
                    log(f"  ❌ Failed to read {description} (reg {address}): {result}")
                else:
                    values = result.registers
                    log(f"  ✅ {description} (reg {address}): {values}")
                    successful_reads += 1
                
                time.sleep(0.1)  # Small delay between reads
                
            except Exception as e:
                log(f"  ❌ Error reading {description}: {e}")
        
        client.close()
        
        log(f"Modbus test completed: {successful_reads}/{len(test_registers)} registers read successfully")
        return successful_reads > 0
        
    except ImportError:
        log("❌ pymodbus library not installed")
        log("Install with: pip3 install pymodbus")
        return False
    except Exception as e:
        log(f"Modbus test error: {e}")
        return False

def check_modbus_service():
    """Check if Modbus service is running"""
    log("Checking Modbus service status...")
    
    try:
        # Check if there's a Modbus service running
        result = subprocess.run(['ps', 'aux'], capture_output=True, text=True, timeout=10)
        
        modbus_processes = []
        for line in result.stdout.split('\n'):
            if 'modbus' in line.lower() or 'python' in line and 'config.json' in line:
                modbus_processes.append(line.strip())
        
        if modbus_processes:
            log("Found Modbus-related processes:")
            for process in modbus_processes:
                log(f"  {process}")
        else:
            log("No Modbus-related processes found")
        
        # Check if port 502 is being used
        result = subprocess.run(['netstat', '-tlnp'], capture_output=True, text=True, timeout=10)
        
        port_502_used = False
        for line in result.stdout.split('\n'):
            if ':502 ' in line:
                log(f"Port 502 usage: {line.strip()}")
                port_502_used = True
        
        if not port_502_used:
            log("Port 502 is not in use by any process")
        
        return True
        
    except Exception as e:
        log(f"Service check error: {e}")
        return False

def main():
    """Run comprehensive RTU check from production server"""
    
    rtu_ip = "192.168.1.1"
    
    log("=" * 60)
    log("PRODUCTION SERVER RTU CONNECTION CHECK")
    log("=" * 60)
    log(f"Target RTU: {rtu_ip}")
    log("=" * 60)
    
    # Run all tests
    results = {}
    
    results['network_config'] = check_network_config()
    log("")
    
    results['modbus_service'] = check_modbus_service()
    log("")
    
    results['ping'] = test_rtu_ping(rtu_ip)
    log("")
    
    results['tcp'] = test_rtu_tcp(rtu_ip)
    log("")
    
    results['modbus'] = test_rtu_modbus(rtu_ip)
    log("")
    
    # Summary
    log("=" * 60)
    log("TEST RESULTS SUMMARY")
    log("=" * 60)
    
    for test_name, result in results.items():
        status = "✅ PASS" if result else "❌ FAIL"
        log(f"{test_name.upper().replace('_', ' ')}: {status}")
    
    # Diagnosis
    log("")
    log("DIAGNOSIS:")
    
    if results['ping'] and results['tcp'] and results['modbus']:
        log("✅ RTU is fully accessible from production server")
        log("   → RTU communication should be working")
        log("   → Check application logs for data processing issues")
        
    elif results['ping'] and results['tcp'] and not results['modbus']:
        log("⚠️  RTU is reachable but Modbus communication failed")
        log("   → Check RTU Modbus configuration")
        log("   → Verify slave ID and register addresses")
        
    elif results['ping'] and not results['tcp']:
        log("⚠️  RTU responds to ping but Modbus port is closed")
        log("   → Enable Modbus TCP service on RTU")
        log("   → Check RTU firewall settings")
        
    elif not results['ping']:
        log("❌ RTU is not reachable from production server")
        log("   → Check network routing between production server and RTU")
        log("   → Verify RTU is powered on and network connected")
        log("   → Check if RTU IP address is correct")
    
    # Return success if critical tests pass
    critical_tests = ['ping', 'tcp', 'modbus']
    success = all(results.get(test, False) for test in critical_tests)
    
    return 0 if success else 1

if __name__ == "__main__":
    exit_code = main()
    sys.exit(exit_code)