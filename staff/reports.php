<?php
/**
 * Sapphire Aesthetic & Luxury - Comprehensive Reports Module
 * 1. Date Range In/Out, Late/OT & Adjusted No-Pay Timesheet Report (Company & Individual)
 * 2. Active / Inactive Staff Details Report
 * 3. Google Calendar & Sri Lanka Holidays Integration (Poya & Mercantile)
 */
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/holidays.php';
require_login();

$user = current_user();
if (!is_admin_or_manager($user)) {
    header('Location: employee_portal.php');
    exit;
}

$pageTitle = 'Reports & Analytics';
$message = '';
$error = '';

// Handle Google Calendar Sync Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sync_action']) && $_POST['sync_action'] === 'sync_gcal_holidays') {
    $syncRes = sync_google_calendar_holidays($pdo);
    if ($syncRes['success']) {
        $message = $syncRes['message'];
    } else {
        $error = $syncRes['message'];
    }
}

// Active Tab (attendance vs staff)
$tab = $_GET['tab'] ?? 'attendance';

// ==========================================
// 1. ATTENDANCE & LEAVE REPORT (DATE RANGE)
// ==========================================
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate   = $_GET['end_date'] ?? date('Y-m-d');
$selectedDept = $_GET['department'] ?? '';
$selectedEmpId = (int)($_GET['employee_id'] ?? 0);
$viewMode = $_GET['view_mode'] ?? ($selectedEmpId > 0 ? 'employee_statement' : 'summary');

// Holidays map & stats
$holidaysMap = get_holidays_map($pdo, $startDate, $endDate);
$holidayCount = 0;
try {
    $holidayCount = (int)$pdo->query("SELECT COUNT(*) FROM holidays")->fetchColumn();
} catch (Exception $e) {}

// Calculate working days in period (up to today — 7-day open clinic: Sundays & Poya Days are working days)
$todayStr = date('Y-m-d');
$curPeriod = strtotime($startDate);
$endPeriod = min(strtotime($endDate), strtotime($todayStr));
$workingDaysInPeriod = 0;
if ($curPeriod <= $endPeriod) {
    while ($curPeriod <= $endPeriod) {
        $workingDaysInPeriod++;
        $curPeriod = strtotime('+1 day', $curPeriod);
    }
}

// Fetch all employees for selection dropdown
$allEmployees = [];
try {
    $allEmployees = $pdo->query("SELECT id, machine_user_id, emp_no, name, calling_name, department FROM employees ORDER BY machine_user_id ASC")->fetchAll();
} catch (Exception $e) {}

// Individual Employee Timesheet Data
$employeeTimesheet = null;
if ($selectedEmpId > 0 && isset($pdo)) {
    $employeeTimesheet = calculate_employee_timesheet($pdo, $selectedEmpId, $startDate, $endDate);
}

