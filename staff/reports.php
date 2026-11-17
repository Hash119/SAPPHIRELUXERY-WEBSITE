<?php
/**
 * Sapphire Aesthetic & Luxury - Comprehensive Reports Module
 * 1. Date Range In/Out & Leave Timesheet Report
 * 2. Active / Inactive Staff Details Report
 */
require_once __DIR__ . '/config/auth.php';
require_login();

$user = current_user();
if (!is_admin_or_manager($user)) {
    header('Location: employee_portal.php');
    exit;
}

$pageTitle = 'Reports & Analytics';

// Active Tab (attendance vs staff)
$tab = $_GET['tab'] ?? 'attendance';

// ==========================================
// 1. ATTENDANCE & LEAVE REPORT (DATE RANGE)
// ==========================================
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate   = $_GET['end_date'] ?? date('Y-m-d');
$selectedDept = $_GET['department'] ?? '';
$viewMode = $_GET['view_mode'] ?? 'summary'; // summary or detailed

// Handle Attendance CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'attendance_csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Sapphire_Attendance_' . $startDate . '_to_' . $endDate . '.csv');
    $output = fopen('php://output', 'w');

    if ($viewMode === 'detailed') {
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
    } else {
        fputcsv($output, ['Machine ID', 'Employee Code', 'Employee Name', 'Department', 'Gender', 'Present Days', 'Late Count', 'Total Late Mins', 'Early Leave Mins', 'OT Minutes', 'OT Hours', 'Absent Days', 'Leave Days']);
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
            fputcsv($output, [
                $r['machine_user_id'], $r['emp_no'], $r['name'], $r['department'], $r['gender'],
                $r['present_days'] ?: 0, $r['late_count'] ?: 0, $r['total_late_mins'] ?: 0,
                $r['total_early_mins'] ?: 0, $r['total_ot_mins'] ?: 0, round(($r['total_ot_mins'] ?: 0) / 60, 2),
                $r['absent_days'] ?: 0, $r['leave_days'] ?: 0
            ]);
        }
    }
    fclose($output);
    exit;
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
    fputcsv($output, ['Machine ID', 'Emp Code', 'Full Name', 'Calling Name', 'Department', 'Designation', 'Gender', 'Contact Phone', 'Shift Start', 'Shift End', 'Status', 'Maternity Eligible', 'Can Approve Leaves', 'Join Date']);
    
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
            $r['machine_user_id'], $r['emp_no'], $r['name'], $r['calling_name'], $r['department'],
            $r['designation'], $r['gender'], $r['phone'], $r['shift_start'], $r['shift_end'],
            ucfirst($r['status']), $r['maternity_eligible'] ? 'Active (84 Days)' : 'No',
            $r['can_approve_leave'] ? 'Yes' : 'No', $r['join_date']
        ]);
    }
    fclose($output);
    exit;
}

// Fetch Departments
$departments = [];
$attendanceSummary = [];
$attendanceDetailed = [];
$staffList = [];
$activeCount = 0;
$inactiveCount = 0;
$maternityActiveCount = 0;

