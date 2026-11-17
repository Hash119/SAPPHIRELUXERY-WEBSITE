/**
 * Sapphire Aesthetic & Luxury - Node.js Machine Sync Tool
 * Alternative to Python script. Runs directly with Node.js!
 * 
 * Install dependency:
 *   npm install node-zklib
 * 
 * Run:
 *   node machine_sync.js
 */

const ZKLib = require('node-zklib');

const DEVICE_IP = '192.168.1.201';
const DEVICE_PORT = 4370;
const CLOUD_API_URL = 'https://staff.sapphireaesthetic.com/api/sync_attendance.php';
const API_KEY = 'sapphire_secret_sync_key_2026';

async function syncAttendance() {
    console.log(`[${new Date().toISOString()}] Connecting to machine at ${DEVICE_IP}:${DEVICE_PORT}...`);
    const zk = new ZKLib(DEVICE_IP, DEVICE_PORT, 5000, 4000);

    try {
        await zk.createSocket();
        console.log('Socket created. Connecting...');
        
        const logs = await zk.getAttendances();
        console.log(`Fetched ${logs.data ? logs.data.length : 0} attendance logs.`);

        await zk.disconnect();

        if (!logs.data || logs.data.length === 0) {
            console.log('No attendance logs found on machine.');
            return;
        }

        const records = logs.data.map(log => ({
            user_id: parseInt(log.deviceUserId),
            timestamp: new Date(log.recordTime).toISOString().replace('T', ' ').substring(0, 19),
            status: 0
        }));

        console.log(`Pushing ${records.length} logs to Hostinger cloud...`);
        const response = await fetch(CLOUD_API_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-API-KEY': API_KEY
            },
            body: JSON.stringify({ records })
        });

        const result = await response.json();
        console.log('Result from server:', result);

    } catch (err) {
        console.error('Error during sync:', err.message);
    }
}

syncAttendance();
