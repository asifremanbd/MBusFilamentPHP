#!/usr/bin/env python3
"""
Test script to verify connection to Teltonika RTU at 192.168.1.1
"""

import sys
import time
from pymodbus.client import ModbusTcpClient
from pymodbus.exceptions import ModbusException, ConnectionException

def test_teltonika_connection():
    """Test connection to Teltonika RTU"""
    
    # Teltonika RTU configuration
    ip = "192.168.1.1"
    port = 502
    slave_id = 1
    timeout = 15
    
    print(f"Testing connection to Teltonika RTU at {ip}:{port}")
    print(f"Slave ID: {slave_id}, Timeout: {timeout}s")
    print("-" * 50)
    
    client = None
    
    try:
        # Create Modbus TCP client
        client = ModbusTcpClient(host=ip, port=port, timeout=timeout)
        
        # Attempt connection
        print("Attempting to connect...")
        if not client.connect():
            print("❌ Failed to establish TCP connection")
            return False
        
        print("✅ TCP connection established")
        
        # Test basic register read - try common Modbus registers
        test_registers = [
            (40001, "Voltage L1"),
            (40007, "Current L1"), 
            (40013, "Active Power"),
            (40021, "Total Energy"),
            (1, "Coil Status"),
            (30001, "Input Register")
        ]
        
        successful_reads = 0
        
        for address, description in test_registers:
            try:
                print(f"Testing register {address} ({description})...")
                
                if address >= 40001:
                    # Holding register
                    result = client.read_holding_registers(address=address, count=2, slave=slave_id)
                elif address >= 30001:
                    # Input register
                    result = client.read_input_registers(address=address, count=2, slave=slave_id)
                else:
                    # Coil
                    result = client.read_coils(address=address, count=1, slave=slave_id)
                
                if result.isError():
                    print(f"  ❌ Error reading register {address}: {result}")
                else:
                    print(f"  ✅ Successfully read register {address}: {result.registers if hasattr(result, 'registers') else result.bits}")
                    successful_reads += 1
                
                # Small delay between reads
                time.sleep(0.1)
                
            except ModbusException as e:
                print(f"  ❌ Modbus error reading register {address}: {e}")
            except Exception as e:
                print(f"  ❌ Unexpected error reading register {address}: {e}")
        
        print("-" * 50)
        print(f"Connection test completed: {successful_reads}/{len(test_registers)} registers read successfully")
        
        if successful_reads > 0:
            print("✅ Teltonika RTU is responding to Modbus requests")
            return True
        else:
            print("❌ No registers could be read - check device configuration")
            return False
            
    except ConnectionException as e:
        print(f"❌ Connection error: {e}")
        return False
    except Exception as e:
        print(f"❌ Unexpected error: {e}")
        return False
    finally:
        if client and client.connected:
            client.close()
            print("Connection closed")

if __name__ == "__main__":
    success = test_teltonika_connection()
    sys.exit(0 if success else 1)