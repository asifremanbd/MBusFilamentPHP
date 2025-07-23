#!/usr/bin/env python3
"""
Test script to simulate Modbus polling and send test data to Laravel API
"""

import json
import requests
import random
from datetime import datetime, timezone
from dotenv import load_dotenv
import os

# Load environment variables
load_dotenv()

def generate_test_readings():
    """Generate realistic test readings for all configured devices"""
    
    # Load device configuration
    with open('config.json', 'r') as f:
        devices = json.load(f)
    
    readings = []
    
    for device in devices:
        device_id = device['device_id']
        
        for register in device['registers']:
            # Generate realistic values based on parameter type
            parameter = register['parameter']
            unit = register['unit']
            
            if 'Voltage' in parameter:
                value = round(random.uniform(220, 240), 2)  # Voltage range
            elif 'Current' in parameter:
                value = round(random.uniform(5, 15), 2)     # Current range
            elif 'Power' in parameter:
                value = round(random.uniform(1, 5), 2)      # Power range
            elif 'Energy' in parameter:
                value = round(random.uniform(100, 1000), 1) # Energy range
            elif 'Flow' in parameter:
                value = round(random.uniform(10, 50), 1)    # Flow rate
            elif 'Volume' in parameter:
                value = round(random.uniform(1000, 5000), 0) # Volume
            elif 'Runtime' in parameter:
                value = round(random.uniform(100, 8760), 0)  # Runtime hours
            else:
                value = round(random.uniform(1, 100), 2)     # Default range
            
            reading = {
                "device_id": device_id,
                "parameter": parameter,
                "value": value,
                "timestamp": datetime.now(timezone.utc).strftime('%Y-%m-%dT%H:%M:%SZ')
            }
            
            readings.append(reading)
    
    return readings

def send_readings_to_api(readings):
    """Send readings to Laravel API"""
    api_url = os.getenv('LARAVEL_API_URL', 'http://127.0.0.1:8000/api/readings')
    
    print(f"Sending {len(readings)} test readings to {api_url}")
    
    success_count = 0
    
    for reading in readings:
        try:
            response = requests.post(
                api_url,
                json=reading,
                headers={'Content-Type': 'application/json'},
                timeout=10
            )
            
            if response.status_code in [200, 201]:
                success_count += 1
                print(f"✓ {reading['parameter']} = {reading['value']} (Device {reading['device_id']})")
            else:
                print(f"✗ Error {response.status_code}: {response.text}")
                
        except requests.exceptions.RequestException as e:
            print(f"✗ Request error: {e}")
    
    print(f"\nResults: {success_count}/{len(readings)} readings sent successfully")
    return success_count == len(readings)

def main():
    print("=== Energy Monitor Test Poller ===")
    print("Generating test readings for all configured devices...\n")
    
    try:
        # Generate test readings
        readings = generate_test_readings()
        
        # Send to API
        success = send_readings_to_api(readings)
        
        if success:
            print("\n✅ All test readings sent successfully!")
        else:
            print("\n❌ Some readings failed to send")
            
    except FileNotFoundError:
        print("❌ config.json file not found")
    except Exception as e:
        print(f"❌ Error: {e}")

if __name__ == "__main__":
    main()