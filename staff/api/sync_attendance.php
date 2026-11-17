<?php
/**
 * Sapphire Aesthetic & Luxury - Realtime Biometric Machine Sync API
 * Endpoint called by the local office PC sync tool (sync_tool/machine_sync.py)
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

// Method check
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

// 1. API Key Authentication
$apiKeyHeader = $_SERVER['HTTP_X_API_KEY'] ?? '';
$inputRaw = file_get_contents('php://input');
$data = json_decode($inputRaw, true) ?: [];

$apiKey = $apiKeyHeader ?: ($data['api_key'] ?? '');
$expectedKey = get_setting('machine_sync_api_key', 'sapphire_secret_sync_key_2026');

if (empty($apiKey) || $apiKey !== $expectedKey) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized: Invalid API Key']);
    exit;
}

if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database not connected on server']);
    exit;
}

// 2. Process Records
$records = $data['records'] ?? [];
if (empty($records)) {
    echo json_encode(['success' => true, 'processed' => 0, 'message' => 'No records provided']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Map machine_user_id -> emp row
    $empMap = $pdo->query("SELECT machine_user_id, id, shift_start, shift_end, grace_period_mins FROM employees")->fetchAll(PDO::FETCH_UNIQUE);

    // Group punches by (machine_user_id + date)
    $grouped = [];
    foreach ($records as $r) {
        $mId = (int)($r['user_id'] ?? 0);
        $timeStr = $r['timestamp'] ?? '';
        if (!$mId || empty($timeStr)) continue;

        $t = strtotime($timeStr);
        if (!$t) continue;

        $dateStr = date('Y-m-d', $t);
        $hourMin = date('H:i', $t);

        $grouped[$mId][$dateStr][] = $hourMin;
    }

    $processedCount = 0;

    $stmt = $pdo->prepare("
        INSERT INTO attendance_logs 
        (employee_id, machine_user_id, date, check_in, check_out, work_hours, late_minutes, early_leave_minutes, overtime_minutes, status, source, raw_punches)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Machine_IP', ?)
        ON DUPLICATE KEY UPDATE 
        check_in = VALUES(check_in),
        check_out = VALUES(check_out),
        work_hours = VALUES(work_hours),
        late_minutes = VALUES(late_minutes),
        early_leave_minutes = VALUES(early_leave_minutes),
        overtime_minutes = VALUES(overtime_minutes),
        status = VALUES(status),
        source = 'Machine_IP',
        raw_punches = VALUES(raw_punches)
    ");

    foreach ($grouped as $mId => $dates) {
        if (!isset($empMap[$mId])) {
            // Auto create employee if machine ID is new
            $autoStmt = $pdo->prepare("INSERT INTO employees (machine_user_id, emp_no, name, calling_name, department, password, status) VALUES (?, ?, ?, ?, 'Sapphire', ?, 'active')");
            $empNo = sprintf('EMP%03d', $mId);
            $defPass = password_hash('123456', PASSWORD_DEFAULT);
            $autoStmt->execute([$mId, $empNo, 'Staff #' . $mId, 'Staff #' . $mId, $defPass]);
            $newId = $pdo->lastInsertId();
            $empMap[$mId] = ['id' => $newId, 'shift_start' => '09:00:00', 'shift_end' => '18:00:00', 'grace_period_mins' => 15];
        }

        $emp = $empMap[$mId];
        $empId = $emp['id'];

        foreach ($dates as $dateStr => $punches) {
            sort($punches);
            $punches = array_unique($punches);

            $firstPunch = reset($punches);
            $lastPunch = count($punches) > 1 ? end($punches) : null;

            $workHours = 0.00;
            $lateMins = 0;
            $earlyMins = 0;
            $otMins = 0;
            $status = 'Present';

            $shiftStart = strtotime($dateStr . ' ' . ($emp['shift_start'] ?? '09:00:00'));
            $graceStart = strtotime($dateStr . ' ' . date('H:i:s', $shiftStart + (($emp['grace_period_mins'] ?? 15) * 60)));
            $shiftEnd   = strtotime($dateStr . ' ' . ($emp['shift_end'] ?? '18:00:00'));

            if ($firstPunch && $lastPunch) {
                $inT = strtotime($dateStr . ' ' . $firstPunch . ':00');
                $outT = strtotime($dateStr . ' ' . $lastPunch . ':00');
                $workHours = round(max(0, $outT - $inT) / 3600, 2);

                if ($inT > $graceStart) {
                    $lateMins = round(($inT - $shiftStart) / 60);
                    $status = 'Late';
                }
                if ($outT < $shiftEnd) {
                    $earlyMins = round(($shiftEnd - $outT) / 60);
                }
                if ($outT > $shiftEnd) {
                    $otMins = round(($outT - $shiftEnd) / 60);
                }
            } elseif ($firstPunch) {
                $inT = strtotime($dateStr . ' ' . $firstPunch . ':00');
                if ($inT > $graceStart) {
                    $lateMins = round(($inT - $shiftStart) / 60);
                    $status = 'Late';
                }
            }

            $stmt->execute([
                $empId,
                $mId,
                $dateStr,
                $firstPunch ? $firstPunch . ':00' : null,
                $lastPunch ? $lastPunch . ':00' : null,
                $workHours,
                $lateMins,
                $earlyMins,
                $otMins,
                $status,
                implode(',', $punches)
            ]);
            $processedCount++;
        }
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'processed' => $processedCount,
        'message' => "Successfully processed {$processedCount} attendance logs."
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
