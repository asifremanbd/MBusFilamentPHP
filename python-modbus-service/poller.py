#!/usr/bin/env python3
"""
Modbus Polling Service for Energy Monitoring System
Connects to Modbus TCP devices and sends readings to Laravel API
"""

import json
import logging
import requests
import struct
from datetime import datetime, timezone
from typing import Dict, List, Optional, Any
from dataclasses import dataclass
from pymodbus.client import ModbusTcpClient
from pymodbus.exceptions import ModbusException, ConnectionException
import time
import os

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler('modbus_poller.log'),
        logging.StreamHandler()
    ]
)
logger = logging.getLogger(__name__)

@dataclass
class RegisterConfig:
    """Configuration for a single Modbus register"""
    address: int
    parameter: str
    data_type: str  # 'float', 'int', 'uint16', 'uint32'
    scale: float = 1.0
    unit: str = ""
    description: str = ""

@dataclass
class DeviceConfig:
    """Configuration for a Modbus device"""
    device_id: int
    ip: str
    port: int = 502
    slave_id: int = 1
    timeout: int = 10
    registers: List[RegisterConfig] = None

class ModbusPoller:
    """Main Modbus polling service"""
    
    def __init__(self, config_file: str = "config.json", api_url: str = None):
        self.config_file = config_file
        self.api_url = api_url or "http://localhost:8000/api/readings"
        self.devices = self.load_config()
        self.session = requests.Session()
        
    def load_config(self) -> List[DeviceConfig]:
        """Load device configuration from JSON file"""
        try:
            with open(self.config_file, 'r') as f:
                config_data = json.load(f)
            
            devices = []
            for device_data in config_data:
                registers = []
                for reg_data in device_data.get('registers', []):
                    registers.append(RegisterConfig(
                        address=reg_data['address'],
                        parameter=reg_data['parameter'],
                        data_type=reg_data.get('data_type', 'float'),
                        scale=reg_data.get('scale', 1.0),
                        unit=reg_data.get('unit', ''),
                        description=reg_data.get('description', '')
                    ))
                
                devices.append(DeviceConfig(
                    device_id=device_data['device_id'],
                    ip=device_data['ip'],
                    port=device_data.get('port', 502),
                    slave_id=device_data.get('slave_id', 1),
                    timeout=device_data.get('timeout', 10),
                    registers=registers
                ))
            
            logger.info(f"Loaded configuration for {len(devices)} devices")
            return devices
            
        except FileNotFoundError:
            logger.error(f"Configuration file {self.config_file} not found")
            return []
        except json.JSONDecodeError as e:
            logger.error(f"Invalid JSON in configuration file: {e}")
            return []
        except Exception as e:
            logger.error(f"Error loading configuration: {e}")
            return []
    
    def decode_register_value(self, raw_value: int, data_type: str, scale: float = 1.0) -> float:
        """Decode raw register value based on data type and scale"""
        try:
            if data_type == 'float':
                # Convert 32-bit integer to float
                float_bytes = struct.pack('>I', raw_value)
                value = struct.unpack('>f', float_bytes)[0]
            elif data_type == 'int':
                # Signed 16-bit integer
                value = struct.unpack('>h', struct.pack('>H', raw_value & 0xFFFF))[0]
            elif data_type == 'uint16':
                # Unsigned 16-bit integer
                value = raw_value & 0xFFFF
            elif data_type == 'uint32':
                # Unsigned 32-bit integer
                value = raw_value
            else:
                logger.warning(f"Unknown data type: {data_type}, treating as uint16")
                value = raw_value & 0xFFFF
            
            # Apply scale factor
            return float(value) * scale
            
        except Exception as e:
            logger.error(f"Error decoding register value: {e}")
            return 0.0
    
    def read_device_registers(self, device: DeviceConfig) -> List[Dict[str, Any]]:
        """Read all registers for a single device"""
        readings = []
        client = None
        
        try:
            # Connect to Modbus device
            client = ModbusTcpClient(
                host=device.ip,
                port=device.port,
                timeout=device.timeout
            )
            
            if not client.connect():
                logger.error(f"Failed to connect to device {device.device_id} at {device.ip}:{device.port}")
                return readings
            
            logger.info(f"Connected to device {device.device_id} at {device.ip}")
            
            # Read each register
            for register in device.registers:
                try:
                    # Read holding register (function code 03)
                    result = client.read_holding_registers(
                        address=register.address,
                        count=2 if register.data_type == 'float' else 1,
                        slave=device.slave_id
                    )
                    
                    if result.isError():
                        logger.warning(f"Error reading register {register.address}: {result}")
                        continue
                    
                    # Decode the value
                    if register.data_type == 'float':
                        # Combine two 16-bit registers into 32-bit float
                        raw_value = (result.registers[0] << 16) | result.registers[1]
                    else:
                        raw_value = result.registers[0]
                    
                    value = self.decode_register_value(raw_value, register.data_type, register.scale)
                    
                    # Create reading record
                    reading = {
                        "device_id": device.device_id,
                        "parameter": register.parameter,
                        "value": round(value, 3),
                        "unit": register.unit,
                        "timestamp": datetime.now(timezone.utc).isoformat(),
                        "register_address": register.address,
                        "data_type": register.data_type,
                        "description": register.description
                    }
                    
                    readings.append(reading)
                    logger.debug(f"Read {register.parameter}: {value} {register.unit}")
                    
                except ModbusException as e:
                    logger.error(f"Modbus error reading register {register.address}: {e}")
                except Exception as e:
                    logger.error(f"Unexpected error reading register {register.address}: {e}")
            
        except ConnectionException as e:
            logger.error(f"Connection error for device {device.device_id}: {e}")
        except Exception as e:
            logger.error(f"Unexpected error for device {device.device_id}: {e}")
        finally:
            if client and client.connected:
                client.close()
        
        return readings
    
    def send_readings_to_api(self, readings: List[Dict[str, Any]]) -> bool:
        """Send readings to Laravel API"""
        if not readings:
            return True
        
        try:
            # Send each reading individually
            success_count = 0
            for reading in readings:
                try:
                    response = self.session.post(
                        self.api_url,
                        json=reading,
                        headers={'Content-Type': 'application/json'},
                        timeout=30
                    )
                    
                    if response.status_code == 200 or response.status_code == 201:
                        success_count += 1
                        logger.debug(f"Successfully sent reading: {reading['parameter']} = {reading['value']}")
                    else:
                        logger.error(f"API error {response.status_code}: {response.text}")
                        
                except requests.exceptions.RequestException as e:
                    logger.error(f"Request error sending reading: {e}")
                except Exception as e:
                    logger.error(f"Unexpected error sending reading: {e}")
            
            logger.info(f"Sent {success_count}/{len(readings)} readings to API")
            return success_count > 0
            
        except Exception as e:
            logger.error(f"Error sending readings to API: {e}")
            return False
    
    def poll_all_devices(self) -> bool:
        """Poll all configured devices"""
        logger.info("Starting Modbus polling cycle")
        
        all_readings = []
        success_count = 0
        
        for device in self.devices:
            try:
                logger.info(f"Polling device {device.device_id} ({device.ip})")
                readings = self.read_device_registers(device)
                
                if readings:
                    all_readings.extend(readings)
                    success_count += 1
                    logger.info(f"Successfully read {len(readings)} registers from device {device.device_id}")
                else:
                    logger.warning(f"No readings obtained from device {device.device_id}")
                    
            except Exception as e:
                logger.error(f"Error polling device {device.device_id}: {e}")
        
        # Send all readings to API
        if all_readings:
            api_success = self.send_readings_to_api(all_readings)
            if api_success:
                logger.info(f"Polling cycle completed: {len(all_readings)} readings sent to API")
            else:
                logger.error("Failed to send readings to API")
        else:
            logger.warning("No readings obtained from any device")
        
        return success_count > 0
    
    def run_single_poll(self):
        """Run a single polling cycle"""
        return self.poll_all_devices()

def main():
    """Main entry point"""
    # Get configuration from environment or use defaults
    config_file = os.getenv('MODBUS_CONFIG', 'config.json')
    api_url = os.getenv('LARAVEL_API_URL', 'http://localhost:8000/api/readings')
    
    logger.info("Starting Modbus Polling Service")
    logger.info(f"Config file: {config_file}")
    logger.info(f"API URL: {api_url}")
    
    # Create poller instance
    poller = ModbusPoller(config_file=config_file, api_url=api_url)
    
    if not poller.devices:
        logger.error("No devices configured. Please check your config.json file.")
        return
    
    # Run single polling cycle
    success = poller.run_single_poll()
    
    if success:
        logger.info("Polling cycle completed successfully")
    else:
        logger.error("Polling cycle failed")
        exit(1)

if __name__ == "__main__":
    main() 