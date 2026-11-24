<?php
/**
 * Sapphire Aesthetic & Luxury - Employee Self-Service Portal
 * View personal attendance, late minutes, OT hours, leave balances & apply for leave
 */
require_once __DIR__ . '/config/auth.php';
require_login();

$user = current_user();
$empId = $user['id'];
$pageTitle = 'My Staff Portal';
$message = '';
$error = '';

// Re-fetch fresh employee data
if (isset($pdo)) {
    $empStmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
    $empStmt->execute([$empId]);
    $emp = $empStmt->fetch() ?: $user;
} else {
    $emp = $user;
}

$isFemale = ($emp['gender'] === 'Female');
$isMaternityEligible = ($emp['maternity_eligible'] == 1 && $isFemale);

// Selected Date Range for Attendance (Default: 1st of current month to today)
$defaultFrom = date('Y-m-01');
$defaultTo   = date('Y-m-d');

if (!empty($_GET['from_date']) || !empty($_GET['to_date'])) {
    $startDate = !empty($_GET['from_date']) ? $_GET['from_date'] : $defaultFrom;
    $endDate   = !empty($_GET['to_date']) ? $_GET['to_date'] : $defaultTo;
} elseif (!empty($_GET['month'])) {
    // Backward compatibility if ?month=YYYY-MM is passed
    $mTs = strtotime($_GET['month'] . '-01');
    $startDate = date('Y-m-01', $mTs);
    $endDate   = (date('Y-m', $mTs) === date('Y-m')) ? $defaultTo : date('Y-m-t', $mTs);
} else {
    $startDate = $defaultFrom;
    $endDate   = $defaultTo;
}

// Ensure startDate <= endDate
if (strtotime($startDate) > strtotime($endDate)) {
    $tmpDate = $startDate;
    $startDate = $endDate;
    $endDate = $tmpDate;
}

$selectedMonth = date('Y-m', strtotime($endDate));
$year = (int)date('Y', strtotime($endDate));
$monthNum = (int)date('m', strtotime($endDate));

