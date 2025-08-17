#!/usr/bin/env python3
"""
Comprehensive diagnostics for Teltonika RTU connection
"""

import socket
import subprocess
import sys
import time
import requests
from urllib3.exceptions import InsecureRequestWarning
import warnings

# Suppress SSL warnings for self-signed certificates
warnings.filterwarnings('ignore', category=InsecureRequestWarning)

def test_ping(ip):
    """Test basic network connectivity"""
    print(f"🔍 Testing ping to {ip}...")
    try:
        # Use ping command appropriate for Windows
        result = subprocess.run(['ping', '-n', '4', ip], 
                              capture_output=True, text=True, timeout=30)
        if result.returncode == 0:
            print(f"✅ Ping successful to {ip}")
            return True
        else:
            print(f"❌ Ping failed to {ip}")
            print(f"Output: {result.stdout}")
            return False
    except Exception as e:
        print(f"❌ Ping test error: {e}")
        return False

def test_tcp_port(ip, port, timeout=10):
    """Test if TCP port is open"""
    print(f"🔍 Testing TCP connection to {ip}:{port}...")
    try:
        sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        sock.settimeout(timeout)
        result = sock.connect_ex((ip, port))
        sock.close()
        
        if result == 0:
            print(f"✅ TCP port {port} is open on {ip}")
            return True
        else:
            print(f"❌ TCP port {port} is closed or filtered on {ip}")
            return False
    except Exception as e:
        print(f"❌ TCP port test error: {e}")
        return False

def test_web_interface(ip, username="admin", password="Afs01989!"):
    """Test web interface access"""
    print(f"🔍 Testing web interface at {ip}...")
    
    # Common Teltonika web interface URLs
    urls = [
        f"http://{ip}",
        f"https://{ip}",
        f"http://{ip}/login",
        f"https://{ip}/login"
    ]
    
    for url in urls:
        try:
            print(f"  Trying {url}...")
            response = requests.get(url, timeout=10, verify=False)
            print(f"  ✅ Web interface accessible at {url} (Status: {response.status_code})")
            
            # Check if it's a Teltonika device
            if "teltonika" in response.text.lower() or "rut" in response.text.lower():
                print(f"  ✅ Confirmed Teltonika device")
            
            return True
            
        except requests.exceptions.ConnectTimeout:
            print(f"  ❌ Connection timeout to {url}")
        except requests.exceptions.ConnectionError:
            print(f"  ❌ Connection refused to {url}")
        except Exception as e:
            print(f"  ❌ Error accessing {url}: {e}")
    
    return False

def test_common_modbus_ports(ip):
    """Test common Modbus ports"""
    print(f"🔍 Testing common Modbus ports on {ip}...")
    
    modbus_ports = [502, 503, 10502, 1502]
    open_ports = []
    
    for port in modbus_ports:
        try:
            sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
            sock.settimeout(5)
            result = sock.connect_ex((ip, port))
            sock.close()
            
            if result == 0:
                print(f"  ✅ Port {port} is open")
                open_ports.append(port)
            else:
                print(f"  ❌ Port {port} is closed")
                
        except Exception as e:
            print(f"  ❌ Error testing port {port}: {e}")
    
    return open_ports

def check_network_route(ip):
    """Check network routing to the device"""
    print(f"🔍 Checking network route to {ip}...")
    try:
        result = subprocess.run(['tracert', ip], 
                              capture_output=True, text=True, timeout=60)
        print(f"Route trace output:\n{result.stdout}")
        return True
    except Exception as e:
        print(f"❌ Route trace error: {e}")
        return False

def main():
    """Run comprehensive diagnostics"""
    ip = "192.168.1.1"
    
    print("=" * 60)
    print("TELTONIKA RTU CONNECTION DIAGNOSTICS")
    print("=" * 60)
    print(f"Target device: {ip}")
    print(f"Expected credentials: admin / Afs01989!")
    print("=" * 60)
    
    # Test 1: Basic ping
    ping_ok = test_ping(ip)
    print()
    
    # Test 2: Web interface
    web_ok = test_web_interface(ip)
    print()
    
    # Test 3: Modbus ports
    open_ports = test_common_modbus_ports(ip)
    print()
    
    # Test 4: Standard Modbus port
    modbus_ok = test_tcp_port(ip, 502)
    print()
    
    # Test 5: Network route (if ping failed)
    if not ping_ok:
        check_network_route(ip)
        print()
    
    # Summary
    print("=" * 60)
    print("DIAGNOSTIC SUMMARY")
    print("=" * 60)
    
    if ping_ok:
        print("✅ Device is reachable via ping")
    else:
        print("❌ Device is NOT reachable via ping")
        print("   → Check network connectivity")
        print("   → Verify device IP address")
        print("   → Check if device is powered on")
    
    if web_ok:
        print("✅ Web interface is accessible")
    else:
        print("❌ Web interface is NOT accessible")
        print("   → Device might be offline")
        print("   → Web interface might be disabled")
    
    if modbus_ok:
        print("✅ Modbus TCP port (502) is open")
    elif open_ports:
        print(f"⚠️  Modbus TCP port 502 closed, but found open ports: {open_ports}")
        print("   → Try connecting to alternative ports")
    else:
        print("❌ No Modbus ports are accessible")
        print("   → Modbus TCP might be disabled")
        print("   → Check device configuration")
        print("   → Verify firewall settings")
    
    print("\n" + "=" * 60)
    print("NEXT STEPS:")
    print("=" * 60)
    
    if not ping_ok:
        print("1. Verify network connectivity:")
        print("   - Check if your PC and RTU are on same network")
        print("   - Verify RTU IP address configuration")
        print("   - Check network cables and switches")
    
    if ping_ok and not web_ok:
        print("1. Device responds to ping but web interface unavailable:")
        print("   - Web interface might be disabled")
        print("   - Try different ports or protocols")
    
    if ping_ok and not modbus_ok:
        print("1. Enable Modbus TCP on the Teltonika RTU:")
        print("   - Access web interface (admin/Afs01989!)")
        print("   - Go to Services → Modbus")
        print("   - Enable Modbus TCP Server")
        print("   - Set port to 502")
        print("   - Configure slave ID (usually 1)")
        print("   - Save and restart device")

if __name__ == "__main__":
    main()