if (isset($pdo)) {
    try {
        $departments = $pdo->query("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL AND department != '' ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);

        if ($tab === 'attendance') {
            if ($viewMode === 'detailed') {
                $sql = "
                    SELECT a.*, e.machine_user_id, e.name as emp_name, e.calling_name, e.department, e.gender
                    FROM attendance_logs a
                    JOIN employees e ON a.employee_id = e.id
                    WHERE a.date BETWEEN :s_date AND :e_date
                ";
                $p = [':s_date' => $startDate, ':e_date' => $endDate];
                if (!empty($selectedDept)) { $sql .= " AND e.department = :dept"; $p[':dept'] = $selectedDept; }
                $sql .= " ORDER BY a.date DESC, e.machine_user_id ASC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($p);
                $attendanceDetailed = $stmt->fetchAll();
            } else {
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

            // Staff KPI counts
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
            <h2 class="text-xl sm:text-2xl font-serif font-bold text-white">Reports & Analytics</h2>
            <p class="text-xs text-slate-400 mt-1">Export date-range attendance, late/OT timesheets, and active/inactive staff directory</p>
        </div>

        <!-- Navigation Tabs -->
        <div class="inline-flex p-1 bg-slate-900 border border-slate-800 rounded-2xl">
            <a href="reports.php?tab=attendance" class="px-4 py-2 rounded-xl text-xs font-semibold transition-all flex items-center gap-2 <?= $tab === 'attendance' ? 'bg-gold-500/15 text-gold-400 border border-gold-500/30' : 'text-slate-400 hover:text-white' ?>">
                <i class="fa-solid fa-calendar-range"></i>
                <span>Date Range In/Out Report</span>
            </a>
            <a href="reports.php?tab=staff" class="px-4 py-2 rounded-xl text-xs font-semibold transition-all flex items-center gap-2 <?= $tab === 'staff' ? 'bg-gold-500/15 text-gold-400 border border-gold-500/30' : 'text-slate-400 hover:text-white' ?>">
                <i class="fa-solid fa-address-book"></i>
                <span>Active / Inactive Staff Report</span>
            </a>
        </div>
    </div>

    <!-- ============================================== -->
    <!-- TAB 1: DATE RANGE ATTENDANCE & LEAVE REPORT   -->
    <!-- ============================================== -->
    <?php if ($tab === 'attendance'): ?>
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

                <!-- View Mode: Summary vs Detailed -->
                <div>
                    <label class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Report View</label>
                    <select name="view_mode" class="px-3 py-1.5 bg-slate-950 border border-slate-700 rounded-xl text-xs text-slate-100 focus:border-gold-500 focus:outline-none">
                        <option value="summary" <?= $viewMode === 'summary' ? 'selected' : '' ?>>Timesheet Summary</option>
                        <option value="detailed" <?= $viewMode === 'detailed' ? 'selected' : '' ?>>Daily In / Out Punch Log</option>
                    </select>
                </div>

                <div class="pt-4 flex items-center gap-2">
                    <button type="submit" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-xs font-semibold transition-colors flex items-center gap-1.5">
                        <i class="fa-solid fa-filter"></i> Apply Filter
                    </button>
                    <a href="reports.php?tab=attendance&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&department=<?= urlencode($selectedDept) ?>&view_mode=<?= urlencode($viewMode) ?>&export=attendance_csv"
                        class="px-4 py-2 bg-gold-400 hover:bg-gold-500 text-slate-950 rounded-xl text-xs font-semibold shadow-lg shadow-gold-500/20 transition-all flex items-center gap-1.5">
                        <i class="fa-solid fa-file-csv"></i> Export to Excel (CSV)
                    </a>
                    <button type="button" onclick="window.print()" class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs transition-colors">
                        <i class="fa-solid fa-print"></i>
                    </button>
                </div>
            </form>
        </div>

        <!-- REPORT VIEW 1: SUMMARY TIMESHEET -->
        <?php if ($viewMode === 'summary'): ?>
            <div class="bg-slate-900/70 border border-slate-800/80 rounded-2xl overflow-hidden shadow-xl">
                <div class="p-4 border-b border-slate-800 flex items-center justify-between text-xs text-slate-400">
                    <span>Date Range: <strong class="text-white"><?= date('d M Y', strtotime($startDate)) ?> &rarr; <?= date('d M Y', strtotime($endDate)) ?></strong></span>
                    <span>Staff Count: <strong class="text-gold-400"><?= count($attendanceSummary) ?> entries</strong></span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-300">
                        <thead class="bg-slate-950/70 text-[10px] font-semibold uppercase tracking-wider text-slate-400 border-b border-slate-800">
                            <tr>
                                <th class="py-3 px-3">ID</th>
                                <th class="py-3 px-3">Employee</th>
                                <th class="py-3 px-3">Department</th>
                                <th class="py-3 px-3 text-center">Present Days</th>
                                <th class="py-3 px-3 text-center">Late Times</th>
                                <th class="py-3 px-3 text-center">Total Late</th>
                                <th class="py-3 px-3 text-center">Early Leave</th>
                                <th class="py-3 px-3 text-center">Overtime (OT)</th>
                                <th class="py-3 px-3 text-center">Absent</th>
                                <th class="py-3 px-3 text-center">Leaves</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60 font-mono text-[11px]">
                            <?php if (empty($attendanceSummary)): ?>
                                <tr><td colspan="10" class="text-center py-8 text-slate-500 font-sans">No attendance records found for this date range.</td></tr>
                            <?php else: ?>
                                <?php foreach ($attendanceSummary as $row): ?>
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
                                            <?= $row['total_late_mins'] > 0 ? '<span class="text-amber-400 font-bold">+' . format_minutes($row['total_late_mins']) . '</span>' : '-' ?>
                                        </td>
                                        <td class="py-3 px-3 text-center text-slate-400">
                                            <?= $row['total_early_mins'] > 0 ? format_minutes($row['total_early_mins']) : '-' ?>
                                        </td>
                                        <td class="py-3 px-3 text-center">
                                            <?php if ($row['total_ot_mins'] > 0): ?>
                                                <span class="text-indigo-300 font-bold">
                                                    <?= number_format($row['total_ot_mins'] / 60, 1) ?>h (<?= $row['total_ot_mins'] ?>m)
                                                </span>
                                            <?php else: ?>
                                                <span class="text-slate-600">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3 px-3 text-center text-rose-400 font-bold">
                                            <?= $row['absent_days'] > 0 ? $row['absent_days'] : '-' ?>
                                        </td>
                                        <td class="py-3 px-3 text-center text-purple-300 font-bold">
                                            <?= $row['leave_days'] > 0 ? $row['leave_days'] : '-' ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <!-- REPORT VIEW 2: DETAILED DAILY IN/OUT LOG -->
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
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60 font-mono text-[11px]">
                        <?php if (empty($staffList)): ?>
                            <tr><td colspan="11" class="text-center py-8 text-slate-500 font-sans">No staff records match the selected filter.</td></tr>
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