// Helper to build Full Detailed Report (with Late Minutes, Late Hours & Hourly Decimal Breakdown)
function build_full_detail_report($pdo, $startDate, $endDate, $selectedDept, $workingDaysInPeriod) {
    $sql = "
        SELECT e.id, e.machine_user_id, e.emp_no, e.name, e.calling_name, e.department, e.designation, e.employment_type, e.status,
               SUM(CASE WHEN a.status IN ('Present', 'Late', 'Half Day') OR (a.check_in IS NOT NULL AND a.check_in != '00:00:00') THEN 1 ELSE 0 END) as present_days,
               SUM(COALESCE(a.work_hours, 0)) as total_work_hrs,
               SUM(CASE WHEN a.late_minutes > 0 THEN 1 ELSE 0 END) as late_count,
               SUM(COALESCE(a.late_minutes, 0)) as total_late_mins,
               SUM(COALESCE(a.early_leave_minutes, 0)) as total_early_mins,
               SUM(COALESCE(a.overtime_minutes, 0)) as total_ot_mins
        FROM employees e
        LEFT JOIN attendance_logs a ON e.id = a.employee_id AND a.date BETWEEN :s_date AND :e_date
        WHERE e.machine_user_id != 999
    ";
    $p = [':s_date' => $startDate, ':e_date' => $endDate];
    if (!empty($selectedDept)) { $sql .= " AND e.department = :dept"; $p[':dept'] = $selectedDept; }
    $sql .= " GROUP BY e.id ORDER BY e.machine_user_id ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($p);
    $rows = $stmt->fetchAll();

    // Fetch approved leaves overlapping the period for exact leave day counts
    $lStmt = $pdo->prepare("
        SELECT employee_id, leave_type, start_date, end_date, total_days
        FROM leaves
        WHERE status = 'Approved' AND NOT (end_date < ? OR start_date > ?)
    ");
    $lStmt->execute([$startDate, $endDate]);
    $leavesByEmp = [];
    foreach ($lStmt->fetchAll() as $lv) {
        $empId = $lv['employee_id'];
        if (!isset($leavesByEmp[$empId])) $leavesByEmp[$empId] = ['paid' => 0.0, 'nopay' => 0.0];
        $ovStart = max(strtotime($startDate), strtotime($lv['start_date']));
        $ovEnd   = min(strtotime($endDate), strtotime($lv['end_date']));
        if ($ovStart <= $ovEnd) {
            $overlapDays = ((float)$lv['total_days'] < 1 && $lv['start_date'] === $lv['end_date'])
                ? (float)$lv['total_days']
                : (round(($ovEnd - $ovStart) / 86400) + 1);
            if ($lv['leave_type'] === 'No Pay') {
                $leavesByEmp[$empId]['nopay'] += $overlapDays;
            } else {
                $leavesByEmp[$empId]['paid'] += $overlapDays;
            }
        }
    }

    $result = [];
    $grand = [
        'staff_count' => 0, 'present_days' => 0, 'leave_days' => 0.0, 'nopay_days' => 0.0,
        'total_work_hrs' => 0.0, 'late_count' => 0, 'total_late_mins' => 0,
        'total_early_mins' => 0, 'total_ot_mins' => 0
    ];

    foreach ($rows as $r) {
        $eid = $r['id'];
        $pDays     = (int)($r['present_days'] ?? 0);
        $workHrs   = round((float)($r['total_work_hrs'] ?? 0), 2);
        $lateCnt   = (int)($r['late_count'] ?? 0);
        $lateMins  = (int)($r['total_late_mins'] ?? 0);
        $earlyMins = (int)($r['total_early_mins'] ?? 0);
        $otMins    = (int)($r['total_ot_mins'] ?? 0);
        $paidLeave = (float)($leavesByEmp[$eid]['paid'] ?? 0.0);
        $adjNoPay  = max(0, round($workingDaysInPeriod - ($pDays + $paidLeave), 1));

        $lateHrsDec  = round($lateMins / 60, 2);
        $earlyHrsDec = round($earlyMins / 60, 2);
        $otHrsDec    = round($otMins / 60, 2);
        $avgWorkDay  = $pDays > 0 ? round($workHrs / $pDays, 2) : 0.0;
        $avgLateDay  = $lateCnt > 0 ? round($lateMins / $lateCnt, 1) : 0.0;

        $r['present_days']     = $pDays;
        $r['leave_days']       = $paidLeave;
        $r['nopay_days']       = $adjNoPay;
        $r['total_work_hrs']   = $workHrs;
        $r['avg_work_hrs']     = $avgWorkDay;
        $r['late_count']       = $lateCnt;
        $r['total_late_mins']  = $lateMins;
        $r['late_hrs_decimal'] = $lateHrsDec;
        $r['late_hm']          = format_minutes($lateMins);
        $r['avg_late_mins']    = $avgLateDay;
        $r['total_early_mins'] = $earlyMins;
        $r['early_hrs_decimal']= $earlyHrsDec;
        $r['total_ot_mins']    = $otMins;
        $r['ot_hrs_decimal']   = $otHrsDec;
        $r['net_hours']        = round(max(0, $workHrs - $lateHrsDec), 2);

        $result[] = $r;

        $grand['staff_count']++;
        $grand['present_days']     += $pDays;
        $grand['leave_days']       += $paidLeave;
        $grand['nopay_days']       += $adjNoPay;
        $grand['total_work_hrs']   += $workHrs;
        $grand['late_count']       += $lateCnt;
        $grand['total_late_mins']  += $lateMins;
        $grand['total_early_mins'] += $earlyMins;
        $grand['total_ot_mins']    += $otMins;
    }

    $grand['late_hrs_decimal']  = round($grand['total_late_mins'] / 60, 2);
    $grand['early_hrs_decimal'] = round($grand['total_early_mins'] / 60, 2);
    $grand['ot_hrs_decimal']    = round($grand['total_ot_mins'] / 60, 2);
    $grand['total_work_hrs']    = round($grand['total_work_hrs'], 2);

    return ['rows' => $result, 'grand' => $grand];
}

// Handle Attendance CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'attendance_csv') {
    if ($viewMode === 'full_detail' && isset($pdo)) {
        $fdData = build_full_detail_report($pdo, $startDate, $endDate, $selectedDept, $workingDaysInPeriod);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=Sapphire_Full_Detail_Late_Hourly_Report_' . $startDate . '_to_' . $endDate . '.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['SAPPHIRE AESTHETIC & LUXURY - FULL DETAILED ATTENDANCE, LATE MINUTES & HOURLY BREAKDOWN REPORT']);
        fputcsv($output, ['Period:', $startDate . ' to ' . $endDate, 'Working Days (7-Day Clinic):', $workingDaysInPeriod, 'Generated:', date('Y-m-d H:i:s')]);
        fputcsv($output, []);
        fputcsv($output, [
            'Machine ID', 'Emp Code', 'Employee Name', 'Department', 'Designation',
            'Present Days', 'Approved Leaves', 'Adjusted No-Pay Days',
            'Total Work Hours', 'Avg Work Hrs/Day',
            'Late Days', 'Total Late (Mins)', 'Total Late (Hrs & Mins)', 'Total Late Hours (Hourly Decimal)', 'Avg Late Mins/Late Day',
            'Early Leave (Mins)', 'Early Leave (Hourly Decimal)',
            'OT (Mins)', 'OT Hours (Hourly Decimal)', 'Net Effective Hours'
        ]);
        foreach ($fdData['rows'] as $r) {
            fputcsv($output, [
                '#' . $r['machine_user_id'], $r['emp_no'], $r['name'], $r['department'], $r['designation'],
                $r['present_days'], $r['leave_days'], $r['nopay_days'],
                $r['total_work_hrs'], $r['avg_work_hrs'],
                $r['late_count'], $r['total_late_mins'], $r['late_hm'], $r['late_hrs_decimal'], $r['avg_late_mins'],
                $r['total_early_mins'], $r['early_hrs_decimal'],
                $r['total_ot_mins'], $r['ot_hrs_decimal'], $r['net_hours']
            ]);
        }
        $g = $fdData['grand'];
        fputcsv($output, []);
        fputcsv($output, [
            'GRAND TOTAL', '', $g['staff_count'] . ' Staff', '', '',
            $g['present_days'], $g['leave_days'], $g['nopay_days'],
            $g['total_work_hrs'], '',
            $g['late_count'], $g['total_late_mins'], format_minutes($g['total_late_mins']), $g['late_hrs_decimal'], '',
            $g['total_early_mins'], $g['early_hrs_decimal'],
            $g['total_ot_mins'], $g['ot_hrs_decimal'], round(max(0, $g['total_work_hrs'] - $g['late_hrs_decimal']), 2)
        ]);
        fclose($output);
        exit;
    } elseif ($selectedEmpId > 0 && $employeeTimesheet && $employeeTimesheet['employee']) {
        $emp = $employeeTimesheet['employee'];
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=Sapphire_Timesheet_' . $emp['emp_no'] . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $emp['name']) . '_' . $startDate . '_to_' . $endDate . '.csv');
        $output = fopen('php://output', 'w');

        fputcsv($output, ['SAPPHIRE AESTHETIC & LUXURY - INDIVIDUAL EMPLOYEE TIMESHEET']);
        fputcsv($output, ['Employee Name:', $emp['name'], 'Calling Name:', $emp['calling_name']]);
        fputcsv($output, ['Machine ID:', '#' . $emp['machine_user_id'], 'Emp Code:', $emp['emp_no'], 'Department:', $emp['department']]);
        fputcsv($output, ['Date Range:', $startDate . ' to ' . $endDate, 'Generated On:', date('Y-m-d H:i:s')]);
        fputcsv($output, []);
        fputcsv($output, ['SUMMARY METRICS']);
        fputcsv($output, ['Calendar Days', 'Eligible Work Days', 'Present Days', 'Late Count', 'Total Late (Mins)', 'Overtime (Hours)', 'Approved Leaves', 'No-Pay Days (Loss of Pay)', 'Holidays & Off Days']);
        fputcsv($output, [
            $employeeTimesheet['totals']['calendar_days'],
            $employeeTimesheet['totals']['working_days'],
            $employeeTimesheet['totals']['present_days'],
            $employeeTimesheet['totals']['late_count'],
            $employeeTimesheet['totals']['total_late_mins'],
            round($employeeTimesheet['totals']['total_ot_mins'] / 60, 2),
            $employeeTimesheet['totals']['leave_days'],
            $employeeTimesheet['totals']['nopay_days'],
            $employeeTimesheet['totals']['holiday_days'] + $employeeTimesheet['totals']['off_days']
        ]);
        fputcsv($output, []);
        fputcsv($output, ['DAILY ATTENDANCE BREAKDOWN']);
        fputcsv($output, ['Date', 'Day', 'Check In', 'Check Out', 'Work Hours', 'Late Mins', 'OT Mins', 'Status', 'Adjusted Loss of Pay', 'Remarks / Holiday']);

        foreach ($employeeTimesheet['days'] as $dayRow) {
            fputcsv($output, [
                $dayRow['date'],
                $dayRow['day'],
                $dayRow['check_in'] ? date('h:i A', strtotime($dayRow['check_in'])) : '-',
                $dayRow['check_out'] ? date('h:i A', strtotime($dayRow['check_out'])) : '-',
                $dayRow['work_hours'] > 0 ? $dayRow['work_hours'] . 'h' : '-',
                $dayRow['late_minutes'] > 0 ? '+' . $dayRow['late_minutes'] . 'm' : '-',
                $dayRow['overtime_minutes'] > 0 ? '+' . $dayRow['overtime_minutes'] . 'm' : '-',
                $dayRow['status'],
                $dayRow['is_nopay'] ? 'YES (No-Pay)' : 'No',
                $dayRow['notes']
            ]);
        }
        fclose($output);
        exit;
    } elseif ($viewMode === 'detailed') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=Sapphire_Attendance_Punches_' . $startDate . '_to_' . $endDate . '.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Date', 'Day', 'Machine ID', 'Employee Name', 'Department', 'Check In', 'Check Out', 'Work Hours', 'Late Mins', 'OT Mins', 'Status', 'Source', 'Remarks']);
        $detSql = "
            SELECT a.*, e.machine_user_id, e.name as emp_name, e.department
            FROM attendance_logs a
            JOIN employees e ON a.employee_id = e.id
            WHERE a.date BETWEEN ? AND ?
        ";
        $p = [$startDate, $endDate];
        if (!empty($selectedDept)) { $detSql .= " AND e.department = ?"; $p[] = $selectedDept; }
        $detSql .= " ORDER BY a.date ASC, e.machine_user_id ASC";
        $stmt = $pdo->prepare($detSql);
        $stmt->execute($p);
        while ($r = $stmt->fetch()) {
            fputcsv($output, [
                $r['date'], date('l', strtotime($r['date'])), $r['machine_user_id'], $r['emp_name'], $r['department'],
                $r['check_in'] ?: '-', $r['check_out'] ?: '-', $r['work_hours'], $r['late_minutes'], $r['overtime_minutes'],
                $r['status'], $r['source'], $r['remarks']
            ]);
        }
        fclose($output);
        exit;
    } else {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=Sapphire_Attendance_Summary_' . $startDate . '_to_' . $endDate . '.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Machine ID', 'Employee Code', 'Employee Name', 'Department', 'Gender', 'Present Days', 'Late Count', 'Total Late Mins', 'Early Leave Mins', 'OT Minutes', 'OT Hours', 'Approved Leave Days', 'Adjusted No-Pay Days', 'Raw Absent Days']);
        $sumSql = "
            SELECT e.machine_user_id, e.emp_no, e.name, e.department, e.gender,
                   SUM(CASE WHEN a.status IN ('Present', 'Late', 'Half Day') THEN 1 ELSE 0 END) as present_days,
                   SUM(CASE WHEN a.late_minutes > 0 THEN 1 ELSE 0 END) as late_count,
                   SUM(COALESCE(a.late_minutes, 0)) as total_late_mins,
                   SUM(COALESCE(a.early_leave_minutes, 0)) as total_early_mins,
                   SUM(COALESCE(a.overtime_minutes, 0)) as total_ot_mins,
                   SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent_days,
                   SUM(CASE WHEN a.status = 'On Leave' THEN 1 ELSE 0 END) as leave_days
            FROM employees e
            LEFT JOIN attendance_logs a ON e.id = a.employee_id AND a.date BETWEEN ? AND ?
            WHERE 1=1
        ";
        $p = [$startDate, $endDate];
        if (!empty($selectedDept)) { $sumSql .= " AND e.department = ?"; $p[] = $selectedDept; }
        $sumSql .= " GROUP BY e.id ORDER BY e.machine_user_id ASC";
        $stmt = $pdo->prepare($sumSql);
        $stmt->execute($p);
        while ($r = $stmt->fetch()) {
            $pDays = (float)($r['present_days'] ?: 0);
            $lDays = (float)($r['leave_days'] ?: 0);
            $adjNoPay = max(0, $workingDaysInPeriod - ($pDays + $lDays));
            fputcsv($output, [
                $r['machine_user_id'], $r['emp_no'], $r['name'], $r['department'], $r['gender'],
                $pDays, $r['late_count'] ?: 0, $r['total_late_mins'] ?: 0,
                $r['total_early_mins'] ?: 0, $r['total_ot_mins'] ?: 0, round(($r['total_ot_mins'] ?: 0) / 60, 2),
                $lDays, $adjNoPay, $r['absent_days'] ?: 0
            ]);
        }
        fclose($output);
        exit;
    }
}

