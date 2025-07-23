# API Documentation - Energy Monitor

## POST /api/readings

This endpoint accepts readings from the Python Modbus polling service.

### Request Format

**URL:** `POST /api/readings`  
**Content-Type:** `application/json`

### Request Body

```json
{
  "device_id": 3,
  "parameter": "Voltage (L-N)",
  "value": 228.6,
  "timestamp": "2025-07-08T16:00:00Z"
}
```

### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| device_id | integer | Yes | ID of the device from the devices table |
| parameter | string | Yes | Parameter name that matches a register's parameter_name |
| value | number | Yes | The reading value |
| timestamp | string | Yes | ISO 8601 timestamp in format: YYYY-MM-DDTHH:MM:SSZ |

### Response Format

#### Success Response (201 Created)

```json
{
  "success": true,
  "message": "Reading stored successfully",
  "data": {
    "reading_id": 123,
    "device_id": 3,
    "parameter": "Voltage (L-N)",
    "value": 228.6,
    "timestamp": "2025-07-08T16:00:00.000000Z"
  }
}
```

#### Error Responses

**Validation Error (422 Unprocessable Entity)**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "device_id": ["The device id field is required."],
    "value": ["The value must be a number."]
  }
}
```

**Register Not Found (404 Not Found)**
```json
{
  "success": false,
  "message": "Register not found for device 3 and parameter 'Invalid Parameter'"
}
```

**Server Error (500 Internal Server Error)**
```json
{
  "success": false,
  "message": "Internal server error while storing reading"
}
```

### Alert Generation

The endpoint automatically checks for alert conditions and creates alerts when:

1. **Out of Range Values**: When the reading value is outside the normal_range defined in the register
2. **Off-Hours Readings**: When readings are received between 10 PM and 6 AM

### Alert Severity Levels

- **critical**: For out-of-range values where the register is marked as critical
- **warning**: For out-of-range values where the register is not critical
- **info**: For off-hours readings

### Example Usage

#### Python Example (using requests)

```python
import requests
import json
from datetime import datetime

# Prepare the payload
payload = {
    "device_id": 3,
    "parameter": "Voltage (L-N)",
    "value": 228.6,
    "timestamp": datetime.utcnow().strftime('%Y-%m-%dT%H:%M:%SZ')
}

# Send the request
response = requests.post(
    'http://your-domain.com/api/readings',
    json=payload,
    headers={'Content-Type': 'application/json'}
)

# Check the response
if response.status_code == 201:
    print("Reading stored successfully")
    print(response.json())
else:
    print(f"Error: {response.status_code}")
    print(response.json())
```

#### cURL Example

```bash
curl -X POST http://your-domain.com/api/readings \
  -H "Content-Type: application/json" \
  -d '{
    "device_id": 3,
    "parameter": "Voltage (L-N)",
    "value": 228.6,
    "timestamp": "2025-07-08T16:00:00Z"
  }'
```

### Prerequisites

Before using this endpoint, ensure:

1. The device exists in the `devices` table
2. A register exists with the matching `device_id` and `parameter_name`
3. The register has a `normal_range` defined for alert generation (format: "min-max", e.g., "220-240")

### Logging

All successful readings and alerts are logged to the Laravel log file for monitoring and debugging purposes. 