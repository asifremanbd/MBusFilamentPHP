#!/usr/bin/env python3
"""
RTU Register Discovery
Try different slave IDs and register addresses to find working configuration
"""

import time
from datetime import datetime

def log(message):
    timestamp = datetime.now().strftime("%H:%M:%S")
    print(f"[{timestamp}] {message}")

def test_slave_ids(ip="192.168.1.1", port=502):
    """Test different slave IDs"""
    
    try:
        from pymodbus.client import ModbusTcpClient
        
        log("Testing different slave IDs...")
        
        # Common slave IDs to try
        slave_ids = [1, 2, 3, 247, 255]  # 247 is common default, 255 is broadcast
        
        client = ModbusTcpClient(host=ip, port=port, timeout=10)
        
        if not client.connect():
            log("❌ Cannot connect to RTU")
            return None
        
        working_slaves = []
        
        for slave_id in slave_ids:
            log(f"Testing slave ID {slave_id}...")
            
            try:
                # Try reading a basic register
                result = client.read_holding_registers(address=1, count=1, slave=slave_id)
                
                if not result.isError():
                    log(f"  ✅ Slave ID {slave_id} responds: {result.registers}")
                    working_slaves.append(slave_id)
                else:
                    log(f"  ❌ Slave ID {slave_id} error: {result}")
                
                time.sleep(0.2)
                
            except Exception as e:
                log(f"  ❌ Slave ID {slave_id} exception: {e}")
        
        client.close()
        
        if working_slaves:
            log(f"✅ Working slave IDs found: {working_slaves}")
            return working_slaves[0]  # Return first working slave
        else:
            log("❌ No working slave IDs found")
            return None
            
    except Exception as e:
        log(f"❌ Slave ID test error: {e}")
        return None

def discover_registers(ip="192.168.1.1", port=502, slave_id=1):
    """Discover available registers"""
    
    try:
        from pymodbus.client import ModbusTcpClient
        
        log(f"Discovering registers for slave ID {slave_id}...")
        
        client = ModbusTcpClient(host=ip, port=port, timeout=10)
        
        if not client.connect():
            log("❌ Cannot connect to RTU")
            return []
        
        working_registers = []
        
        # Test common register ranges
        test_ranges = [
            (1, 10, "Coils"),
            (10001, 10010, "Discrete Inputs"),
            (30001, 30020, "Input Registers"),
            (40001, 40050, "Holding Registers"),
            (0, 20, "Zero-based Holding Registers"),
            (100, 120, "Alternative Holding Registers")
        ]
        
        for start_addr, end_addr, reg_type in test_ranges:
            log(f"Testing {reg_type} ({start_addr}-{end_addr})...")
            
            for addr in range(start_addr, end_addr):
                try:
                    if "Coil" in reg_type:
                        result = client.read_coils(address=addr, count=1, slave=slave_id)
                    elif "Discrete" in reg_type:
                        result = client.read_discrete_inputs(address=addr, count=1, slave=slave_id)
                    elif "Input" in reg_type:
                        result = client.read_input_registers(address=addr, count=1, slave=slave_id)
                    else:  # Holding registers
                        result = client.read_holding_registers(address=addr, count=1, slave=slave_id)
                    
                    if not result.isError():
                        value = result.registers[0] if hasattr(result, 'registers') else result.bits[0]
                        log(f"  ✅ {reg_type} {addr}: {value}")
                        working_registers.append((addr, reg_type, value))
                        
                        # Don't flood - just find a few working registers per type
                        if len([r for r in working_registers if r[1] == reg_type]) >= 3:
                            break
                    
                    time.sleep(0.05)  # Small delay
                    
                except Exception as e:
                    # Skip individual register errors
                    pass
        
        client.close()
        
        if working_registers:
            log(f"✅ Found {len(working_registers)} working registers")
            for addr, reg_type, value in working_registers:
                log(f"  {reg_type} {addr}: {value}")
        else:
            log("❌ No working registers found")
        
        return working_registers
        
    except Exception as e:
        log(f"❌ Register discovery error: {e}")
        return []