// ==========================================
// 2. ACTIVE / INACTIVE STAFF DETAILS REPORT
// ==========================================
$staffStatusFilter = $_GET['staff_status'] ?? 'all';

// Handle Staff Details CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'staff_csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Sapphire_Staff_Directory_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Machine ID', 'Emp Code', 'Full Name', 'Calling Name', 'Email', 'Department', 'Designation', 'Gender', 'Contact Phone', 'Shift Start', 'Shift End', 'Status', 'Maternity Eligible', 'Can Approve Leaves', 'Join Date', 'Date of Appointment']);
    
    $stSql = "SELECT * FROM employees WHERE 1=1";
    $p = [];
    if ($staffStatusFilter === 'active') { $stSql .= " AND status = 'active'"; }
    elseif ($staffStatusFilter === 'inactive') { $stSql .= " AND status = 'inactive'"; }
    if (!empty($selectedDept)) { $stSql .= " AND department = ?"; $p[] = $selectedDept; }
    $stSql .= " ORDER BY status ASC, machine_user_id ASC";
    $stmt = $pdo->prepare($stSql);
    $stmt->execute($p);
    while ($r = $stmt->fetch()) {
        fputcsv($output, [
            $r['machine_user_id'], $r['emp_no'], $r['name'], $r['calling_name'], $r['email'] ?? '', $r['department'],
            $r['designation'], $r['gender'], $r['phone'], $r['shift_start'], $r['shift_end'],
            ucfirst($r['status']), $r['maternity_eligible'] ? 'Active (84 Days)' : 'No',
            $r['can_approve_leave'] ? 'Yes' : 'No', $r['join_date'], $r['appointment_date'] ?? ''
        ]);
    }
    fclose($output);
    exit;
}

// Data Collections
$departments = [];
$attendanceSummary = [];
$attendanceDetailed = [];
$fullDetailReport = ['rows' => [], 'grand' => []];
$staffList = [];
$activeCount = 0;
$inactiveCount = 0;
$maternityActiveCount = 0;

