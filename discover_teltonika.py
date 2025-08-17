#!/usr/bin/env python3
"""
Discover Teltonika RTU on VPN network
"""

import socket
import subprocess
import threading
import time
from concurrent.futures import ThreadPoolExecutor, as_completed

def ping_host(ip):
    """Ping a single host"""
    try:
        result = subprocess.run(['ping', '-n', '1', '-w', '1000', ip], 
                              capture_output=True, text=True, timeout=3)
        return ip if result.returncode == 0 else None
    except:
        return None

def scan_port(ip, port):
    """Scan a single port on an IP"""
    try:
        sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        sock.settimeout(2)
        result = sock.connect_ex((ip, port))
        sock.close()
        return (ip, port) if result == 0 else None
    except:
        return None

def discover_network():
    """Discover active hosts on VPN networks"""
    print("🔍 Discovering devices on VPN networks...")
    
    # Networks to scan
    networks = [
        "10.5.0",      # Your VPN network
        "192.168.1",   # Common RTU network
        "192.168.100", # Alternative network
        "10.0.0",      # Alternative VPN network
    ]
    
    active_hosts = []
    
    for network in networks:
        print(f"\n🔍 Scanning network {network}.x...")
        
        # Create list of IPs to test
        ips_to_test = [f"{network}.{i}" for i in range(1, 255)]
        
        # Use ThreadPoolExecutor for faster scanning
        with ThreadPoolExecutor(max_workers=50) as executor:
            future_to_ip = {executor.submit(ping_host, ip): ip for ip in ips_to_test}
            
            for future in as_completed(future_to_ip, timeout=30):
                result = future.result()
                if result:
                    print(f"  ✅ Found active host: {result}")
                    active_hosts.append(result)
    
    return active_hosts

def scan_modbus_ports(hosts):
    """Scan for Modbus ports on active hosts"""
    print(f"\n🔍 Scanning Modbus ports on {len(hosts)} active hosts...")
    
    modbus_ports = [502, 503, 1502, 10502]
    modbus_devices = []
    
    for host in hosts:
        print(f"\n🔍 Scanning {host}...")
        
        with ThreadPoolExecutor(max_workers=10) as executor:
            futures = [executor.submit(scan_port, host, port) for port in modbus_ports]
            
            for future in as_completed(futures, timeout=10):
                result = future.result()
                if result:
                    ip, port = result
                    print(f"  ✅ Modbus port {port} open on {ip}")
                    modbus_devices.append((ip, port))
    
    return modbus_devices

def test_modbus_device(ip, port=502):
    """Test Modbus communication with a device"""
    print(f"\n🔍 Testing Modbus communication with {ip}:{port}...")
    
    try:
        from pymodbus.client import ModbusTcpClient
        
        client = ModbusTcpClient(host=ip, port=port, timeout=5)
        
        if not client.connect():
            print(f"❌ Failed to connect to {ip}:{port}")
            return False
        
        print(f"✅ Connected to Modbus TCP on {ip}:{port}")
        
        # Try to read device identification
        try:
            # Test common slave IDs
            for slave_id in [1, 2, 3, 247]:
                try:
                    result = client.read_holding_registers(address=1, count=1, slave=slave_id)
                    if not result.isError():
                        print(f"  ✅ Slave ID {slave_id} responds")
                        
                        # Try to read some common registers
                        test_regs = [40001, 40007, 40013, 40021]
                        for reg in test_regs:
                            try:
                                result = client.read_holding_registers(address=reg, count=2, slave=slave_id)
                                if not result.isError():
                                    print(f"    ✅ Register {reg}: {result.registers}")
                            except:
                                pass
                        
                        client.close()
                        return True
                except:
                    continue
        except Exception as e:
            print(f"  ❌ Communication error: {e}")
        
        client.close()
        return False
        
    except ImportError:
        print("❌ pymodbus not available for testing")
        return False
    except Exception as e:
        print(f"❌ Error: {e}")
        return False

def main():
    """Main discovery function"""
    print("=" * 60)
    print("TELTONIKA RTU NETWORK DISCOVERY")
    print("=" * 60)
    
    # Step 1: Discover active hosts
    active_hosts = discover_network()
    
    if not active_hosts:
        print("\n❌ No active hosts found on any network")
        print("Possible issues:")
        print("- RTU is powered off")
        print("- VPN connection is not working properly")
        print("- RTU is on a different network")
        return
    
    print(f"\n✅ Found {len(active_hosts)} active hosts")
    
    # Step 2: Scan for Modbus ports
    modbus_devices = scan_modbus_ports(active_hosts)
    
    if not modbus_devices:
        print("\n❌ No Modbus devices found")
        print("The RTU might not have Modbus TCP enabled")
        return
    
    print(f"\n✅ Found {len(modbus_devices)} potential Modbus devices")
    
    # Step 3: Test Modbus communication
    print("\n" + "=" * 60)
    print("TESTING MODBUS COMMUNICATION")
    print("=" * 60)
    
    working_devices = []
    
    for ip, port in modbus_devices:
        if test_modbus_device(ip, port):
            working_devices.append((ip, port))
    
    # Summary
    print("\n" + "=" * 60)
    print("DISCOVERY SUMMARY")
    print("=" * 60)
    
    if working_devices:
        print("✅ Found working Teltonika RTU devices:")
        for ip, port in working_devices:
            print(f"   📍 {ip}:{port}")
        
        # Update config suggestion
        best_device = working_devices[0]
        print(f"\n💡 Recommended configuration:")
        print(f"   IP: {best_device[0]}")
        print(f"   Port: {best_device[1]}")
        print(f"   Update your config.json with this IP address")
    else:
        print("❌ No working Modbus devices found")
        print("\nNext steps:")
        print("1. Access RTU web interface to enable Modbus TCP")
        print("2. Check RTU network configuration")
        print("3. Verify RTU is powered and connected")

if __name__ == "__main__":
    main()