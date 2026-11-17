"""
Sapphire Aesthetic & Luxury - Local Machine to Cloud Sync Utility
Run this script on the Salon Reception / Office PC connected to the same Wi-Fi as the biometric machine.

Requirements:
  pip install requests pyzk

Usage:
  python machine_sync.py
"""

import sys
import time
import requests
from datetime import datetime

# ================= CONFIGURATION =================
DEVICE_IP = "192.168.1.201"      # IP address of the biometric machine
DEVICE_PORT = 4370                # Standard ZKTeco port
CLOUD_API_URL = "https://staff.sapphireaesthetic.com/api/sync_attendance.php"
API_KEY = "sapphire_secret_sync_key_2026"
# =================================================

def sync_from_device():
    print(f"[{datetime.now().strftime('%Y-%m-%d %H:%M:%S')}] Connecting to machine at {DEVICE_IP}:{DEVICE_PORT}...")
    
    try:
        from zk import ZK
        zk = ZK(DEVICE_IP, port=DEVICE_PORT, timeout=5)
        conn = zk.connect()
        print("Connected successfully to Biometric Machine!")
        
        # Disable device temporarily during reading
        conn.disable_device()
        attendances = conn.get_attendance()
        print(f"Fetched {len(attendances)} raw attendance logs from device.")
        
        # Prepare payload
        records = []
        for att in attendances:
            records.append({
                "user_id": int(att.user_id),
                "timestamp": att.timestamp.strftime('%Y-%m-%d %H:%M:%S'),
                "status": att.status
            })
            
        conn.enable_device()
        conn.disconnect()
        
        if not records:
            print("No records to sync.")
            return

        # Push to Hostinger Cloud API
        print(f"Pushing {len(records)} records to {CLOUD_API_URL}...")
        headers = {
            "Content-Type": "application/json",
            "X-API-KEY": API_KEY
        }
        resp = requests.post(CLOUD_API_URL, json={"records": records}, headers=headers, timeout=15)
        
        if resp.status_code == 200:
            res_data = resp.json()
            print(f"SUCCESS: {res_data.get('message', 'Records synced!')}")
        else:
            print(f"FAILED (Status {resp.status_code}): {resp.text}")

    except ImportError:
        print("Error: 'pyzk' and 'requests' packages are required.")
        print("Install them using: pip install requests pyzk")
    except Exception as e:
        print(f"Sync error: {e}")

if __name__ == "__main__":
    sync_from_device()
