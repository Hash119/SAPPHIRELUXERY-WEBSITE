<?php
/**
 * Sapphire Aesthetic & Luxury - 1-Click Biometric USB Excel Upload API
 * Endpoint called by the Admin PC 1-Click Sync Tool (sync_tool/sync_usb.ps1)
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/excel_reader.php';

// Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed. Use POST.']);
    exit;
}

// 1. API Key Authentication
$apiKeyHeader = $_SERVER['HTTP_X_API_KEY'] ?? ($_POST['api_key'] ?? '');
$expectedKey = get_setting('machine_sync_api_key', 'sapphire_secret_sync_key_2026');

if (empty($apiKeyHeader) || $apiKeyHeader !== $expectedKey) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized: Invalid or missing API Key.']);
    exit;
}

// 2. Validate File Upload
if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
    $errCode = $_FILES['excel_file']['error'] ?? 'No file';
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => "Excel file upload failed (Code: {$errCode})"]);
    exit;
}

$file = $_FILES['excel_file'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($ext !== 'xlsx') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid file format. Only .xlsx files exported from the attendance machine are supported.']);
    exit;
}

if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed on server.']);
    exit;
}

try {
    // Parse Excel via pure PHP parser
    $parsed = ZkReportReader::parse($file['tmp_name']);
    
    $pdo->beginTransaction();

    // Pre-fetch all employee mapping (machine_user_id -> id)
    $empMap = $pdo->query("SELECT machine_user_id, id, shift_start, grace_period_mins FROM employees")->fetchAll(PDO::FETCH_UNIQUE);

    $insertLogStmt = $pdo->prepare("
        INSERT INTO attendance_logs 
        (employee_id, machine_user_id, date, check_in, check_out, work_hours, late_minutes, early_leave_minutes, overtime_minutes, status, source, raw_punches)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Machine_Excel', ?)
        ON DUPLICATE KEY UPDATE
        check_in = VALUES(check_in),
        check_out = VALUES(check_out),
        work_hours = VALUES(work_hours),
        late_minutes = VALUES(late_minutes),
        early_leave_minutes = VALUES(early_leave_minutes),
        overtime_minutes = VALUES(overtime_minutes),
        status = VALUES(status),
        source = VALUES(source),
        raw_punches = VALUES(raw_punches)
    ");

    $insertedCount = 0;
    $totalLate = 0;

    foreach ($parsed['employees'] as $mId => $empData) {
        // Auto-create employee if not found
        if (!isset($empMap[$mId])) {
            $autoEmpStmt = $pdo->prepare("
                INSERT INTO employees 
                (machine_user_id, emp_no, name, calling_name, department, role, password, status)
                VALUES (?, ?, ?, ?, ?, 'employee', ?, 'active')
            ");
            $empNo = sprintf('EMP%03d', $mId);
            $defPass = password_hash('123456', PASSWORD_DEFAULT);
            $autoEmpStmt->execute([$mId, $empNo, $empData['name'], $empData['name'], $empData['department'], $defPass]);
            $newEmpId = $pdo->lastInsertId();
            $empMap[$mId] = ['id' => $newEmpId, 'shift_start' => '09:00:00', 'grace_period_mins' => 15];

            $pdo->prepare("INSERT INTO leave_balances (employee_id, year, annual_total, casual_total, monthly_quota) VALUES (?, ?, 14.0, 7.0, 4.0)")->execute([$newEmpId, $parsed['year']]);
        }

        $empDbId = $empMap[$mId]['id'];

        foreach ($empData['records'] as $dateStr => $rec) {
            $insertLogStmt->execute([
                $empDbId,
                $mId,
                $rec['date'],
                $rec['check_in'],
                $rec['check_out'],
                $rec['work_hours'],
                $rec['late_minutes'],
                $rec['early_leave_minutes'],
                $rec['overtime_minutes'],
                $rec['status'],
                $rec['raw_punches']
            ]);
            $insertedCount++;
            if ($rec['late_minutes'] > 0) $totalLate++;
        }
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => "Successfully imported {$insertedCount} attendance records from USB Excel!",
        'file_name' => $file['name'],
        'total_employees' => $parsed['total_employees_found'],
        'total_records' => $insertedCount,
        'total_late' => $totalLate,
        'date_range' => ($parsed['start_date'] ?? '') . ' to ' . ($parsed['end_date'] ?? '')
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => "Processing failed: " . $e->getMessage()
    ]);
}