def test_teltonika_specific_registers(ip="192.168.1.1", port=502, slave_id=1):
    """Test Teltonika-specific register addresses"""
    
    try:
        from pymodbus.client import ModbusTcpClient
        
        log(f"Testing Teltonika-specific registers for slave ID {slave_id}...")
        
        client = ModbusTcpClient(host=ip, port=port, timeout=10)
        
        if not client.connect():
            log("❌ Cannot connect to RTU")
            return []
        
        # Teltonika RUT955 common registers (check manual)
        teltonika_registers = [
            (1, "Digital Input 1"),
            (2, "Digital Input 2"), 
            (3, "Digital Output 1"),
            (4, "Digital Output 2"),
            (5, "Analog Input 1"),
            (6, "Analog Input 2"),
            (64, "GSM Signal Strength"),
            (65, "Connection State"),
            (66, "Connection Type"),
            (67, "Router Temperature"),
            (68, "Router Uptime"),
            (100, "Custom Register 1"),
            (101, "Custom Register 2"),
            (200, "System Status"),
            (300, "Network Status")
        ]
        
        working_registers = []
        
        for addr, description in teltonika_registers:
            try:
                log(f"Testing {description} (reg {addr})...")
                
                # Try both holding and input registers
                for reg_type, read_func in [("Holding", client.read_holding_registers), 
                                          ("Input", client.read_input_registers)]:
                    try:
                        result = read_func(address=addr, count=1, slave=slave_id)
                        
                        if not result.isError():
                            value = result.registers[0]
                            log(f"  ✅ {description} ({reg_type} {addr}): {value}")
                            working_registers.append((addr, description, reg_type, value))
                            break  # Found working register, no need to try other type
                        
                    except Exception:
                        pass
                
                time.sleep(0.1)
                
            except Exception as e:
                log(f"  ❌ Error testing {description}: {e}")
        
        client.close()
        
        if working_registers:
            log(f"✅ Found {len(working_registers)} Teltonika registers")
        else:
            log("❌ No Teltonika-specific registers found")
        
        return working_registers
        
    except Exception as e:
        log(f"❌ Teltonika register test error: {e}")
        return []

def main():
    """Run comprehensive RTU register discovery"""
    
    ip = "192.168.1.1"
    
    log("=" * 60)
    log("RTU REGISTER DISCOVERY")
    log("=" * 60)
    log(f"Target RTU: {ip}")
    log("=" * 60)
    
    # Step 1: Find working slave ID
    working_slave = test_slave_ids(ip)
    
    if not working_slave:
        log("❌ No working slave ID found - RTU may not be configured for Modbus")
        return 1
    
    log("")
    log(f"Using slave ID {working_slave} for further tests...")
    log("")
    
    # Step 2: Discover general registers
    general_registers = discover_registers(ip, slave_id=working_slave)
    
    log("")
    
    # Step 3: Test Teltonika-specific registers
    teltonika_registers = test_teltonika_specific_registers(ip, slave_id=working_slave)
    
    # Summary
    log("")
    log("=" * 60)
    log("DISCOVERY SUMMARY")
    log("=" * 60)
    
    log(f"Working slave ID: {working_slave}")
    log(f"General registers found: {len(general_registers)}")
    log(f"Teltonika registers found: {len(teltonika_registers)}")
    
    if general_registers or teltonika_registers:
        log("")
        log("RECOMMENDED CONFIGURATION:")
        log(f"  IP: {ip}")
        log(f"  Port: 502")
        log(f"  Slave ID: {working_slave}")
        log("")
        log("Working registers:")
        
        all_registers = general_registers + [(r[0], r[1], r[2], r[3]) for r in teltonika_registers]
        for reg_info in all_registers[:10]:  # Show first 10
            if len(reg_info) == 3:
                addr, reg_type, value = reg_info
                log(f"  Address {addr} ({reg_type}): {value}")
            else:
                addr, desc, reg_type, value = reg_info
                log(f"  Address {addr} ({desc}, {reg_type}): {value}")
        
        return 0
    else:
        log("❌ No working registers found")
        return 1

if __name__ == "__main__":
    exit_code = main()
    exit(exit_code)