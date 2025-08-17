#!/usr/bin/env python3
"""
Test Teltonika RTU connection through VPN tunnel
"""

import socket
import subprocess
import sys
from pymodbus.client import ModbusTcpClient
from pymodbus.exceptions import ModbusException, ConnectionException

def test_vpn_network():
    """Test devices on the VPN network"""
    print("🔍 Scanning VPN network (10.5.0.x) for Teltonika RTU...")
    
    # Common IP addresses for Teltonika devices on VPN
    test_ips = [
        "10.5.0.1",    # Your VPN IP
        "10.5.0.2",    # Common RTU IP
        "10.5.0.10",   # Alternative RTU IP
        "10.5.0.100",  # Alternative RTU IP
        "192.168.1.1", # Original IP (might be accessible via VPN)
    ]
    
    reachable_devices = []
    
    for ip in test_ips:
        print(f"\n🔍 Testing {ip}...")
        
        # Test ping
        try:
            result = subprocess.run(['ping', '-n', '2', ip], 
                                  capture_output=True, text=True, timeout=10)
            if result.returncode == 0:
                print(f"  ✅ Ping successful to {ip}")
                reachable_devices.append(ip)
                
                # Test Modbus port
                if test_modbus_port(ip):
                    print(f"  ✅ Modbus TCP available on {ip}")
                    return ip
                else:
                    print(f"  ❌ Modbus TCP not available on {ip}")
            else:
                print(f"  ❌ Ping failed to {ip}")
        except Exception as e:
            print(f"  ❌ Error testing {ip}: {e}")
    
    return None

def test_modbus_port(ip, port=502):
    """Test if Modbus TCP port is open"""
    try:
        sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        sock.settimeout(5)
        result = sock.connect_ex((ip, port))
        sock.close()
        return result == 0
    except:
        return False

def test_modbus_connection(ip):
    """Test actual Modbus communication"""
    print(f"\n🔍 Testing Modbus communication with {ip}...")
    
    client = None
    try:
        client = ModbusTcpClient(host=ip, port=502, timeout=10)
        
        if not client.connect():
            print(f"❌ Failed to connect to Modbus TCP on {ip}")
            return False
        
        print(f"✅ Connected to Modbus TCP on {ip}")
        
        # Test reading some common registers
        test_registers = [1, 40001, 40007, 40013]
        successful_reads = 0
        
        for reg in test_registers:
            try:
                result = client.read_holding_registers(address=reg, count=2, slave=1)
                if not result.isError():
                    print(f"  ✅ Register {reg}: {result.registers}")
                    successful_reads += 1
                else:
                    print(f"  ❌ Register {reg}: {result}")
            except Exception as e:
                print(f"  ❌ Register {reg}: {e}")
        
        if successful_reads > 0:
            print(f"✅ Successfully read {successful_reads} registers")
            return True
        else:
            print("❌ No registers could be read")
            return False
            
    except Exception as e:
        print(f"❌ Modbus connection error: {e}")
        return False
    finally:
        if client and client.connected:
            client.close()

def main():
    """Main test function"""
    print("=" * 60)
    print("TELTONIKA RTU VPN CONNECTION TEST")
    print("=" * 60)
    
    # Check VPN status
    print("🔍 Checking VPN connection status...")
    try:
        result = subprocess.run(['ping', '-n', '1', '10.5.0.1'], 
                              capture_output=True, text=True, timeout=5)
        if result.returncode == 0:
            print("✅ VPN connection is active (10.5.0.1 reachable)")
        else:
            print("❌ VPN connection might be down")
            print("   → Check if TeltonicaVPN adapter is connected")
            return
    except Exception as e:
        print(f"❌ Error checking VPN: {e}")
        return
    
    # Scan for RTU
    rtu_ip = test_vpn_network()
    
    if rtu_ip:
        print(f"\n✅ Found Teltonika RTU at {rtu_ip}")
        
        # Test Modbus communication
        if test_modbus_connection(rtu_ip):
            print(f"\n🎉 SUCCESS: Teltonika RTU is accessible at {rtu_ip}")
            print(f"Update your config.json to use IP: {rtu_ip}")
        else:
            print(f"\n⚠️  RTU found at {rtu_ip} but Modbus communication failed")
            print("   → Check if Modbus TCP is enabled on the device")
    else:
        print("\n❌ No accessible Teltonika RTU found")
        print("Troubleshooting steps:")
        print("1. Verify VPN connection is active")
        print("2. Check RTU power and network status")
        print("3. Confirm RTU IP address configuration")

if __name__ == "__main__":
    main()