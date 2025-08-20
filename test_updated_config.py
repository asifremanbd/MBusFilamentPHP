#!/usr/bin/env python3
"""
Test the updated RTU configuration
"""

import json
import time
from datetime import datetime

def log(message):
    timestamp = datetime.now().strftime("%H:%M:%S")
    print(f"[{timestamp}] {message}")

def test_updated_config():
    """Test the updated configuration"""
    
    # Load the updated config
    try:
        with open('config.json', 'r') as f:
            config = json.load(f)
    except FileNotFoundError:
        log("❌ config.json not found")
        return False
    
    # Find RTU device (device_id 9)
    rtu_config = None
    for device in config:
        if device.get('device_id') == 9:
            rtu_config = device
            break
    
    if not rtu_config:
        log("❌ RTU device (ID 9) not found in config")
        return False
    
    log(f"Testing RTU configuration:")
    log(f"  IP: {rtu_config['ip']}")
    log(f"  Port: {rtu_config['port']}")
    log(f"  Slave ID: {rtu_config['slave_id']}")
    log(f"  Registers: {len(rtu_config['registers'])}")
    
    try:
        from pymodbus.client import ModbusTcpClient
        
        client = ModbusTcpClient(
            host=rtu_config['ip'], 
            port=rtu_config['port'], 
            timeout=rtu_config['timeout']
        )
        
        if not client.connect():
            log("❌ Cannot connect to RTU")
            return False
        
        log("✅ Connected to RTU")
        
        successful_reads = 0
        total_registers = len(rtu_config['registers'])
        
        for reg_config in rtu_config['registers']:
            address = reg_config['address']
            parameter = reg_config['parameter']
            
            try:
                result = client.read_holding_registers(
                    address=address, 
                    count=1, 
                    slave=rtu_config['slave_id']
                )
                
                if not result.isError():
                    raw_value = result.registers[0]
                    scaled_value = raw_value * reg_config['scale']
                    
                    log(f"  ✅ {parameter} (reg {address}): {raw_value} -> {scaled_value} {reg_config['unit']}")
                    successful_reads += 1
                else:
                    log(f"  ❌ {parameter} (reg {address}): {result}")
                
                time.sleep(0.1)
                
            except Exception as e:
                log(f"  ❌ {parameter} (reg {address}): {e}")
        
        client.close()
        
        log(f"")
        log(f"Test completed: {successful_reads}/{total_registers} registers read successfully")
        
        if successful_reads == total_registers:
            log("✅ All registers working - RTU configuration is correct!")
            return True
        elif successful_reads > 0:
            log("⚠️  Some registers working - partial success")
            return True
        else:
            log("❌ No registers working - configuration needs adjustment")
            return False
            
    except ImportError:
        log("❌ pymodbus library not available")
        return False
    except Exception as e:
        log(f"❌ Test error: {e}")
        return False

if __name__ == "__main__":
    success = test_updated_config()
    exit(0 if success else 1)