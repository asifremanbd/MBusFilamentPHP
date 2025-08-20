#!/usr/bin/env python3
"""
Fix timestamp format in poller.py
"""

import re

# Read the poller.py file
with open('/MBusFilamentPHP/python-modbus-service/poller.py', 'r') as f:
    content = f.read()

# Replace the timestamp format
old_pattern = r'datetime\.now\(timezone\.utc\)\.isoformat\(\)'
new_pattern = r'datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")'

content = re.sub(old_pattern, new_pattern, content)

# Write the fixed content back
with open('/MBusFilamentPHP/python-modbus-service/poller.py', 'w') as f:
    f.write(content)

print("Timestamp format fixed successfully!")