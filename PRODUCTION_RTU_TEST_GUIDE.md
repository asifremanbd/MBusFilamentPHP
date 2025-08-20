# Production RTU Test Guide

## Quick SSH Test

### Option 1: Using the PowerShell script
```powershell
.\ssh_production_test.ps1 -ServerIP "your-production-server-ip" -Username "ubuntu"
```

### Option 2: Manual SSH commands

1. **SSH to your production server:**
   ```bash
   ssh ubuntu@your-production-server-ip
   ```

2. **Create the test script on the server:**
   ```bash
   cat > production_rtu_check.py << 'EOF'
   # Copy the content from production_rtu_check.py file
   EOF
   ```

3. **Run the test:**
   ```bash
   python3 production_rtu_check.py
   ```

### Option 3: One-liner test commands

SSH to production server and run these commands:

```bash
# Test basic connectivity
ping -c 4 192.168.1.1

# Test TCP port
nc -zv 192.168.1.1 502

# Test with telnet (if available)
telnet 192.168.1.1 502

# Check network interface
ip addr show

# Check routing
ip route show

# Check if any process is using port 502
netstat -tlnp | grep :502
```

## Expected Results

### If RTU is working correctly:
- ✅ Ping successful
- ✅ TCP port 502 open
- ✅ Modbus registers readable
- ✅ Data values returned

### Common Issues:

#### 1. Ping fails
- RTU is powered off
- Network connectivity issue
- Wrong IP address
- Firewall blocking ICMP

#### 2. Ping works, TCP fails
- Modbus TCP service disabled on RTU
- RTU firewall blocking port 502
- Wrong port number

#### 3. TCP works, Modbus fails
- Wrong slave ID (try 1, 2, or 247)
- Wrong register addresses
- Modbus protocol mismatch

## RTU Configuration Check

If you can access the RTU web interface (http://192.168.1.1):

1. **Login:** admin / Afs01989!
2. **Go to:** Services → Modbus
3. **Check:**
   - Modbus TCP Server: Enabled
   - Port: 502
   - Slave ID: 1 (or note the actual value)
   - Timeout: 10-15 seconds

## Dashboard Status Fix

Once RTU communication is confirmed working:

1. **Check application logs** for specific errors
2. **Verify database connectivity** between production and local
3. **Check data parsing logic** in the application
4. **Restart Modbus service** if needed
5. **Update RTU status** in dashboard manually if needed

## Troubleshooting Commands

```bash
# Check system logs
sudo journalctl -f

# Check application logs (adjust path as needed)
tail -f /var/log/modbus-service.log

# Check Python Modbus service
ps aux | grep python
ps aux | grep modbus

# Test Modbus with Python
python3 -c "
from pymodbus.client import ModbusTcpClient
client = ModbusTcpClient('192.168.1.1', port=502, timeout=10)
if client.connect():
    result = client.read_holding_registers(40001, 2, slave=1)
    print('Success:', result.registers if not result.isError() else result)
    client.close()
else:
    print('Connection failed')
"
```