if (isset($pdo)) {
    try {
        $departments = $pdo->query("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL AND department != '' ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);

        if ($tab === 'attendance') {
            if ($viewMode === 'full_detail') {
                $fullDetailReport = build_full_detail_report($pdo, $startDate, $endDate, $selectedDept, $workingDaysInPeriod);
            } elseif ($viewMode === 'detailed') {
                $sql = "
                    SELECT a.*, e.machine_user_id, e.name as emp_name, e.calling_name, e.department, e.gender
                    FROM attendance_logs a
                    JOIN employees e ON a.employee_id = e.id
                    WHERE a.date BETWEEN :s_date AND :e_date
                ";
                $p = [':s_date' => $startDate, ':e_date' => $endDate];
                if (!empty($selectedDept)) { $sql .= " AND e.department = :dept"; $p[':dept'] = $selectedDept; }
                if ($selectedEmpId > 0) { $sql .= " AND e.id = :emp_id"; $p[':emp_id'] = $selectedEmpId; }
                $sql .= " ORDER BY a.date DESC, e.machine_user_id ASC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($p);
                $attendanceDetailed = $stmt->fetchAll();
            } elseif ($viewMode === 'summary') {
                $sql = "
                    SELECT e.id, e.machine_user_id, e.emp_no, e.name, e.calling_name, e.department, e.gender, e.status, e.maternity_eligible,
                           SUM(CASE WHEN a.status IN ('Present', 'Late', 'Half Day') THEN 1 ELSE 0 END) as present_days,
                           SUM(CASE WHEN a.late_minutes > 0 THEN 1 ELSE 0 END) as late_count,
                           SUM(COALESCE(a.late_minutes, 0)) as total_late_mins,
                           SUM(COALESCE(a.early_leave_minutes, 0)) as total_early_mins,
                           SUM(COALESCE(a.overtime_minutes, 0)) as total_ot_mins,
                           SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent_days,
                           SUM(CASE WHEN a.status = 'On Leave' THEN 1 ELSE 0 END) as leave_days
                    FROM employees e
                    LEFT JOIN attendance_logs a ON e.id = a.employee_id AND a.date BETWEEN :s_date AND :e_date
                    WHERE 1=1
                ";
                $p = [':s_date' => $startDate, ':e_date' => $endDate];
                if (!empty($selectedDept)) { $sql .= " AND e.department = :dept"; $p[':dept'] = $selectedDept; }
                $sql .= " GROUP BY e.id ORDER BY e.machine_user_id ASC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($p);
                $attendanceSummary = $stmt->fetchAll();
            }
        } elseif ($tab === 'staff') {
            $sql = "SELECT * FROM employees WHERE 1=1";
            $p = [];
            if ($staffStatusFilter === 'active') { $sql .= " AND status = 'active'"; }
            elseif ($staffStatusFilter === 'inactive') { $sql .= " AND status = 'inactive'"; }
            if (!empty($selectedDept)) { $sql .= " AND department = ?"; $p[] = $selectedDept; }
            $sql .= " ORDER BY status ASC, machine_user_id ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($p);
            $staffList = $stmt->fetchAll();

            $activeCount = (int)$pdo->query("SELECT COUNT(*) FROM employees WHERE status = 'active'")->fetchColumn();
            $inactiveCount = (int)$pdo->query("SELECT COUNT(*) FROM employees WHERE status = 'inactive'")->fetchColumn();
            $maternityActiveCount = (int)$pdo->query("SELECT COUNT(*) FROM employees WHERE maternity_eligible = 1 AND gender = 'Female'")->fetchColumn();
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="space-y-6">
    <!-- Header with Tab Navigation -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl sm:text-2xl font-serif font-bold text-white">Reports & Timesheet Analytics</h2>
            <p class="text-xs text-slate-400 mt-1">Export employee date-range attendance, OT/Late hours, adjusted No-Pay statements & Sri Lanka holidays</p>
        </div>

        <!-- Navigation Tabs -->
        <div class="inline-flex p-1 bg-slate-900 border border-slate-800 rounded-2xl">
            <a href="reports.php?tab=attendance" class="px-4 py-2 rounded-xl text-xs font-semibold transition-all flex items-center gap-2 <?= $tab === 'attendance' ? 'bg-gold-500/15 text-gold-400 border border-gold-500/30' : 'text-slate-400 hover:text-white' ?>">
                <i class="fa-solid fa-calendar-range"></i>
                <span>Timesheets & No-Pay</span>
            </a>
            <a href="reports.php?tab=staff" class="px-4 py-2 rounded-xl text-xs font-semibold transition-all flex items-center gap-2 <?= $tab === 'staff' ? 'bg-gold-500/15 text-gold-400 border border-gold-500/30' : 'text-slate-400 hover:text-white' ?>">
                <i class="fa-solid fa-address-book"></i>
                <span>Active / Inactive Staff</span>
            </a>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if ($message): ?>
        <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-xs flex items-center justify-between gap-3 shadow-lg shadow-emerald-500/10">
            <div class="flex items-center gap-2.5">
                <i class="fa-solid fa-circle-check text-base text-emerald-400"></i>
                <span><?= htmlspecialchars($message) ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-slate-400 hover:text-white text-xs">&times;</button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs flex items-center justify-between gap-3 shadow-lg shadow-rose-500/10">
            <div class="flex items-center gap-2.5">
                <i class="fa-solid fa-triangle-exclamation text-base text-rose-400"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-slate-400 hover:text-white text-xs">&times;</button>
        </div>
    <?php endif; ?>

    <!-- ============================================== -->
    <!-- TAB 1: DATE RANGE ATTENDANCE & LEAVE REPORT   -->
    <!-- ============================================== -->
    <?php if ($tab === 'attendance'): ?>

        <!-- SRI LANKA HOLIDAYS & GOOGLE CALENDAR BANNER -->
        <div class="bg-gradient-to-r from-amber-950/30 via-slate-900 to-slate-900 border border-amber-500/30 rounded-2xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <span class="w-9 h-9 rounded-xl bg-amber-500/15 text-amber-400 flex items-center justify-center text-base shrink-0">
                    <i class="fa-solid fa-moon"></i>
                </span>
                <div>
                    <div class="flex items-center gap-2">
                        <h4 class="text-xs font-bold text-amber-300 uppercase tracking-wider">Sri Lanka Calendar Integration</h4>
                        <span class="px-2 py-0.5 rounded text-[10px] font-mono bg-amber-500/20 text-amber-300 border border-amber-500/40">
                            <?= $holidayCount ?> Holidays & Poya Days Active
                        </span>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-0.5">
                        Sapphire Clinic operates <strong class="text-slate-200">7 days a week</strong> (Sundays &amp; Poya Days are open working days). Holidays are tracked for shift reference; staff take scheduled offs using their <strong class="text-gold-300">4 Monthly Leaves</strong>.
                    </p>
                </div>
            </div>

            <!-- Sync Form -->
            <form method="POST" action="reports.php?tab=attendance&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&employee_id=<?= $selectedEmpId ?>" class="shrink-0">
                <button type="submit" name="sync_action" value="sync_gcal_holidays"
                    class="px-3.5 py-2 bg-amber-500/20 hover:bg-amber-500/30 text-amber-300 border border-amber-500/40 rounded-xl text-xs font-semibold transition-all flex items-center gap-1.5 shadow-md">
                    <i class="fa-solid fa-rotate"></i>
                    <span>Sync Google Calendar</span>
                </button>
            </form>
        </div>

        <!-- Filter Controls -->
        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-4 sm:p-5">
            <form method="GET" action="reports.php" class="flex flex-wrap items-center gap-3">
                <input type="hidden" name="tab" value="attendance">

                <!-- Start Date -->
                <div>
                    <label class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-1">From Date</label>
                    <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>"
                        class="px-3 py-1.5 bg-slate-950 border border-slate-700 rounded-xl text-xs text-slate-100 font-mono focus:border-gold-500 focus:outline-none">
                </div>

                <!-- End Date -->
                <div>
                    <label class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-1">To Date</label>
                    <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>"
                        class="px-3 py-1.5 bg-slate-950 border border-slate-700 rounded-xl text-xs text-slate-100 font-mono focus:border-gold-500 focus:outline-none">
                </div>

                <!-- Department -->
                <div>
                    <label class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Department</label>
                    <select name="department" class="px-3 py-1.5 bg-slate-950 border border-slate-700 rounded-xl text-xs text-slate-100 focus:border-gold-500 focus:outline-none">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= htmlspecialchars($dept) ?>" <?= $selectedDept === $dept ? 'selected' : '' ?>><?= htmlspecialchars($dept) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Employee Selector (Individual statement) -->
                <div>
                    <label class="block text-[10px] font-semibold uppercase tracking-wider text-gold-400 mb-1">Select Employee</label>
                    <select name="employee_id" class="px-3 py-1.5 bg-slate-950 border border-gold-500/50 rounded-xl text-xs text-gold-300 font-medium focus:border-gold-400 focus:outline-none">
                        <option value="">All Employees (Company Overview)</option>
                        <?php foreach ($allEmployees as $empOpt): ?>
                            <option value="<?= $empOpt['id'] ?>" <?= $selectedEmpId === (int)$empOpt['id'] ? 'selected' : '' ?>>
                                [<?= htmlspecialchars($empOpt['emp_no']) ?>] <?= htmlspecialchars($empOpt['name']) ?> (<?= htmlspecialchars($empOpt['department']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- View Mode -->
                <div>
                    <label class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Report View</label>
                    <select name="view_mode" class="px-3 py-1.5 bg-slate-950 border border-slate-700 rounded-xl text-xs text-slate-100 focus:border-gold-500 focus:outline-none">
                        <?php if ($selectedEmpId > 0): ?>
                            <option value="employee_statement" <?= $viewMode === 'employee_statement' ? 'selected' : '' ?>>Individual Employee Statement (Day-by-Day)</option>
                        <?php endif; ?>
                        <option value="summary" <?= $viewMode === 'summary' ? 'selected' : '' ?>>All Staff Summary (No-Pay Adjusted)</option>
                        <option value="full_detail" <?= $viewMode === 'full_detail' ? 'selected' : '' ?>>Full Detail Report (Late Mins, Late Hours &amp; Hourly)</option>
                        <option value="detailed" <?= $viewMode === 'detailed' ? 'selected' : '' ?>>Daily Machine In/Out Punches</option>
                    </select>
                </div>

                <div class="pt-4 flex flex-wrap items-center gap-2">
                    <button type="submit" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-xs font-semibold transition-colors flex items-center gap-1.5">
                        <i class="fa-solid fa-filter"></i> Apply Filter
                    </button>
                    <a href="reports.php?tab=attendance&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&department=<?= urlencode($selectedDept) ?>&view_mode=full_detail"
                        class="px-3.5 py-2 <?= $viewMode === 'full_detail' ? 'bg-indigo-500 text-white shadow-lg shadow-indigo-500/25' : 'bg-indigo-500/15 hover:bg-indigo-500/25 text-indigo-300 border border-indigo-500/30' ?> rounded-xl text-xs font-semibold transition-all flex items-center gap-1.5">
                        <i class="fa-solid fa-table-list"></i> Full Detail &amp; Hourly Report
                    </a>
                    <a href="reports.php?tab=attendance&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&department=<?= urlencode($selectedDept) ?>&employee_id=<?= $selectedEmpId ?>&view_mode=<?= urlencode($viewMode) ?>&export=attendance_csv"
                        class="px-4 py-2 bg-gold-400 hover:bg-gold-500 text-slate-950 rounded-xl text-xs font-semibold shadow-lg shadow-gold-500/20 transition-all flex items-center gap-1.5">
                        <i class="fa-solid fa-file-csv"></i> Export to Excel (CSV)
                    </a>
                    <button type="button" onclick="window.print()" class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs transition-colors">
                        <i class="fa-solid fa-print"></i>
                    </button>
                </div>
            </form>
        </div>

        <!-- ======================================================== -->
        <!-- VIEW 0: FULL DETAILED REPORT (LATE MINS, HOURS & HOURLY) -->
        <!-- ======================================================== -->
        <?php if ($viewMode === 'full_detail'): ?>
            <?php
                $fdRows  = $fullDetailReport['rows'] ?? [];
                $fdGrand = $fullDetailReport['grand'] ?? [
                    'staff_count'=>0,'present_days'=>0,'leave_days'=>0,'nopay_days'=>0,
                    'total_work_hrs'=>0,'late_count'=>0,'total_late_mins'=>0,'late_hrs_decimal'=>0,
                    'total_early_mins'=>0,'early_hrs_decimal'=>0,'total_ot_mins'=>0,'ot_hrs_decimal'=>0
                ];
            ?>
            <div class="space-y-5">
                <!-- Company-Wide Full Detail KPI Strip -->
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                    <div class="bg-slate-900/70 border border-slate-800 rounded-2xl p-3.5">
                        <span class="text-[10px] text-slate-400 block uppercase font-semibold">Period Work Days</span>
                        <span class="text-xl font-bold text-white mt-1 block font-mono"><?= $workingDaysInPeriod ?> Days</span>
                        <span class="text-[10px] text-slate-500"><?= $fdGrand['staff_count'] ?> Staff Tracked</span>
                    </div>
                    <div class="bg-slate-900/70 border border-emerald-500/30 rounded-2xl p-3.5 bg-emerald-950/10">
                        <span class="text-[10px] text-emerald-300 block uppercase font-semibold">Total Work Hours</span>
                        <span class="text-xl font-bold text-emerald-400 mt-1 block font-mono"><?= number_format($fdGrand['total_work_hrs'], 1) ?>h</span>
                        <span class="text-[10px] text-slate-400"><?= $fdGrand['present_days'] ?> Total Shifts</span>
                    </div>
                    <div class="bg-slate-900/70 border border-amber-500/40 rounded-2xl p-3.5 bg-amber-950/15">
                        <span class="text-[10px] text-amber-300 block uppercase font-semibold">Total Late Minutes</span>
                        <span class="text-xl font-bold text-amber-400 mt-1 block font-mono"><?= number_format($fdGrand['total_late_mins']) ?> min</span>
                        <span class="text-[10px] text-slate-400"><?= $fdGrand['late_count'] ?> Late Instances</span>
                    </div>
                    <div class="bg-slate-900/70 border border-orange-500/40 rounded-2xl p-3.5 bg-orange-950/15">
                        <span class="text-[10px] text-orange-300 block uppercase font-semibold">Total Late (Hourly)</span>
                        <span class="text-xl font-bold text-orange-300 mt-1 block font-mono"><?= number_format($fdGrand['late_hrs_decimal'], 2) ?> hrs</span>
                        <span class="text-[10px] text-slate-400"><?= format_minutes($fdGrand['total_late_mins']) ?> Exact</span>
                    </div>
                    <div class="bg-slate-900/70 border border-indigo-500/30 rounded-2xl p-3.5 bg-indigo-950/10">
                        <span class="text-[10px] text-indigo-300 block uppercase font-semibold">Total OT (Hourly)</span>
                        <span class="text-xl font-bold text-indigo-300 mt-1 block font-mono"><?= number_format($fdGrand['ot_hrs_decimal'], 2) ?> hrs</span>
                        <span class="text-[10px] text-slate-400"><?= number_format($fdGrand['total_ot_mins']) ?> mins</span>
                    </div>
                    <div class="bg-slate-900/70 border border-rose-500/30 rounded-2xl p-3.5 bg-rose-950/10">
                        <span class="text-[10px] text-rose-300 block uppercase font-semibold">Total No-Pay Days</span>
                        <span class="text-xl font-bold text-rose-400 mt-1 block font-mono"><?= $fdGrand['nopay_days'] ?></span>
                        <span class="text-[10px] text-slate-400"><?= $fdGrand['leave_days'] ?> Paid Leaves</span>
                    </div>
                </div>

                <!-- Full Detail Breakdown Table -->
                <div class="bg-slate-900/70 border border-slate-800/80 rounded-2xl overflow-hidden shadow-xl">
                    <div class="p-4 border-b border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs text-slate-400">
                        <div>
                            <strong class="text-white text-sm font-serif">Full Detailed Late Minutes, Late Hours &amp; Hourly Breakdown Report</strong>
                            <span class="ml-2 text-gold-400 font-mono"><?= date('d M Y', strtotime($startDate)) ?> &rarr; <?= date('d M Y', strtotime($endDate)) ?></span>
                        </div>
                        <span class="text-[11px] text-slate-400">Hourly Decimal = Minutes &divide; 60 (for payroll calculation)</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-slate-300">
                            <thead class="bg-slate-950/80 text-[10px] font-semibold uppercase tracking-wider text-slate-400 border-b border-slate-800">
                                <tr>
                                    <th class="py-3 px-3">ID</th>
                                    <th class="py-3 px-3">Employee</th>
                                    <th class="py-3 px-3 text-center">Present</th>
                                    <th class="py-3 px-3 text-center">Leaves / No-Pay</th>
                                    <th class="py-3 px-3 text-center">Work Hours (Avg/Day)</th>
                                    <th class="py-3 px-3 text-center bg-amber-950/20 text-amber-300 border-l border-amber-500/20">Late Days</th>
                                    <th class="py-3 px-3 text-center bg-amber-950/20 text-amber-300">Total Late (Mins)</th>
                                    <th class="py-3 px-3 text-center bg-amber-950/20 text-amber-300">Late (Hrs &amp; Mins)</th>
                                    <th class="py-3 px-3 text-center bg-orange-950/30 text-orange-300 font-bold border-r border-amber-500/20">Late Hourly (Hrs)</th>
                                    <th class="py-3 px-3 text-center">Early Leave (Hrs)</th>
                                    <th class="py-3 px-3 text-center text-indigo-300">OT (Hourly)</th>
                                    <th class="py-3 px-3 text-center">Net Hours</th>
                                    <th class="py-3 px-3 text-center">Statement</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800/60 font-mono text-[11px]">
                                <?php if (empty($fdRows)): ?>
                                    <tr><td colspan="13" class="text-center py-8 text-slate-500 font-sans">No employee records found for this filter.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($fdRows as $r): ?>
                                        <tr class="hover:bg-slate-800/30 transition-colors">
                                            <td class="py-3 px-3 font-bold text-gold-400">#<?= htmlspecialchars($r['machine_user_id']) ?></td>
                                            <td class="py-3 px-3 font-sans">
                                                <div class="font-medium text-white"><?= htmlspecialchars($r['name']) ?></div>
                                                <div class="text-[10px] text-slate-500"><?= htmlspecialchars($r['department']) ?> &bull; <?= htmlspecialchars($r['designation']) ?></div>
                                            </td>
                                            <td class="py-3 px-3 text-center font-bold text-emerald-400"><?= $r['present_days'] ?></td>
                                            <td class="py-3 px-3 text-center">
                                                <span class="text-purple-300"><?= $r['leave_days'] ?>L</span>
                                                <span class="text-slate-600 mx-0.5">/</span>
                                                <span class="<?= $r['nopay_days'] > 0 ? 'text-rose-400 font-bold' : 'text-slate-500' ?>"><?= $r['nopay_days'] ?>NP</span>
                                            </td>
                                            <td class="py-3 px-3 text-center">
                                                <div class="text-white font-semibold"><?= number_format($r['total_work_hrs'], 1) ?>h</div>
                                                <div class="text-[10px] text-slate-500"><?= number_format($r['avg_work_hrs'], 1) ?>h/d</div>
                                            </td>
                                            <td class="py-3 px-3 text-center bg-amber-950/10 border-l border-amber-500/15">
                                                <?= $r['late_count'] > 0 ? '<span class="text-amber-400 font-bold">' . $r['late_count'] . '</span>' : '<span class="text-slate-600">0</span>' ?>
                                            </td>
                                            <td class="py-3 px-3 text-center bg-amber-950/10">
                                                <?php if ($r['total_late_mins'] > 0): ?>
                                                    <span class="text-amber-300 font-bold"><?= number_format($r['total_late_mins']) ?> min</span>
                                                    <div class="text-[9px] text-slate-500">avg <?= $r['avg_late_mins'] ?>m/day</div>
                                                <?php else: ?>
                                                    <span class="text-slate-600">0 min</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-3 px-3 text-center bg-amber-950/10">
                                                <?= $r['total_late_mins'] > 0 ? '<span class="text-amber-400 font-semibold">' . $r['late_hm'] . '</span>' : '<span class="text-slate-600">0h</span>' ?>
                                            </td>
                                            <td class="py-3 px-3 text-center bg-orange-950/20 border-r border-amber-500/15">
                                                <?php if ($r['total_late_mins'] > 0): ?>
                                                    <span class="px-2 py-0.5 rounded font-bold text-orange-300 bg-orange-500/15 border border-orange-500/30">
                                                        <?= number_format($r['late_hrs_decimal'], 2) ?> hrs
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-slate-600">0.00 hrs</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-3 px-3 text-center">
                                                <?php if ($r['total_early_mins'] > 0): ?>
                                                    <span class="text-rose-300"><?= number_format($r['early_hrs_decimal'], 2) ?>h</span>
                                                    <div class="text-[9px] text-slate-500"><?= $r['total_early_mins'] ?>m</div>
                                                <?php else: ?>
                                                    <span class="text-slate-600">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-3 px-3 text-center">
                                                <?php if ($r['total_ot_mins'] > 0): ?>
                                                    <span class="text-indigo-300 font-bold"><?= number_format($r['ot_hrs_decimal'], 2) ?> hrs</span>
                                                    <div class="text-[9px] text-slate-500"><?= $r['total_ot_mins'] ?>m</div>
                                                <?php else: ?>
                                                    <span class="text-slate-600">0.00</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-3 px-3 text-center font-semibold text-cyan-300">
                                                <?= number_format($r['net_hours'], 2) ?>h
                                            </td>
                                            <td class="py-3 px-3 text-center font-sans">
                                                <a href="reports.php?tab=attendance&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&employee_id=<?= $r['id'] ?>&view_mode=employee_statement"
                                                    class="px-2 py-1 rounded-lg text-[10px] font-semibold bg-gold-500/10 hover:bg-gold-500/20 text-gold-400 border border-gold-500/30 inline-flex items-center gap-1">
                                                    <span>Daily</span> <i class="fa-solid fa-arrow-right text-[9px]"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                            <?php if (!empty($fdRows)): ?>
                            <tfoot class="bg-slate-950/90 border-t-2 border-gold-500/30 font-mono text-[11px] font-bold text-white">
                                <tr>
                                    <td colspan="2" class="py-3.5 px-3 font-sans uppercase tracking-wider text-gold-400">Grand Total (<?= $fdGrand['staff_count'] ?> Staff)</td>
                                    <td class="py-3.5 px-3 text-center text-emerald-400"><?= $fdGrand['present_days'] ?></td>
                                    <td class="py-3.5 px-3 text-center"><?= $fdGrand['leave_days'] ?>L / <span class="text-rose-400"><?= $fdGrand['nopay_days'] ?>NP</span></td>
                                    <td class="py-3.5 px-3 text-center"><?= number_format($fdGrand['total_work_hrs'], 1) ?>h</td>
                                    <td class="py-3.5 px-3 text-center text-amber-400 bg-amber-950/20 border-l border-amber-500/20"><?= $fdGrand['late_count'] ?></td>
                                    <td class="py-3.5 px-3 text-center text-amber-300 bg-amber-950/20"><?= number_format($fdGrand['total_late_mins']) ?> min</td>
                                    <td class="py-3.5 px-3 text-center text-amber-400 bg-amber-950/20"><?= format_minutes($fdGrand['total_late_mins']) ?></td>
                                    <td class="py-3.5 px-3 text-center text-orange-300 bg-orange-950/30 border-r border-amber-500/20"><?= number_format($fdGrand['late_hrs_decimal'], 2) ?> hrs</td>
                                    <td class="py-3.5 px-3 text-center text-rose-300"><?= number_format($fdGrand['early_hrs_decimal'], 2) ?>h</td>
                                    <td class="py-3.5 px-3 text-center text-indigo-300"><?= number_format($fdGrand['ot_hrs_decimal'], 2) ?> hrs</td>
                                    <td class="py-3.5 px-3 text-center text-cyan-300"><?= number_format(max(0, $fdGrand['total_work_hrs'] - $fdGrand['late_hrs_decimal']), 2) ?>h</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
            </div>

        <!-- ======================================================== -->
        <!-- VIEW 1: INDIVIDUAL EMPLOYEE STATEMENT (DAY-BY-DAY)       -->
        <!-- ======================================================== -->
        <?php elseif ($selectedEmpId > 0 && $employeeTimesheet && $employeeTimesheet['employee']): ?>
            <?php 
                $emp = $employeeTimesheet['employee'];
                $totals = $employeeTimesheet['totals'];
                $days = $employeeTimesheet['days'];
            ?>
            <div class="space-y-5">
                <!-- Employee Header Profile Card -->
                <div class="bg-gradient-to-r from-slate-900 via-slate-900/90 to-slate-800 border border-slate-800 rounded-2xl p-6 shadow-xl">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="flex items-center gap-4">
                            <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-gold-600 to-amber-300 text-slate-950 font-bold font-serif text-xl flex items-center justify-center shadow-lg shadow-gold-500/20">
                                <?= strtoupper(substr($emp['calling_name'] ?: $emp['name'], 0, 1)) ?>
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <h3 class="text-xl font-serif font-bold text-white"><?= htmlspecialchars($emp['name']) ?></h3>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-gold-500/15 text-gold-400 border border-gold-500/30">
                                        ID #<?= htmlspecialchars($emp['machine_user_id']) ?> (<?= htmlspecialchars($emp['emp_no']) ?>)
                                    </span>
                                </div>
                                <p class="text-xs text-slate-400 mt-1">
                                    <?= htmlspecialchars($emp['designation']) ?> &bull; <span class="text-slate-300"><?= htmlspecialchars($emp['department']) ?></span>
                                    &bull; Shift: <span class="text-slate-200 font-mono"><?= substr($emp['shift_start'], 0, 5) ?> - <?= substr($emp['shift_end'], 0, 5) ?></span>
                                    &bull; Gender: <span class="text-slate-300"><?= htmlspecialchars($emp['gender']) ?></span>
                                </p>
                            </div>
                        </div>

                        <div class="text-right sm:text-right text-xs text-slate-400">
                            <div>Timesheet Statement Period:</div>
                            <div class="text-sm font-bold text-white mt-0.5"><?= date('d M Y', strtotime($startDate)) ?> &rarr; <?= date('d M Y', strtotime($endDate)) ?></div>
                            <div class="text-[11px] text-gold-400 mt-0.5"><?= $totals['calendar_days'] ?> Calendar Days (<?= $totals['working_days'] ?> Standard Working Days)</div>
                        </div>
                    </div>
                </div>

                <!-- 7 KPI Metric Cards -->
                <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3">
                    <!-- Calendar Days -->
                    <div class="bg-slate-900/70 border border-slate-800 rounded-2xl p-3.5">
                        <span class="text-[10px] text-slate-400 block uppercase font-semibold">Period Days</span>
                        <span class="text-xl font-bold text-white mt-1 block font-mono"><?= $totals['calendar_days'] ?></span>
                        <span class="text-[10px] text-slate-500"><?= $totals['working_days'] ?> Work Days</span>
                    </div>

                    <!-- Present Days -->
                    <div class="bg-slate-900/70 border border-emerald-500/30 rounded-2xl p-3.5 bg-emerald-950/10">
                        <span class="text-[10px] text-emerald-300 block uppercase font-semibold">Days Present</span>
                        <span class="text-xl font-bold text-emerald-400 mt-1 block font-mono"><?= $totals['present_days'] ?></span>
                        <span class="text-[10px] text-slate-400"><?= $totals['total_work_hrs'] ?> Work Hrs</span>
                    </div>

                    <!-- Late Time -->
                    <div class="bg-slate-900/70 border border-amber-500/30 rounded-2xl p-3.5 bg-amber-950/10">
                        <span class="text-[10px] text-amber-300 block uppercase font-semibold">Late Time (Hourly)</span>
                        <span class="text-xl font-bold text-amber-400 mt-1 block font-mono"><?= format_minutes($totals['total_late_mins']) ?></span>
                        <span class="text-[10px] text-orange-300/90 font-mono block"><?= $totals['total_late_mins'] ?>m &bull; <?= number_format($totals['total_late_mins'] / 60, 2) ?> hrs (<?= $totals['late_count'] ?>x)</span>
                    </div>

                    <!-- Overtime (OT) -->
                    <div class="bg-slate-900/70 border border-indigo-500/30 rounded-2xl p-3.5 bg-indigo-950/10">
                        <span class="text-[10px] text-indigo-300 block uppercase font-semibold">Overtime (OT)</span>
                        <span class="text-xl font-bold text-indigo-300 mt-1 block font-mono"><?= number_format($totals['total_ot_mins'] / 60, 1) ?>h</span>
                        <span class="text-[10px] text-slate-400"><?= format_minutes($totals['total_ot_mins']) ?> Total</span>
                    </div>

                    <!-- Approved Leaves -->
                    <div class="bg-slate-900/70 border border-purple-500/30 rounded-2xl p-3.5 bg-purple-950/10">
                        <span class="text-[10px] text-purple-300 block uppercase font-semibold">Paid Leaves</span>
                        <span class="text-xl font-bold text-purple-300 mt-1 block font-mono"><?= $totals['leave_days'] ?></span>
                        <span class="text-[10px] text-slate-400">Approved Leaves</span>
                    </div>

                    <!-- ADJUSTED NO-PAY (LOSS OF PAY) -->
                    <div class="bg-slate-900/70 border-2 <?= $totals['nopay_days'] > 0 ? 'border-rose-500 bg-rose-950/30' : 'border-slate-800' ?> rounded-2xl p-3.5">
                        <span class="text-[10px] <?= $totals['nopay_days'] > 0 ? 'text-rose-300 font-bold' : 'text-slate-400' ?> block uppercase">No-Pay Days</span>
                        <span class="text-xl font-bold <?= $totals['nopay_days'] > 0 ? 'text-rose-400 animate-pulse' : 'text-slate-300' ?> mt-1 block font-mono">
                            <?= $totals['nopay_days'] ?>
                        </span>
                        <span class="text-[10px] <?= $totals['nopay_days'] > 0 ? 'text-rose-300 font-semibold' : 'text-slate-500' ?>">
                            <?= $totals['nopay_days'] > 0 ? 'Loss of Pay' : 'Zero Deductions' ?>
                        </span>
                    </div>

                    <!-- Clinic Schedule -->
                    <div class="bg-slate-900/70 border border-slate-800 rounded-2xl p-3.5">
                        <span class="text-[10px] text-slate-400 block uppercase font-semibold">Clinic Policy</span>
                        <span class="text-xl font-bold text-cyan-300 mt-1 block font-mono">7 Days</span>
                        <span class="text-[10px] text-slate-400">Sun &amp; Poya Open</span>
                    </div>
                </div>

                <!-- Day-by-Day Statement Breakdown Table -->
                <div class="bg-slate-900/70 border border-slate-800/80 rounded-2xl overflow-hidden shadow-xl">
                    <div class="p-4 border-b border-slate-800 flex items-center justify-between text-xs text-slate-400">
                        <span>Day-by-Day Attendance & No-Pay Breakdown: <strong class="text-white"><?= htmlspecialchars($emp['name']) ?></strong></span>
                        <div class="flex items-center gap-3">
                            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span> Present</span>
                            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span> Late</span>
                            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-purple-500"></span> Leave</span>
                            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-cyan-400"></span> Holiday</span>
                            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span> <strong>No-Pay</strong></span>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-slate-300">
                            <thead class="bg-slate-950/70 text-[10px] font-semibold uppercase tracking-wider text-slate-400 border-b border-slate-800">
                                <tr>
                                    <th class="py-3 px-3">Date</th>
                                    <th class="py-3 px-3">Day</th>
                                    <th class="py-3 px-3">Status</th>
                                    <th class="py-3 px-3">Check In</th>
                                    <th class="py-3 px-3">Check Out</th>
                                    <th class="py-3 px-3">Work Hours</th>
                                    <th class="py-3 px-3 text-center">Late Mins</th>
                                    <th class="py-3 px-3 text-center">OT Mins</th>
                                    <th class="py-3 px-3">Remarks & Holiday Description</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800/60 font-mono text-[11px]">
                                <?php foreach ($days as $dRow): ?>
                                    <tr class="hover:bg-slate-800/30 transition-colors <?= $dRow['is_nopay'] ? 'bg-rose-950/20' : ($dRow['status_code'] === 'holiday' ? 'bg-amber-950/10' : '') ?>">
                                        <td class="py-2.5 px-3 font-bold text-white"><?= $dRow['date'] ?></td>
                                        <td class="py-2.5 px-3 font-sans <?= $dRow['day'] === 'Sunday' ? 'text-amber-400 font-semibold' : 'text-slate-400' ?>"><?= $dRow['day'] ?></td>
                                        <td class="py-2.5 px-3 font-sans">
                                            <span class="px-2.5 py-0.5 rounded-md text-[10px] font-semibold border <?= $dRow['badge_color'] ?>">
                                                <?php if ($dRow['is_nopay']): ?>
                                                    <i class="fa-solid fa-triangle-exclamation mr-1"></i>
                                                <?php elseif ($dRow['status_code'] === 'holiday'): ?>
                                                    <i class="fa-solid fa-moon mr-1"></i>
                                                <?php elseif ($dRow['status_code'] === 'leave'): ?>
                                                    <i class="fa-solid fa-calendar-day mr-1"></i>
                                                <?php endif; ?>
                                                <?= $dRow['status'] ?>
                                            </span>
                                        </td>
                                        <td class="py-2.5 px-3 text-emerald-400 font-bold">
                                            <?= $dRow['check_in'] ? format_time_12h($dRow['check_in']) : '-' ?>
                                        </td>
                                        <td class="py-2.5 px-3 text-slate-300">
                                            <?= $dRow['check_out'] ? format_time_12h($dRow['check_out']) : '-' ?>
                                        </td>
                                        <td class="py-2.5 px-3"><?= $dRow['work_hours'] > 0 ? $dRow['work_hours'] . 'h' : '-' ?></td>
                                        <td class="py-2.5 px-3 text-center">
                                            <?= $dRow['late_minutes'] > 0 ? '<span class="text-amber-400 font-bold">+' . $dRow['late_minutes'] . 'm</span>' : '-' ?>
                                        </td>
                                        <td class="py-2.5 px-3 text-center">
                                            <?= $dRow['overtime_minutes'] > 0 ? '<span class="text-indigo-300 font-bold">+' . $dRow['overtime_minutes'] . 'm</span>' : '-' ?>
                                        </td>
                                        <td class="py-2.5 px-3 font-sans text-xs text-slate-400">
                                            <?php if ($dRow['is_nopay']): ?>
                                                <div class="flex flex-wrap items-center justify-between gap-2">
                                                    <span class="text-rose-400 font-semibold">⚠️ <?= htmlspecialchars($dRow['notes'] ?: 'Unexcused Absence • Adjusted as No-Pay') ?></span>
                                                    <a href="leaves.php?apply_emp=<?= $emp['id'] ?>&apply_date=<?= urlencode($dRow['date']) ?>" class="print:hidden inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-semibold bg-gold-500/15 hover:bg-gold-500/25 text-gold-300 border border-gold-500/30 transition-colors">
                                                        <i class="fa-solid fa-calendar-plus text-[9px]"></i> Apply Leave
                                                    </a>
                                                </div>
                                            <?php else: ?>
                                                <?= htmlspecialchars($dRow['notes']) ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Printable Signature Blocks (visible on print) -->
                    <div class="hidden print:block p-8 border-t border-slate-800 text-slate-900 mt-8 font-sans">
                        <div class="grid grid-cols-3 gap-8 pt-10 text-center text-xs">
                            <div class="border-t border-slate-400 pt-2">
                                <p class="font-bold">Employee Signature</p>
                                <p class="text-[10px] text-slate-500 mt-1"><?= htmlspecialchars($emp['name']) ?></p>
                            </div>
                            <div class="border-t border-slate-400 pt-2">
                                <p class="font-bold">Supervisor / Approver</p>
                                <p class="text-[10px] text-slate-500 mt-1">Verification</p>
                            </div>
                            <div class="border-t border-slate-400 pt-2">
                                <p class="font-bold">Managing Director / Admin</p>
                                <p class="text-[10px] text-slate-500 mt-1">Sapphire Aesthetic & Luxury</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <!-- ======================================================== -->
        <!-- VIEW 2: ALL EMPLOYEES SUMMARY (WITH ADJUSTED NO-PAY)    -->
        <!-- ======================================================== -->
        <?php elseif ($viewMode === 'summary'): ?>
            <div class="bg-slate-900/70 border border-slate-800/80 rounded-2xl overflow-hidden shadow-xl">
                <div class="p-4 border-b border-slate-800 flex items-center justify-between text-xs text-slate-400">
                    <span>Date Range: <strong class="text-white"><?= date('d M Y', strtotime($startDate)) ?> &rarr; <?= date('d M Y', strtotime($endDate)) ?></strong> &bull; Working Days: <strong class="text-gold-400"><?= $workingDaysInPeriod ?> Days</strong></span>
                    <span>Staff Count: <strong class="text-gold-400"><?= count($attendanceSummary) ?> entries</strong></span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-300">
                        <thead class="bg-slate-950/70 text-[10px] font-semibold uppercase tracking-wider text-slate-400 border-b border-slate-800">
                            <tr>
                                <th class="py-3 px-3">ID</th>
                                <th class="py-3 px-3">Employee</th>
                                <th class="py-3 px-3">Department</th>
                                <th class="py-3 px-3 text-center">Present</th>
                                <th class="py-3 px-3 text-center">Late Times</th>
                                <th class="py-3 px-3 text-center">Total Late</th>
                                <th class="py-3 px-3 text-center">Overtime</th>
                                <th class="py-3 px-3 text-center">Leaves</th>
                                <th class="py-3 px-3 text-center bg-rose-950/20 text-rose-300 font-bold border-x border-rose-500/20">
                                    Adjusted No-Pay
                                </th>
                                <th class="py-3 px-3 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60 font-mono text-[11px]">
                            <?php if (empty($attendanceSummary)): ?>
                                <tr><td colspan="10" class="text-center py-8 text-slate-500 font-sans">No attendance records found for this date range.</td></tr>
                            <?php else: ?>
                                <?php foreach ($attendanceSummary as $row): ?>
                                    <?php 
                                        $pDays = (float)($row['present_days'] ?: 0);
                                        $lDays = (float)($row['leave_days'] ?: 0);
                                        $adjNoPay = max(0, $workingDaysInPeriod - ($pDays + $lDays));
                                    ?>
                                    <tr class="hover:bg-slate-800/30 transition-colors">
                                        <td class="py-3 px-3 font-bold text-gold-400">#<?= htmlspecialchars($row['machine_user_id']) ?></td>
                                        <td class="py-3 px-3 font-sans">
                                            <div class="font-medium text-white"><?= htmlspecialchars($row['name']) ?></div>
                                            <div class="text-[10px] text-slate-500 font-mono"><?= htmlspecialchars($row['emp_no']) ?></div>
                                        </td>
                                        <td class="py-3 px-3 font-sans text-slate-400"><?= htmlspecialchars($row['department']) ?></td>
                                        <td class="py-3 px-3 text-center font-bold text-emerald-400"><?= $row['present_days'] ?: 0 ?></td>
                                        <td class="py-3 px-3 text-center">
                                            <?= $row['late_count'] > 0 ? '<span class="text-amber-400 font-bold">' . $row['late_count'] . '</span>' : '-' ?>
                                        </td>
                                        <td class="py-3 px-3 text-center">
                                            <?php if ($row['total_late_mins'] > 0): ?>
                                                <span class="text-amber-400 font-bold">+<?= format_minutes($row['total_late_mins']) ?></span>
                                                <div class="text-[9px] text-orange-300/90"><?= $row['total_late_mins'] ?>m (<?= number_format($row['total_late_mins'] / 60, 2) ?> hrs)</div>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3 px-3 text-center">
                                            <?php if ($row['total_ot_mins'] > 0): ?>
                                                <span class="text-indigo-300 font-bold">
                                                    <?= number_format($row['total_ot_mins'] / 60, 1) ?>h
                                                </span>
                                            <?php else: ?>
                                                <span class="text-slate-600">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3 px-3 text-center text-purple-300 font-bold">
                                            <?= $row['leave_days'] > 0 ? $row['leave_days'] : '-' ?>
                                        </td>
                                        <!-- ADJUSTED NO-PAY DAYS -->
                                        <td class="py-3 px-3 text-center bg-rose-950/20 border-x border-rose-500/20">
                                            <?php if ($adjNoPay > 0): ?>
                                                <span class="px-2 py-0.5 rounded font-bold text-rose-300 bg-rose-500/20 border border-rose-500/40">
                                                    <?= $adjNoPay ?> Days
                                                </span>
                                            <?php else: ?>
                                                <span class="text-slate-500 font-sans">0</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3 px-3 text-center font-sans">
                                            <a href="reports.php?tab=attendance&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&employee_id=<?= $row['id'] ?>&view_mode=employee_statement"
                                                class="px-2.5 py-1 rounded-lg text-[10px] font-semibold bg-gold-500/10 hover:bg-gold-500/20 text-gold-400 border border-gold-500/30 transition-all inline-flex items-center gap-1">
                                                <span>Timesheet</span>
                                                <i class="fa-solid fa-arrow-right text-[9px]"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <!-- ======================================================== -->
        <!-- VIEW 3: DETAILED DAILY PUNCH LOG                         -->
        <!-- ======================================================== -->
        <?php else: ?>
            <div class="bg-slate-900/70 border border-slate-800/80 rounded-2xl overflow-hidden shadow-xl">
                <div class="p-4 border-b border-slate-800 flex items-center justify-between text-xs text-slate-400">
                    <span>Detailed Punch Records: <strong class="text-white"><?= $startDate ?> &rarr; <?= $endDate ?></strong></span>
                    <span>Total Punches: <strong class="text-gold-400"><?= count($attendanceDetailed) ?> records</strong></span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-300">
                        <thead class="bg-slate-950/70 text-[10px] font-semibold uppercase tracking-wider text-slate-400 border-b border-slate-800">
                            <tr>
                                <th class="py-3 px-3">Date</th>
                                <th class="py-3 px-3">Day</th>
                                <th class="py-3 px-3">ID</th>
                                <th class="py-3 px-3">Employee</th>
                                <th class="py-3 px-3">Check In</th>
                                <th class="py-3 px-3">Check Out</th>
                                <th class="py-3 px-3">Work Hours</th>
                                <th class="py-3 px-3">Late Mins</th>
                                <th class="py-3 px-3">OT Mins</th>
                                <th class="py-3 px-3">Status</th>
                                <th class="py-3 px-3">Source</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60 font-mono text-[11px]">
                            <?php if (empty($attendanceDetailed)): ?>
                                <tr><td colspan="11" class="text-center py-8 text-slate-500 font-sans">No daily punches recorded for this period.</td></tr>
                            <?php else: ?>
                                <?php foreach ($attendanceDetailed as $pRow): ?>
                                    <tr class="hover:bg-slate-800/30 transition-colors">
                                        <td class="py-2.5 px-3 text-white"><?= $pRow['date'] ?></td>
                                        <td class="py-2.5 px-3 font-sans text-slate-400"><?= date('D', strtotime($pRow['date'])) ?></td>
                                        <td class="py-2.5 px-3 text-gold-400 font-bold">#<?= $pRow['machine_user_id'] ?></td>
                                        <td class="py-2.5 px-3 font-sans font-medium text-white"><?= htmlspecialchars($pRow['emp_name']) ?></td>
                                        <td class="py-2.5 px-3 text-emerald-400 font-bold"><?= format_time_12h($pRow['check_in']) ?></td>
                                        <td class="py-2.5 px-3 text-slate-300"><?= format_time_12h($pRow['check_out']) ?></td>
                                        <td class="py-2.5 px-3"><?= $pRow['work_hours'] > 0 ? $pRow['work_hours'] . 'h' : '-' ?></td>
                                        <td class="py-2.5 px-3 text-amber-400"><?= $pRow['late_minutes'] > 0 ? '+' . $pRow['late_minutes'] . 'm' : '-' ?></td>
                                        <td class="py-2.5 px-3 text-indigo-300"><?= $pRow['overtime_minutes'] > 0 ? '+' . $pRow['overtime_minutes'] . 'm' : '-' ?></td>
                                        <td class="py-2.5 px-3 font-sans">
                                            <span class="px-2 py-0.5 rounded text-[10px] <?= $pRow['status'] === 'Late' ? 'bg-amber-500/15 text-amber-300' : ($pRow['status'] === 'Present' ? 'bg-emerald-500/15 text-emerald-300' : 'bg-slate-800 text-slate-400') ?>">
                                                <?= $pRow['status'] ?>
                                            </span>
                                        </td>
                                        <td class="py-2.5 px-3 font-sans text-[10px] text-slate-400"><?= $pRow['source'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

    <!-- ============================================== -->
    <!-- TAB 2: ACTIVE / INACTIVE STAFF DETAILS REPORT -->
    <!-- ============================================== -->
    <?php elseif ($tab === 'staff'): ?>
        <!-- KPI Row for Staff Summary -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="bg-slate-900/70 border border-slate-800 rounded-2xl p-4">
                <span class="text-xs text-slate-400 block uppercase">Total Staff</span>
                <span class="text-2xl font-bold text-white mt-1 block"><?= $activeCount + $inactiveCount ?></span>
            </div>
            <div class="bg-slate-900/70 border border-slate-800 rounded-2xl p-4">
                <span class="text-xs text-slate-400 block uppercase">Active Staff</span>
                <span class="text-2xl font-bold text-emerald-400 mt-1 block"><?= $activeCount ?></span>
            </div>
            <div class="bg-slate-900/70 border border-slate-800 rounded-2xl p-4">
                <span class="text-xs text-slate-400 block uppercase">Inactive Staff</span>
                <span class="text-2xl font-bold text-slate-400 mt-1 block"><?= $inactiveCount ?></span>
            </div>
            <div class="bg-slate-900/70 border border-purple-500/30 rounded-2xl p-4 bg-purple-950/20">
                <span class="text-xs text-purple-300 block uppercase">Maternity Leave Active</span>
                <span class="text-2xl font-bold text-purple-300 mt-1 block"><?= $maternityActiveCount ?> Ladies</span>
            </div>
        </div>

        <!-- Filter Controls -->
        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-4 sm:p-5 flex flex-wrap items-center justify-between gap-4">
            <form method="GET" action="reports.php" class="flex flex-wrap items-center gap-3">
                <input type="hidden" name="tab" value="staff">

                <div>
                    <label class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Status Filter</label>
                    <select name="staff_status" onchange="this.form.submit()" class="px-3 py-1.5 bg-slate-950 border border-slate-700 rounded-xl text-xs text-slate-100 focus:border-gold-500 focus:outline-none">
                        <option value="all" <?= $staffStatusFilter === 'all' ? 'selected' : '' ?>>All Staff Members</option>
                        <option value="active" <?= $staffStatusFilter === 'active' ? 'selected' : '' ?>>Active Only (<?= $activeCount ?>)</option>
                        <option value="inactive" <?= $staffStatusFilter === 'inactive' ? 'selected' : '' ?>>Inactive Only (<?= $inactiveCount ?>)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Department</label>
                    <select name="department" onchange="this.form.submit()" class="px-3 py-1.5 bg-slate-950 border border-slate-700 rounded-xl text-xs text-slate-100 focus:border-gold-500 focus:outline-none">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= htmlspecialchars($dept) ?>" <?= $selectedDept === $dept ? 'selected' : '' ?>><?= htmlspecialchars($dept) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>

            <div class="flex items-center gap-2">
                <a href="reports.php?tab=staff&staff_status=<?= urlencode($staffStatusFilter) ?>&department=<?= urlencode($selectedDept) ?>&export=staff_csv"
                    class="px-4 py-2 bg-gold-400 hover:bg-gold-500 text-slate-950 rounded-xl text-xs font-semibold shadow-lg shadow-gold-500/20 transition-all flex items-center gap-1.5">
                    <i class="fa-solid fa-file-csv"></i> Export Staff Directory (CSV)
                </a>
                <button type="button" onclick="window.print()" class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs transition-colors">
                    <i class="fa-solid fa-print"></i>
                </button>
            </div>
        </div>

        <!-- Staff Details Table -->
        <div class="bg-slate-900/70 border border-slate-800/80 rounded-2xl overflow-hidden shadow-xl">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-300">
                    <thead class="bg-slate-950/70 text-[10px] font-semibold uppercase tracking-wider text-slate-400 border-b border-slate-800">
                        <tr>
                            <th class="py-3 px-3">Machine ID</th>
                            <th class="py-3 px-3">Emp Code</th>
                            <th class="py-3 px-3">Employee Name</th>
                            <th class="py-3 px-3">Gender</th>
                            <th class="py-3 px-3">Department</th>
                            <th class="py-3 px-3">Designation</th>
                            <th class="py-3 px-3">Shift</th>
                            <th class="py-3 px-3">Status</th>
                            <th class="py-3 px-3">Maternity Permission</th>
                            <th class="py-3 px-3">Leave Approver</th>
                            <th class="py-3 px-3">Join Date</th>
                            <th class="py-3 px-3">Appointment Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60 font-mono text-[11px]">
                        <?php if (empty($staffList)): ?>
                            <tr><td colspan="12" class="text-center py-8 text-slate-500 font-sans">No staff records match the selected filter.</td></tr>
                        <?php else: ?>
                            <?php foreach ($staffList as $st): ?>
                                <tr class="hover:bg-slate-800/30 transition-colors <?= $st['status'] === 'inactive' ? 'opacity-60 bg-slate-950/30' : '' ?>">
                                    <td class="py-3 px-3 font-bold text-gold-400">#<?= htmlspecialchars($st['machine_user_id']) ?></td>
                                    <td class="py-3 px-3 text-slate-400"><?= htmlspecialchars($st['emp_no']) ?></td>
                                    <td class="py-3 px-3 font-sans font-medium text-white">
                                        <?= htmlspecialchars($st['name']) ?>
                                        <?php if (!empty($st['calling_name']) && $st['calling_name'] !== $st['name']): ?>
                                            <span class="text-[10px] text-slate-500 font-mono">(<?= htmlspecialchars($st['calling_name']) ?>)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-3 font-sans">
                                        <span class="<?= $st['gender'] === 'Female' ? 'text-pink-400' : 'text-blue-400' ?>">
                                            <i class="fa-solid <?= $st['gender'] === 'Female' ? 'fa-venus' : 'fa-mars' ?>"></i>
                                            <?= $st['gender'] ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 font-sans text-slate-300"><?= htmlspecialchars($st['department']) ?></td>
                                    <td class="py-3 px-3 font-sans text-slate-400"><?= htmlspecialchars($st['designation']) ?></td>
                                    <td class="py-3 px-3 text-slate-400"><?= substr($st['shift_start'], 0, 5) ?> - <?= substr($st['shift_end'], 0, 5) ?></td>
                                    <td class="py-3 px-3 font-sans">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold <?= $st['status'] === 'active' ? 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30' : 'bg-slate-800 text-slate-400 border border-slate-700' ?>">
                                            <?= ucfirst($st['status']) ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 font-sans">
                                        <?php if ($st['gender'] === 'Female'): ?>
                                            <?php if ($st['maternity_eligible']): ?>
                                                <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-purple-500/20 text-purple-300 border border-purple-500/40">
                                                    <i class="fa-solid fa-baby"></i> Active (84 Days)
                                                </span>
                                            <?php else: ?>
                                                <span class="text-slate-500 text-[10px]">Normal Leaves</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-slate-600 text-[10px]">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-3 font-sans text-slate-400">
                                        <?= ($st['can_approve_leave'] || $st['role'] === 'admin') ? '<span class="text-emerald-400"><i class="fa-solid fa-check"></i> Yes</span>' : 'No' ?>
                                    </td>
                                    <td class="py-3 px-3 text-slate-400"><?= $st['join_date'] ?></td>
                                    <td class="py-3 px-3 text-gold-400"><?= !empty($st['appointment_date']) ? htmlspecialchars($st['appointment_date']) : '-' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