// Handle Leave Application
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($pdo)) {
    $action = $_POST['action'] ?? '';

    if ($action === 'apply_leave') {
        $type = $_POST['leave_type'] ?? 'Casual';
        $duration = $_POST['duration'] ?? 'full';
        $start = $_POST['start_date'] ?? date('Y-m-d');
        $end = $_POST['end_date'] ?? $start;
        $reason = trim($_POST['reason'] ?? '');

        $applyYear = (int)date('Y', strtotime($start));
        $applyMonth = (int)date('m', strtotime($start));

        // Calculate dynamic leave entitlement for the requested leave date using fresh $emp data
        $applyAccrual = calculate_employee_annual_leave_accrual($emp, $applyYear, $applyMonth);
        sync_employee_leave_balances($pdo, $empId, $applyYear, $applyMonth);

        $balCheckStmt = $pdo->prepare("SELECT * FROM leave_balances WHERE employee_id = ? AND year = ?");
        $balCheckStmt->execute([$empId, $applyYear]);
        $applyBal = $balCheckStmt->fetch() ?: [
            'annual_total' => $applyAccrual['annual_total'], 'annual_used' => 0.0,
            'casual_total' => $applyAccrual['casual_total'], 'casual_used' => 0.0
        ];

        $sT = strtotime($start);
        $eT = strtotime($end);
        if ($duration === 'half' || $type === 'Half Day' || $type === 'Short Leave') {
            $days = 0.5;
            $end = $start;
        } else {
            $days = max(1, round(($eT - $sT) / 86400) + 1);
        }

        $annualRemCheck = max(0, (float)$applyBal['annual_total'] - (float)$applyBal['annual_used']);
        $casualRemCheck = max(0, (float)$applyBal['casual_total'] - (float)$applyBal['casual_used']);

        if ($type === 'Maternity' && !$isMaternityEligible) {
            $error = "Maternity leave is only available for eligible female staff.";
        } elseif ($type === 'Casual' && $applyAccrual['casual_total'] <= 0) {
            $error = "Casual leaves are not available yet. They unlock after your 1-year appointment date (" . ($applyAccrual['unlock_date'] ?? 'confirmation') . ").";
        } elseif ($type === 'Casual' && $days > $casualRemCheck) {
            $error = "Insufficient Casual Leave balance. Requested: {$days} day(s), Available: {$casualRemCheck} of {$applyBal['casual_total']} day(s).";
        } elseif ($type === 'Annual' && $applyAccrual['annual_total'] <= 0) {
            $error = "Annual leaves are not yet available. " . $applyAccrual['description'];
        } elseif ($type === 'Annual' && $days > $annualRemCheck) {
            $hint = ($annualRemCheck == 0.5) ? " Please select 'Half Day (0.5 Day)' duration or wait for next month's +0.5 accrual." : "";
            $error = "Insufficient Annual Leave balance. Requested: {$days} day(s), Available: {$annualRemCheck} day(s) (Entitlement: {$applyBal['annual_total']}, Used: {$applyBal['annual_used']}).{$hint}";
        } else {
            try {
                $ins = $pdo->prepare("
                    INSERT INTO leaves (employee_id, leave_type, start_date, end_date, total_days, reason, status)
                    VALUES (?, ?, ?, ?, ?, ?, 'Pending')
                ");
                $ins->execute([$empId, $type, $start, $end, $days, $reason]);
                $message = "Leave application ({$type} - {$days} day" . ($days == 1 ? '' : 's') . ") submitted successfully! Management will review and authorize it.";
            } catch (Exception $e) {
                $error = "Error: " . $e->getMessage();
            }
        }
    } elseif ($action === 'request_punch') {
        $date = $_POST['date'] ?? date('Y-m-d');
        $timeIn = !empty($_POST['requested_in']) ? $_POST['requested_in'] . ':00' : null;
        $timeOut = !empty($_POST['requested_out']) ? $_POST['requested_out'] . ':00' : null;
        $reason = trim($_POST['reason'] ?? '');

        try {
            $ins = $pdo->prepare("
                INSERT INTO manual_punches (employee_id, date, requested_in, requested_out, reason, status, requested_by)
                VALUES (?, ?, ?, ?, ?, 'Pending', ?)
            ");
            $ins->execute([$empId, $date, $timeIn, $timeOut, $reason, $empId]);
            $message = "Manual punch request sent for management authorization.";
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Fetch Personal Leave Balances
$balance = [
    'annual_total' => 14.0, 'annual_used' => 0.0,
    'casual_total' => 7.0, 'casual_used' => 0.0,
    'medical_total' => 7.0, 'medical_used' => 0.0,
    'maternity_total' => $isMaternityEligible ? 84.0 : 0.0, 'maternity_used' => 0.0
];

$myAttendance = [];
$totalLateMins = 0;
$lateDaysCount = 0;
$totalOtMins = 0;
$presentDays = 0;
$approvedLeavesMonth = 0;
$noPayDays = 0;
$missingPunchCount = 0;
$monthlyLeavesUsedThisMonth = 0.0;
$monthlyQuota = 4.0;
$monthlyRemaining = 4.0;
$isProbation = ($emp['employment_type'] ?? 'confirmed') === 'probation';
$leaveAccrual = [
    'stage' => 'confirmed_permanent', 'annual_total' => 14.0, 'casual_total' => 7.0,
    'monthly_quota' => 4.0, 'is_accruing' => false, 'unlock_date' => '', 'badge' => 'Confirmed Employee', 'description' => ''
];
$myLeaves = [];
$myPunches = [];

if (isset($pdo)) {
    try {
        // Calculate dynamic leave accrual & sync balances using fresh $emp data
        $selectedMonthNum = (int)date('m', strtotime($selectedMonth . '-01'));
        $leaveAccrual = calculate_employee_annual_leave_accrual($emp, $year, $selectedMonthNum);
        sync_employee_leave_balances($pdo, $empId, $year, $selectedMonthNum);

        // Fetch refreshed balances
        $bStmt = $pdo->prepare("SELECT * FROM leave_balances WHERE employee_id = ? AND year = ?");
        $bStmt->execute([$empId, $year]);
        $rowBal = $bStmt->fetch();
        if ($rowBal) {
            $balance = array_merge($balance, $rowBal);
        }

        // Monthly Timesheet Calculation (7-Day Open Clinic & Adjusted No-Pay)
        $portalTimesheet = calculate_employee_timesheet($pdo, $empId, $startDate, $endDate);
        $myAttendance = $portalTimesheet['days'];
        $presentDays = $portalTimesheet['totals']['present_days'];
        $lateDaysCount = $portalTimesheet['totals']['late_count'];
        $totalLateMins = $portalTimesheet['totals']['total_late_mins'];
        $totalOtMins = $portalTimesheet['totals']['total_ot_mins'];
        $approvedLeavesMonth = $portalTimesheet['totals']['leave_days'];
        $noPayDays = $portalTimesheet['totals']['nopay_days'];

        // Check probation status from fresh $emp
        $isProbation = ($emp['employment_type'] ?? 'confirmed') === 'probation';

        // Monthly leaves used this selected month
        $monthlyLeavesUsedThisMonth = 0.0;
        try {
            $mlmStmt = $pdo->prepare("
                SELECT COALESCE(SUM(total_days), 0) 
                FROM leaves 
                WHERE employee_id = ? 
                  AND leave_type = 'Monthly' 
                  AND status = 'Approved' 
                  AND DATE_FORMAT(start_date, '%Y-%m') = ?
            ");
            $mlmStmt->execute([$empId, $selectedMonth]);
            $monthlyLeavesUsedThisMonth = (float)$mlmStmt->fetchColumn();
        } catch (Exception $e) {}
        $monthlyQuota = (float)($leaveAccrual['monthly_quota'] ?? $balance['monthly_quota'] ?? 4.0);
        $monthlyRemaining = max(0, $monthlyQuota - $monthlyLeavesUsedThisMonth);

        $missingPunchCount = 0;
        $todayStr = date('Y-m-d');

        foreach ($myAttendance as $row) {
            // Detect missed / incomplete punches on days where employee checked in or checked out
            $hasIn = !empty($row['check_in']) && $row['check_in'] !== '00:00:00';
            $hasOut = !empty($row['check_out']) && $row['check_out'] !== '00:00:00';
            $isPast = ($row['date'] < $todayStr);
            if ((!$hasIn && $hasOut) || ($hasIn && !$hasOut && $isPast)) {
                $missingPunchCount++;
            }
        }

        // My Leaves History
        $mlStmt = $pdo->prepare("
            SELECT l.*, a.name as approver_name 
            FROM leaves l 
            LEFT JOIN employees a ON l.approved_by = a.id 
            WHERE l.employee_id = ? 
            ORDER BY l.applied_at DESC
        ");
        $mlStmt->execute([$empId]);
        $myLeaves = $mlStmt->fetchAll();

        // My Manual Punches History
        $mpStmt = $pdo->prepare("
            SELECT m.*, a.name as authorizer_name 
            FROM manual_punches m 
            LEFT JOIN employees a ON m.authorized_by = a.id 
            WHERE m.employee_id = ? 
            ORDER BY m.requested_at DESC
        ");
        $mpStmt->execute([$empId]);
        $myPunches = $mpStmt->fetchAll();

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="space-y-6">
    <!-- Profile & Welcome Banner -->
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-slate-900 via-slate-900/90 to-slate-800 border border-slate-800 p-6 sm:p-8">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-6">
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-gold-600 to-amber-300 text-slate-950 font-bold font-serif text-2xl flex items-center justify-center shadow-xl shadow-gold-500/20">
                    <?= strtoupper(substr($emp['calling_name'] ?? $emp['name'], 0, 1)) ?>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-xl sm:text-2xl font-serif font-bold text-white"><?= htmlspecialchars($emp['name']) ?></h2>
                        <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-gold-500/15 text-gold-400 border border-gold-500/30">
                            #<?= htmlspecialchars($emp['machine_user_id']) ?>
                        </span>
                    </div>
                    <p class="text-xs text-slate-400 mt-1">
                        <?= htmlspecialchars($emp['designation']) ?> &bull; <span class="text-slate-300"><?= htmlspecialchars($emp['department']) ?></span>
                        &bull; Shift: <span class="text-slate-200 font-mono"><?= substr($emp['shift_start'], 0, 5) ?> - <?= substr($emp['shift_end'], 0, 5) ?></span>
                        <?php if (!empty($emp['email'])): ?>
                            &bull; <span class="text-cyan-400 font-mono"><?= htmlspecialchars($emp['email']) ?></span>
                        <?php endif; ?>
                    </p>
                    <?php if (!empty($emp['join_date']) || !empty($emp['appointment_date'])): ?>
                    <p class="text-[11px] text-slate-500 mt-0.5 font-mono">
                        <?php if (!empty($emp['join_date'])): ?>Joined: <span class="text-slate-300"><?= htmlspecialchars($emp['join_date']) ?></span><?php endif; ?>
                        <?php if (!empty($emp['appointment_date'])): ?> &bull; Date of Appointment: <span class="text-gold-400"><?= htmlspecialchars($emp['appointment_date']) ?></span><?php endif; ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Action Buttons -->
            <div class="flex items-center gap-2.5 flex-wrap">
                <button onclick="openApplyModal()" class="px-3.5 py-2 rounded-xl text-xs font-semibold text-slate-950 bg-gradient-to-r from-gold-500 to-amber-400 hover:from-gold-400 hover:to-amber-300 shadow-lg shadow-gold-500/20 transition-all flex items-center gap-1.5">
                    <i class="fa-solid fa-calendar-plus"></i>
                    <span>Apply for Leave</span>
                </button>
                <button onclick="openPunchModal()" class="px-3.5 py-2 rounded-xl text-xs font-semibold text-white bg-slate-800 hover:bg-slate-700 border border-slate-700 transition-all flex items-center gap-1.5">
                    <i class="fa-solid fa-clock-rotate-left text-amber-400"></i>
                    <span>Request Missed Punch</span>
                </button>
                <a href="change_password.php" class="px-3.5 py-2 rounded-xl text-xs font-semibold text-slate-200 bg-slate-800/90 hover:bg-slate-700 border border-slate-700 hover:border-gold-500/50 transition-all flex items-center gap-1.5">
                    <i class="fa-solid fa-key text-gold-400"></i>
                    <span>Change Password</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if ($message): ?>
        <div class="p-3.5 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-xs flex items-center gap-2">
            <i class="fa-solid fa-circle-check"></i>
            <span><?= htmlspecialchars($message) ?></span>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="p-3.5 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs flex items-center gap-2">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- RED WARNING BANNER FOR MISSED / INCOMPLETE PUNCHES -->
    <?php if ($missingPunchCount > 0): ?>
        <div class="bg-gradient-to-r from-rose-950/40 via-slate-900 to-slate-900 border border-rose-500/50 rounded-2xl p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-xl shadow-rose-500/10">
            <div class="flex items-center gap-3.5">
                <span class="w-10 h-10 rounded-xl bg-rose-500/20 text-rose-400 flex items-center justify-center text-lg shrink-0 animate-pulse">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </span>
                <div>
                    <h4 class="text-sm font-bold text-rose-300">Warning: <?= $missingPunchCount ?> Missed / Incomplete Punch(es) Recorded!</h4>
                    <p class="text-xs text-slate-400 mt-0.5">Some attendance days have an In-punch without Out-punch or vice versa. Submit a manual punch request for management authorization to fix them.</p>
                </div>
            </div>
            <button onclick="openPunchModal()" class="px-4 py-2.5 rounded-xl text-xs font-semibold text-white bg-rose-600 hover:bg-rose-500 shrink-0 transition-colors flex items-center gap-2 shadow-lg shadow-rose-600/30">
                <i class="fa-solid fa-wrench"></i> Fix Missed Punches
            </button>
        </div>
    <?php endif; ?>

    <!-- PERSONAL KPI CARDS (LATE, OT, ATTENDANCE, LEAVES, NO-PAY) -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
        <!-- Monthly Late Time -->
        <div class="bg-slate-900/70 border border-slate-800/80 rounded-2xl p-4 sm:p-5 hover:border-amber-500/40 transition-all">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium uppercase tracking-wider text-slate-400">Late Time</span>
                <span class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-hourglass-half"></i>
                </span>
            </div>
            <div class="mt-2.5">
                <span class="text-xl sm:text-2xl font-bold font-mono text-amber-400">
                    <?= format_minutes($totalLateMins) ?>
                </span>
                <p class="text-[10px] text-slate-400 mt-0.5"><?= $lateDaysCount ?> late days</p>
            </div>
        </div>

        <!-- Monthly Overtime (OT) -->
        <div class="bg-slate-900/70 border border-slate-800/80 rounded-2xl p-4 sm:p-5 hover:border-emerald-500/40 transition-all">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium uppercase tracking-wider text-slate-400">Overtime (OT)</span>
                <span class="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-business-time"></i>
                </span>
            </div>
            <div class="mt-2.5">
                <span class="text-xl sm:text-2xl font-bold font-mono text-emerald-400">
                    <?= number_format($totalOtMins / 60, 1) ?> hrs
                </span>
                <p class="text-[10px] text-slate-400 mt-0.5"><?= format_minutes($totalOtMins) ?> worked</p>
            </div>
        </div>

        <!-- Days Attended -->
        <div class="bg-slate-900/70 border border-slate-800/80 rounded-2xl p-4 sm:p-5 hover:border-blue-500/40 transition-all">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium uppercase tracking-wider text-slate-400">Days Present</span>
                <span class="w-8 h-8 rounded-xl bg-blue-500/10 text-blue-400 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-calendar-check"></i>
                </span>
            </div>
            <div class="mt-2.5">
                <span class="text-xl sm:text-2xl font-bold text-white"><?= $presentDays ?></span>
                <span class="text-xs text-slate-400 ml-1">Days</span>
                <p class="text-[10px] text-slate-400 mt-0.5"><?= date('d M', strtotime($startDate)) ?> &ndash; <?= date('d M Y', strtotime($endDate)) ?></p>
            </div>
        </div>

        <!-- Leaves Taken this Month -->
        <div class="bg-slate-900/70 border border-slate-800/80 rounded-2xl p-4 sm:p-5 hover:border-purple-500/40 transition-all">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium uppercase tracking-wider text-slate-400">Approved Leaves</span>
                <span class="w-8 h-8 rounded-xl bg-purple-500/10 text-purple-400 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-calendar-day"></i>
                </span>
            </div>
            <div class="mt-2.5">
                <span class="text-xl sm:text-2xl font-bold text-purple-300"><?= $approvedLeavesMonth ?></span>
                <span class="text-xs text-slate-400 ml-1">Days</span>
                <p class="text-[10px] text-slate-400 mt-0.5">Taken this month</p>
            </div>
        </div>

        <!-- No Pay / Absences this Month -->
        <div class="bg-slate-900/70 border <?= $noPayDays > 0 ? 'border-rose-500/50 bg-rose-950/20' : 'border-slate-800/80' ?> rounded-2xl p-4 sm:p-5 hover:border-rose-500/50 transition-all">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium uppercase tracking-wider text-slate-400">No-Pay Days</span>
                <span class="w-8 h-8 rounded-xl bg-rose-500/10 text-rose-400 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-ban"></i>
                </span>
            </div>
            <div class="mt-2.5">
                <span class="text-xl sm:text-2xl font-bold font-mono <?= $noPayDays > 0 ? 'text-rose-400' : 'text-slate-300' ?>">
                    <?= $noPayDays ?>
                </span>
                <span class="text-xs text-slate-400 ml-1">Days</span>
                <p class="text-[10px] <?= $noPayDays > 0 ? 'text-rose-400 font-semibold' : 'text-slate-400' ?> mt-0.5">
                    <?= $noPayDays > 0 ? 'Deducted as No Pay' : 'Zero No Pay' ?>
                </p>
            </div>
        </div>
    </div>

    <!-- LEAVE BALANCES BREAKDOWN -->
    <div class="bg-slate-900/70 border border-slate-800/80 rounded-2xl p-6">
        <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
            <h3 class="font-serif font-bold text-base text-white">My Leave Quotas & Balances</h3>
            <?php if ($leaveAccrual['stage'] === 'custom_override'): ?>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-purple-500/15 text-purple-300 border border-purple-500/30">
                    <i class="fa-solid fa-sliders text-[11px]"></i> Custom Leave Entitlement (Admin Configured)
                </span>
            <?php elseif ($leaveAccrual['stage'] === 'probation_joining_year'): ?>
                <?php
                $probMonths = (int)($emp['probation_months'] ?? 6);
                $joinDate = $emp['join_date'] ?? date('Y-m-d');
                $probEnd = !empty($emp['probation_end_date']) ? $emp['probation_end_date'] : date('Y-m-d', strtotime("+{$probMonths} months", strtotime($joinDate)));
                $today = date('Y-m-d');
                $daysLeft = max(0, round((strtotime($probEnd) - strtotime($today)) / 86400));
                $isDue = ($today >= $probEnd);
                ?>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-500/15 text-amber-300 border border-amber-500/30">
                    <i class="fa-solid fa-clock-rotate-left text-[11px]"></i> Probation <?= $probMonths ?>M (<?= $isDue ? 'Confirmation Due' : "{$daysLeft} days left" ?> &bull; 4 Monthly Only)
                </span>
            <?php elseif ($leaveAccrual['is_accruing']): ?>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-500/15 text-amber-300 border border-amber-500/30">
                    <i class="fa-solid fa-chart-line text-[11px]"></i> Accruing Annual (+0.5d/mo) &bull; Full 14+7 unlocks <?= htmlspecialchars($leaveAccrual['unlock_date']) ?>
                </span>
            <?php elseif ($leaveAccrual['stage'] === 'confirmed_post_appointment'): ?>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-500/15 text-emerald-300 border border-emerald-500/30">
                    <i class="fa-solid fa-award text-[11px]"></i> 1-Yr Milestone Reached (14 Annual + 7 Casual + 4 Monthly &bull; Balanced)
                </span>
            <?php else: ?>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-sky-500/15 text-sky-300 border border-sky-500/30">
                    <i class="fa-solid fa-circle-check text-[11px]"></i> Confirmed Employee (14 Annual + 7 Casual + 4 Monthly)
                </span>
            <?php endif; ?>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-4">
            <!-- 1. Monthly Leave -->
            <div class="p-3.5 rounded-xl bg-gold-950/20 border border-gold-500/30">
                <span class="text-xs text-gold-400 block font-semibold">Monthly Leave (<?= date('M', strtotime($selectedMonth . '-01')) ?>)</span>
                <div class="mt-1 flex items-baseline justify-between">
                    <span class="text-lg font-bold text-gold-300"><?= rtrim(rtrim(number_format($monthlyRemaining, 1), '0'), '.') ?></span>
                    <span class="text-[11px] text-slate-400">of <?= rtrim(rtrim(number_format($monthlyQuota, 1), '0'), '.') ?> Days</span>
                </div>
                <p class="text-[10px] text-slate-400 mt-0.5">Used: <?= rtrim(rtrim(number_format($monthlyLeavesUsedThisMonth, 1), '0'), '.') ?> days</p>
            </div>

            <!-- 2. Annual Leave -->
            <div class="p-3.5 rounded-xl bg-slate-950/60 border border-slate-800">
                <span class="text-xs text-slate-400 block">Annual Leave</span>
                <?php if ($leaveAccrual['stage'] === 'probation_joining_year'): ?>
                    <div class="mt-1 flex items-baseline justify-between">
                        <span class="text-xs font-semibold text-amber-400">Joining Year</span>
                        <span class="text-[10px] text-slate-500 font-mono">0 of 0</span>
                    </div>
                    <p class="text-[10px] text-slate-500 mt-0.5">+0.5d/mo starts Jan <?= ((int)date('Y', strtotime($emp['join_date'] ?? date('Y-m-d'))) + 1) ?></p>
                <?php elseif ($leaveAccrual['is_accruing']): ?>
                    <?php $annRemDisp = max(0, (float)$balance['annual_total'] - (float)$balance['annual_used']); ?>
                    <div class="mt-1 flex items-baseline justify-between">
                        <span class="text-lg font-bold text-amber-300"><?= rtrim(rtrim(number_format($annRemDisp, 1), '0'), '.') ?></span>
                        <span class="text-[11px] text-slate-400">of <?= rtrim(rtrim(number_format((float)$balance['annual_total'], 1), '0'), '.') ?> Accrued</span>
                    </div>
                    <p class="text-[10px] text-amber-400/90 mt-0.5">+0.5d/mo &bull; Used: <?= rtrim(rtrim(number_format((float)$balance['annual_used'], 1), '0'), '.') ?>d</p>
                <?php else: ?>
                    <?php $annRemDisp = max(0, (float)$balance['annual_total'] - (float)$balance['annual_used']); ?>
                    <div class="mt-1 flex items-baseline justify-between">
                        <span class="text-lg font-bold text-white"><?= rtrim(rtrim(number_format($annRemDisp, 1), '0'), '.') ?></span>
                        <span class="text-[11px] text-slate-500">of <?= rtrim(rtrim(number_format((float)$balance['annual_total'], 1), '0'), '.') ?></span>
                    </div>
                    <p class="text-[10px] text-slate-400 mt-0.5">Used: <?= rtrim(rtrim(number_format((float)$balance['annual_used'], 1), '0'), '.') ?> days</p>
                <?php endif; ?>
            </div>

            <!-- 3. Casual Leave -->
            <div class="p-3.5 rounded-xl bg-slate-950/60 border border-slate-800">
                <span class="text-xs text-slate-400 block">Casual Leave</span>
                <?php if ($leaveAccrual['casual_total'] <= 0): ?>
                    <div class="mt-1 flex items-baseline justify-between">
                        <span class="text-xs font-semibold text-amber-400">Not Eligible</span>
                        <span class="text-[10px] text-slate-500 font-mono">0 of 0</span>
                    </div>
                    <p class="text-[10px] text-slate-500 mt-0.5">Unlocks <?= htmlspecialchars($leaveAccrual['unlock_date'] ?: 'on 1-Yr Confirmation') ?></p>
                <?php else: ?>
                    <?php $casRemDisp = max(0, (float)$balance['casual_total'] - (float)$balance['casual_used']); ?>
                    <div class="mt-1 flex items-baseline justify-between">
                        <span class="text-lg font-bold text-white"><?= rtrim(rtrim(number_format($casRemDisp, 1), '0'), '.') ?></span>
                        <span class="text-[11px] text-slate-500">of <?= rtrim(rtrim(number_format((float)$balance['casual_total'], 1), '0'), '.') ?></span>
                    </div>
                    <p class="text-[10px] text-slate-400 mt-0.5">Used: <?= rtrim(rtrim(number_format((float)$balance['casual_used'], 1), '0'), '.') ?> days</p>
                <?php endif; ?>
            </div>

            <!-- 4. Medical Leave -->
            <div class="p-3.5 rounded-xl bg-slate-950/60 border border-slate-800">
                <span class="text-xs text-slate-400 block">Medical Leave</span>
                <?php $medRemDisp = max(0, (float)$balance['medical_total'] - (float)$balance['medical_used']); ?>
                <div class="mt-1 flex items-baseline justify-between">
                    <span class="text-lg font-bold text-white"><?= rtrim(rtrim(number_format($medRemDisp, 1), '0'), '.') ?></span>
                    <span class="text-[11px] text-slate-500">of <?= rtrim(rtrim(number_format((float)$balance['medical_total'], 1), '0'), '.') ?></span>
                </div>
                <p class="text-[10px] text-slate-400 mt-0.5">Used: <?= rtrim(rtrim(number_format((float)$balance['medical_used'], 1), '0'), '.') ?> days</p>
            </div>

            <!-- 5. Maternity / Short Leave -->
            <?php if ($isMaternityEligible): ?>
            <div class="p-3.5 rounded-xl bg-purple-950/30 border border-purple-800/40">
                <span class="text-xs text-purple-300 block font-semibold">Maternity Leave</span>
                <div class="mt-1 flex items-baseline justify-between">
                    <span class="text-lg font-bold text-purple-200"><?= $balance['maternity_total'] - $balance['maternity_used'] ?></span>
                    <span class="text-[11px] text-purple-400/80">of 84 days</span>
                </div>
                <p class="text-[10px] text-purple-400/80 mt-0.5">Eligible female staff</p>
            </div>
            <?php else: ?>
            <div class="p-3.5 rounded-xl bg-slate-950/60 border border-slate-800">
                <span class="text-xs text-slate-400 block">Short Leaves</span>
                <div class="mt-1 flex items-baseline justify-between">
                    <span class="text-lg font-bold text-slate-400">Available</span>
                    <span class="text-[11px] text-slate-500">2 per month</span>
                </div>
                <p class="text-[10px] text-slate-500 mt-0.5">Permission needed</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- MY ATTENDANCE LOG (DATE RANGE) -->
    <div class="bg-slate-900/70 border border-slate-800/80 rounded-2xl overflow-hidden shadow-xl">
        <div class="p-5 border-b border-slate-800 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div>
                <h3 class="font-serif font-bold text-base text-white">My Attendance Log</h3>
                <p class="text-xs text-slate-400 mt-0.5">
                    Showing <span class="text-gold-400 font-mono font-semibold"><?= date('d M Y', strtotime($startDate)) ?></span> to <span class="text-gold-400 font-mono font-semibold"><?= date('d M Y', strtotime($endDate)) ?></span> &bull; Machine check-in, check-out &amp; late/OT details
                </p>
            </div>

            <!-- Date From - To Selector (Default: 1st of month -> Today) -->
            <form method="GET" action="employee_portal.php" class="flex flex-wrap items-center gap-2">
                <div class="flex items-center gap-1.5">
                    <label for="att_from_date" class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">From</label>
                    <input type="date" id="att_from_date" name="from_date" value="<?= htmlspecialchars($startDate) ?>"
                        class="px-2.5 py-1.5 bg-slate-950 border border-slate-700 rounded-xl text-xs text-slate-100 font-mono focus:border-gold-500 focus:outline-none">
                </div>
                <div class="flex items-center gap-1.5">
                    <label for="att_to_date" class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">To</label>
                    <input type="date" id="att_to_date" name="to_date" value="<?= htmlspecialchars($endDate) ?>"
                        class="px-2.5 py-1.5 bg-slate-950 border border-slate-700 rounded-xl text-xs text-slate-100 font-mono focus:border-gold-500 focus:outline-none">
                </div>
                <button type="submit" class="px-3.5 py-1.5 rounded-xl text-xs font-semibold text-slate-950 bg-gold-400 hover:bg-gold-500 transition-colors flex items-center gap-1.5">
                    <i class="fa-solid fa-magnifying-glass text-[10px]"></i>
                    <span>Search</span>
                </button>
                <?php if ($startDate !== $defaultFrom || $endDate !== $defaultTo): ?>
                    <a href="employee_portal.php" class="px-2.5 py-1.5 rounded-xl text-xs font-medium text-slate-300 bg-slate-800 hover:bg-slate-700 border border-slate-700 transition-colors" title="Reset to 1st of month to Today">
                        Reset
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-300">
                <thead class="bg-slate-950/70 text-[11px] font-semibold uppercase tracking-wider text-slate-400 border-b border-slate-800">
                    <tr>
                        <th class="py-3 px-4">Date</th>
                        <th class="py-3 px-4">Day</th>
                        <th class="py-3 px-4">Check In</th>
                        <th class="py-3 px-4">Check Out</th>
                        <th class="py-3 px-4">Work Hours</th>
                        <th class="py-3 px-4">Late Time</th>
                        <th class="py-3 px-4">Overtime (OT)</th>
                        <th class="py-3 px-4">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 font-mono text-[11px]">
                    <?php if (empty($myAttendance)): ?>
                        <tr><td colspan="8" class="text-center py-8 text-slate-500 font-sans">No attendance records found for <?= date('d M Y', strtotime($startDate)) ?> to <?= date('d M Y', strtotime($endDate)) ?>.</td></tr>
                    <?php else: ?>
                        <?php foreach ($myAttendance as $log): 
                            $hasIn = !empty($log['check_in']) && $log['check_in'] !== '00:00:00';
                            $hasOut = !empty($log['check_out']) && $log['check_out'] !== '00:00:00';
                            $isPast = ($log['date'] < date('Y-m-d'));
                            $isMissingIn = (!$hasIn && $hasOut);
                            $isMissingOut = ($hasIn && !$hasOut && $isPast);
                            $isIncomplete = ($isMissingIn || $isMissingOut);
                        ?>
                            <tr class="hover:bg-slate-800/30 transition-colors <?= $isIncomplete ? 'bg-rose-950/20' : '' ?>">
                                <td class="py-3 px-4 text-white font-medium"><?= $log['date'] ?></td>
                                <td class="py-3 px-4 text-slate-400 font-sans"><?= date('l', strtotime($log['date'])) ?></td>
                                <td class="py-3 px-4">
                                    <?php if ($hasIn): ?>
                                        <span class="text-emerald-400 font-bold"><?= format_time_12h($log['check_in']) ?></span>
                                    <?php elseif ($isMissingIn): ?>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-500/20 text-rose-400 border border-rose-500/40 inline-flex items-center gap-1 animate-pulse">
                                            <i class="fa-solid fa-triangle-exclamation"></i> Missing In
                                        </span>
                                    <?php else: ?>
                                        <span class="text-slate-600">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4">
                                    <?php if ($hasOut): ?>
                                        <span class="text-slate-200"><?= format_time_12h($log['check_out']) ?></span>
                                    <?php elseif ($isMissingOut): ?>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-500/20 text-rose-400 border border-rose-500/40 inline-flex items-center gap-1 animate-pulse">
                                            <i class="fa-solid fa-triangle-exclamation"></i> Missing Out
                                        </span>
                                    <?php else: ?>
                                        <span class="text-slate-600">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4"><?= $log['work_hours'] > 0 ? number_format($log['work_hours'], 1) . ' hrs' : '-' ?></td>
                                <td class="py-3 px-4">
                                    <?php if ($log['late_minutes'] > 0): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-500/15 text-amber-300 border border-amber-500/30">
                                            +<?= format_minutes($log['late_minutes']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-slate-600">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4">
                                    <?php if ($log['overtime_minutes'] > 0): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-indigo-500/15 text-indigo-300 border border-indigo-500/30">
                                            +<?= format_minutes($log['overtime_minutes']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-slate-600">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 font-sans">
                                    <?php if (!empty($log['is_nopay'])): ?>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-500/20 text-rose-300 border border-rose-500/40 inline-flex items-center gap-1">
                                            <i class="fa-solid fa-triangle-exclamation"></i> No-Pay (Absent)
                                        </span>
                                        <button type="button" onclick="openPunchModalWithDate('<?= $log['date'] ?>')" class="text-[10px] text-amber-400 hover:text-amber-300 underline block mt-0.5">
                                            Request Punch &rarr;
                                        </button>
                                    <?php elseif ($isIncomplete): ?>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-500/20 text-rose-300 border border-rose-500/40 inline-flex items-center gap-1">
                                            ⚠️ Missed Punch
                                        </span>
                                        <button type="button" onclick="openPunchModalWithDate('<?= $log['date'] ?>')" class="text-[10px] text-amber-400 hover:text-amber-300 underline block mt-0.5">
                                            Fix &rarr;
                                        </button>
                                    <?php elseif (($log['status_code'] ?? '') === 'holiday'): ?>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-500/15 text-amber-300 border border-amber-500/30 inline-flex items-center gap-1">
                                            <i class="fa-solid fa-moon"></i> <?= htmlspecialchars($log['status']) ?>
                                        </span>
                                        <span class="text-[10px] text-slate-400 block mt-0.5"><?= htmlspecialchars($log['notes'] ?? '') ?></span>
                                    <?php elseif (($log['status_code'] ?? '') === 'off'): ?>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-medium bg-slate-800 text-slate-400 border border-slate-700">
                                            Weekly Off (Sun)
                                        </span>
                                    <?php elseif (($log['status_code'] ?? '') === 'future'): ?>
                                        <span class="text-slate-500 text-[10px]">Upcoming</span>
                                    <?php else: ?>
                                        <?php
                                        $stClass = match($log['status'] ?? '') {
                                            'Present' => 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30',
                                            'Late' => 'bg-amber-500/15 text-amber-400 border-amber-500/30',
                                            'Half Day' => 'bg-blue-500/15 text-blue-400 border-blue-500/30',
                                            'On Leave' => 'bg-purple-500/15 text-purple-400 border-purple-500/30',
                                            default => 'bg-purple-500/15 text-purple-300 border-purple-500/30'
                                        };
                                        ?>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-medium border <?= $stClass ?>">
                                            <?= htmlspecialchars($log['status'] ?? 'Scheduled') ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- MY LEAVE APPLICATIONS STATUS -->
    <div class="bg-slate-900/70 border border-slate-800/80 rounded-2xl overflow-hidden shadow-xl">
        <div class="p-5 border-b border-slate-800 flex items-center justify-between">
            <h3 class="font-serif font-bold text-base text-white">My Leave Requests Status</h3>
            <span class="text-xs text-slate-400"><?= count($myLeaves) ?> requests</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-300">
                <thead class="bg-slate-950/70 text-[11px] font-semibold uppercase tracking-wider text-slate-400 border-b border-slate-800">
                    <tr>
                        <th class="py-3 px-4">Leave Type</th>
                        <th class="py-3 px-4">Period</th>
                        <th class="py-3 px-4">Days</th>
                        <th class="py-3 px-4">Reason</th>
                        <th class="py-3 px-4">Applied On</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4">Approver Remarks</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 font-mono text-[11px]">
                    <?php if (empty($myLeaves)): ?>
                        <tr><td colspan="7" class="text-center py-6 text-slate-500 font-sans">You have not submitted any leave applications.</td></tr>
                    <?php else: ?>
                        <?php foreach ($myLeaves as $ml): ?>
                            <tr class="hover:bg-slate-800/30">
                                <td class="py-3 px-4 font-sans font-medium text-white"><?= htmlspecialchars($ml['leave_type']) ?></td>
                                <td class="py-3 px-4 text-slate-300"><?= $ml['start_date'] ?> &rarr; <?= $ml['end_date'] ?></td>
                                <td class="py-3 px-4 text-gold-400 font-bold"><?= $ml['total_days'] ?></td>
                                <td class="py-3 px-4 font-sans text-slate-400 max-w-xs truncate"><?= htmlspecialchars($ml['reason']) ?></td>
                                <td class="py-3 px-4 text-slate-500"><?= date('d M Y', strtotime($ml['applied_at'])) ?></td>
                                <td class="py-3 px-4 font-sans">
                                    <?php
                                    $sB = match($ml['status']) {
                                        'Approved' => 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30',
                                        'Rejected' => 'bg-rose-500/15 text-rose-400 border-rose-500/30',
                                        default => 'bg-amber-500/15 text-amber-400 border-amber-500/30'
                                    };
                                    ?>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-medium border <?= $sB ?>">
                                        <?= htmlspecialchars($ml['status']) ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 font-sans text-slate-400">
                                    <?= htmlspecialchars($ml['rejection_reason'] ?? ($ml['status'] === 'Approved' ? 'Approved by ' . ($ml['approver_name'] ?? 'Management') : '-')) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Apply Leave -->
<div id="applyModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm hidden overflow-y-auto">
    <div class="relative w-full max-w-lg bg-slate-900 border border-slate-800 rounded-2xl p-6 sm:p-8 shadow-2xl">
        <div class="flex items-center justify-between pb-4 border-b border-slate-800 mb-6">
            <h3 class="text-lg font-serif font-bold text-white">Apply for Leave</h3>
            <button onclick="closeApplyModal()" class="text-slate-400 hover:text-white p-2">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="employee_portal.php" class="space-y-4">
            <input type="hidden" name="action" value="apply_leave">

            <!-- Leave Type -->
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                    Leave Type *
                </label>
                <select name="leave_type" id="portal_leave_type" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-100 focus:border-gold-500 focus:outline-none">
                    <option value="Monthly" class="text-gold-300 font-semibold">Monthly Leave (<?= rtrim(rtrim(number_format($monthlyRemaining, 1), '0'), '.') ?> of <?= rtrim(rtrim(number_format($monthlyQuota, 1), '0'), '.') ?> days remaining for <?= date('M Y') ?>)</option>
                    <?php
                    // Use $leaveAccrual (dynamic accrual or custom admin quota) for Annual & Casual eligibility
                    $accrualForDropdown = $leaveAccrual ?? ['annual_total' => 14.0, 'casual_total' => 7.0, 'is_accruing' => false, 'stage' => 'confirmed_permanent'];
                    $annualAvailable = ($accrualForDropdown['annual_total'] ?? 0) > 0;
                    $casualAvailable = ($accrualForDropdown['casual_total'] ?? 0) > 0;
                    $annualTotalDisp  = (float)($balance['annual_total'] ?? $accrualForDropdown['annual_total']);
                    $casualTotalDisp  = (float)($balance['casual_total'] ?? $accrualForDropdown['casual_total']);
                    $annualRemaining  = max(0, $annualTotalDisp - (float)($balance['annual_used'] ?? 0));
                    $casualRemaining  = max(0, $casualTotalDisp - (float)($balance['casual_used'] ?? 0));
                    ?>
                    <?php if ($casualAvailable): ?>
                        <option value="Casual">Casual Leave (<?= rtrim(rtrim(number_format($casualRemaining, 1), '0'), '.') ?> remaining of <?= rtrim(rtrim(number_format($casualTotalDisp, 1), '0'), '.') ?>)</option>
                    <?php else: ?>
                        <option value="Casual" disabled class="text-slate-600">Casual Leave – Unlocks after Date of Appointment</option>
                    <?php endif; ?>
                    <?php if ($annualAvailable): ?>
                        <?php if (!empty($accrualForDropdown['is_accruing'])): ?>
                            <option value="Annual">Annual Leave – Accrued <?= rtrim(rtrim(number_format($annualTotalDisp, 1), '0'), '.') ?> days (<?= rtrim(rtrim(number_format($annualRemaining, 1), '0'), '.') ?> remaining)</option>
                        <?php else: ?>
                            <option value="Annual">Annual Leave (<?= rtrim(rtrim(number_format($annualRemaining, 1), '0'), '.') ?> remaining of <?= rtrim(rtrim(number_format($annualTotalDisp, 1), '0'), '.') ?>)</option>
                        <?php endif; ?>
                    <?php else: ?>
                        <option value="Annual" disabled class="text-slate-600">Annual Leave – Not yet eligible (Joining Year)</option>
                    <?php endif; ?>
                    <?php $medRemOpt = max(0, (float)$balance['medical_total'] - (float)$balance['medical_used']); ?>
                    <option value="Medical">Medical / Sick Leave (<?= rtrim(rtrim(number_format($medRemOpt, 1), '0'), '.') ?> remaining of <?= rtrim(rtrim(number_format((float)$balance['medical_total'], 1), '0'), '.') ?>)</option>
                    <?php if ($isMaternityEligible): ?>
                        <option value="Maternity" class="text-purple-300 font-semibold">
                            Maternity Leave (<?= $balance['maternity_total'] - $balance['maternity_used'] ?> days quota)
                        </option>
                    <?php endif; ?>
                    <option value="Half Day">Half Day</option>
                    <option value="Short Leave">Short Leave</option>
                </select>
            </div>

            <!-- Duration (Full Day vs Half Day 0.5) -->
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                    Leave Duration *
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="flex items-center gap-2 p-2.5 rounded-xl bg-slate-950 border border-slate-800 cursor-pointer hover:border-gold-500/50 transition-colors">
                        <input type="radio" name="duration" value="full" checked onchange="togglePortalDuration()" class="text-gold-400 focus:ring-gold-500">
                        <div>
                            <span class="text-xs font-semibold text-white block">Full Day(s)</span>
                            <span class="text-[10px] text-slate-400">1.0 day per date</span>
                        </div>
                    </label>
                    <label class="flex items-center gap-2 p-2.5 rounded-xl bg-slate-950 border border-slate-800 cursor-pointer hover:border-gold-500/50 transition-colors">
                        <input type="radio" name="duration" value="half" onchange="togglePortalDuration()" class="text-gold-400 focus:ring-gold-500">
                        <div>
                            <span class="text-xs font-semibold text-amber-300 block">Half Day (0.5 Day)</span>
                            <span class="text-[10px] text-slate-400">Uses 0.5 from quota</span>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Dates -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                        Start Date *
                    </label>
                    <input type="date" name="start_date" id="portal_start_date" onchange="syncPortalEndDate()" required value="<?= date('Y-m-d') ?>"
                        class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-100 font-mono focus:border-gold-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                        End Date *
                    </label>
                    <input type="date" name="end_date" id="portal_end_date" required value="<?= date('Y-m-d') ?>"
                        class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-100 font-mono focus:border-gold-500 focus:outline-none">
                </div>
            </div>

            <!-- Reason -->
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                    Reason / Details *
                </label>
                <textarea name="reason" rows="3" required placeholder="Explain the reason for requesting leave..."
                    class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-100 focus:border-gold-500 focus:outline-none"></textarea>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-800">
                <button type="button" onclick="closeApplyModal()" class="px-4 py-2.5 rounded-xl text-xs font-medium text-slate-400 hover:bg-slate-800">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2.5 rounded-xl text-xs font-semibold text-slate-950 bg-gold-400 hover:bg-gold-500 shadow-lg shadow-gold-500/20">
                    Submit Application
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Request Punch -->
<div id="punchModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm hidden overflow-y-auto">
    <div class="relative w-full max-w-lg bg-slate-900 border border-slate-800 rounded-2xl p-6 sm:p-8 shadow-2xl">
        <div class="flex items-center justify-between pb-4 border-b border-slate-800 mb-6">
            <h3 class="text-lg font-serif font-bold text-white">Request Missed In/Out Punch</h3>
            <button onclick="closePunchModal()" class="text-slate-400 hover:text-white p-2">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="employee_portal.php" class="space-y-4">
            <input type="hidden" name="action" value="request_punch">

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                    Attendance Date *
                </label>
                <input type="date" name="date" required value="<?= date('Y-m-d') ?>"
                    class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-100 font-mono focus:border-gold-500 focus:outline-none">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                        Check-In Time
                    </label>
                    <input type="time" name="requested_in" value="09:00"
                        class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-100 font-mono focus:border-gold-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                        Check-Out Time
                    </label>
                    <input type="time" name="requested_out" value="18:00"
                        class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-100 font-mono focus:border-gold-500 focus:outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                    Reason for Missed Punch *
                </label>
                <textarea name="reason" rows="3" required placeholder="e.g. Fingerprint sensor did not detect, power cut, off-site client treatment..."
                    class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-100 focus:border-gold-500 focus:outline-none"></textarea>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-800">
                <button type="button" onclick="closePunchModal()" class="px-4 py-2.5 rounded-xl text-xs font-medium text-slate-400 hover:bg-slate-800">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2.5 rounded-xl text-xs font-semibold text-slate-950 bg-amber-400 hover:bg-amber-300">
                    Submit Request
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const applyModal = document.getElementById('applyModal');
    const punchModal = document.getElementById('punchModal');
    function openApplyModal() { applyModal.classList.remove('hidden'); }
    function closeApplyModal() { applyModal.classList.add('hidden'); }
    function openPunchModal() { punchModal.classList.remove('hidden'); }
    function closePunchModal() { punchModal.classList.add('hidden'); }
    function openPunchModalWithDate(date) {
        const dInput = document.querySelector('#punchModal input[name="date"]');
        if (dInput) dInput.value = date;
        openPunchModal();
    }
    function togglePortalDuration() {
        const isHalf = document.querySelector('input[name="duration"]:checked')?.value === 'half';
        const sIn = document.getElementById('portal_start_date');
        const eIn = document.getElementById('portal_end_date');
        if (isHalf && sIn && eIn) {
            eIn.value = sIn.value;
            eIn.readOnly = true;
            eIn.classList.add('opacity-60');
        } else if (eIn) {
            eIn.readOnly = false;
            eIn.classList.remove('opacity-60');
        }
    }
    function syncPortalEndDate() {
        const isHalf = document.querySelector('input[name="duration"]:checked')?.value === 'half';
        const sIn = document.getElementById('portal_start_date');
        const eIn = document.getElementById('portal_end_date');
        if (sIn && eIn && (isHalf || eIn.value < sIn.value)) {
            eIn.value = sIn.value;
        }
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
