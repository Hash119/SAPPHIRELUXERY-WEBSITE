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

// Selected Month for Attendance
$selectedMonth = $_GET['month'] ?? date('Y-m');
$year = (int)date('Y', strtotime($selectedMonth . '-01'));
$monthNum = (int)date('m', strtotime($selectedMonth . '-01'));
$startDate = date('Y-m-01', strtotime($selectedMonth . '-01'));
$endDate = date('Y-m-t', strtotime($selectedMonth . '-01'));

// Handle Leave Application
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($pdo)) {
    $action = $_POST['action'] ?? '';

    if ($action === 'apply_leave') {
        $type = $_POST['leave_type'] ?? 'Casual';
        $start = $_POST['start_date'] ?? date('Y-m-d');
        $end = $_POST['end_date'] ?? $start;
        $reason = trim($_POST['reason'] ?? '');

        // Security check for Maternity
        if ($type === 'Maternity' && !$isMaternityEligible) {
            $error = "Maternity leave is only available for eligible female staff.";
        } else {
            $sT = strtotime($start);
            $eT = strtotime($end);
            $days = max(1, round(($eT - $sT) / 86400) + 1);
            if ($type === 'Half Day' || $type === 'Short Leave') $days = 0.5;

            try {
                $ins = $pdo->prepare("
                    INSERT INTO leaves (employee_id, leave_type, start_date, end_date, total_days, reason, status)
                    VALUES (?, ?, ?, ?, ?, ?, 'Pending')
                ");
                $ins->execute([$empId, $type, $start, $end, $days, $reason]);
                $message = "Leave application submitted successfully! Management will review and authorize it.";
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
    'medical_total' => 14.0, 'medical_used' => 0.0,
    'maternity_total' => $isMaternityEligible ? 84.0 : 0.0, 'maternity_used' => 0.0
];

$myAttendance = [];
$totalLateMins = 0;
$lateDaysCount = 0;
$totalOtMins = 0;
$presentDays = 0;
$myLeaves = [];
$myPunches = [];

if (isset($pdo)) {
    try {
        // Balances
        $bStmt = $pdo->prepare("SELECT * FROM leave_balances WHERE employee_id = ? AND year = ?");
        $bStmt->execute([$empId, $year]);
        $rowBal = $bStmt->fetch();
        if ($rowBal) {
            $balance = array_merge($balance, $rowBal);
        }

        // Monthly Timesheet Calculation with Sri Lanka Holidays & Adjusted No-Pay
        $portalTimesheet = calculate_employee_timesheet($pdo, $empId, $startDate, $endDate);
        $myAttendance = $portalTimesheet['days'];
        $presentDays = $portalTimesheet['totals']['present_days'];
        $lateDaysCount = $portalTimesheet['totals']['late_count'];
        $totalLateMins = $portalTimesheet['totals']['total_late_mins'];
        $totalOtMins = $portalTimesheet['totals']['total_ot_mins'];
        $approvedLeavesMonth = $portalTimesheet['totals']['leave_days'];
        $noPayDays = $portalTimesheet['totals']['nopay_days'];

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
                    </p>
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
                <p class="text-[10px] text-slate-400 mt-0.5"><?= date('M Y', strtotime($selectedMonth . '-01')) ?></p>
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
        <h3 class="font-serif font-bold text-base text-white mb-4">My <?= $year ?> Leave Quotas & Balances</h3>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="p-3.5 rounded-xl bg-slate-950/60 border border-slate-800">
                <span class="text-xs text-slate-400 block">Annual Leave</span>
                <div class="mt-1 flex items-baseline justify-between">
                    <span class="text-lg font-bold text-white"><?= $balance['annual_total'] - $balance['annual_used'] ?></span>
                    <span class="text-[11px] text-slate-500">of <?= $balance['annual_total'] ?></span>
                </div>
            </div>
            <div class="p-3.5 rounded-xl bg-slate-950/60 border border-slate-800">
                <span class="text-xs text-slate-400 block">Casual Leave</span>
                <div class="mt-1 flex items-baseline justify-between">
                    <span class="text-lg font-bold text-white"><?= $balance['casual_total'] - $balance['casual_used'] ?></span>
                    <span class="text-[11px] text-slate-500">of <?= $balance['casual_total'] ?></span>
                </div>
            </div>
            <div class="p-3.5 rounded-xl bg-slate-950/60 border border-slate-800">
                <span class="text-xs text-slate-400 block">Medical Leave</span>
                <div class="mt-1 flex items-baseline justify-between">
                    <span class="text-lg font-bold text-white"><?= $balance['medical_total'] - $balance['medical_used'] ?></span>
                    <span class="text-[11px] text-slate-500">of <?= $balance['medical_total'] ?></span>
                </div>
            </div>
            <?php if ($isMaternityEligible): ?>
            <div class="p-3.5 rounded-xl bg-purple-950/30 border border-purple-800/40">
                <span class="text-xs text-purple-300 block font-semibold">Maternity Leave</span>
                <div class="mt-1 flex items-baseline justify-between">
                    <span class="text-lg font-bold text-purple-200"><?= $balance['maternity_total'] - $balance['maternity_used'] ?></span>
                    <span class="text-[11px] text-purple-400/80">of 84 days</span>
                </div>
            </div>
            <?php else: ?>
            <div class="p-3.5 rounded-xl bg-slate-950/60 border border-slate-800">
                <span class="text-xs text-slate-400 block">Short Leaves</span>
                <div class="mt-1 flex items-baseline justify-between">
                    <span class="text-lg font-bold text-slate-400">Available</span>
                    <span class="text-[11px] text-slate-500">2 per month</span>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- MY ATTENDANCE LOG FOR THE MONTH -->
    <div class="bg-slate-900/70 border border-slate-800/80 rounded-2xl overflow-hidden shadow-xl">
        <div class="p-5 border-b border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h3 class="font-serif font-bold text-base text-white">My Attendance Log</h3>
                <p class="text-xs text-slate-400 mt-0.5">Machine check-in, check-out, and daily late/OT details</p>
            </div>

            <!-- Month selector -->
            <form method="GET" action="employee_portal.php" class="flex items-center gap-2">
                <input type="month" name="month" value="<?= htmlspecialchars($selectedMonth) ?>" onchange="this.form.submit()"
                    class="px-3 py-1.5 bg-slate-950 border border-slate-700 rounded-xl text-xs text-slate-100 font-mono focus:border-gold-500 focus:outline-none">
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
                        <tr><td colspan="8" class="text-center py-8 text-slate-500 font-sans">No attendance records found for <?= date('F Y', strtotime($selectedMonth . '-01')) ?>.</td></tr>
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
                    <option value="Casual">Casual Leave (<?= $balance['casual_total'] - $balance['casual_used'] ?> remaining)</option>
                    <option value="Annual">Annual Leave (<?= $balance['annual_total'] - $balance['annual_used'] ?> remaining)</option>
                    <option value="Medical">Medical / Sick Leave (<?= $balance['medical_total'] - $balance['medical_used'] ?> remaining)</option>
                    <?php if ($isMaternityEligible): ?>
                        <option value="Maternity" class="text-purple-300 font-semibold">
                            Maternity Leave (<?= $balance['maternity_total'] - $balance['maternity_used'] ?> days quota)
                        </option>
                    <?php endif; ?>
                    <option value="Half Day">Half Day</option>
                    <option value="Short Leave">Short Leave</option>
                </select>
            </div>

            <!-- Dates -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                        Start Date *
                    </label>
                    <input type="date" name="start_date" required value="<?= date('Y-m-d') ?>"
                        class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-100 font-mono focus:border-gold-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                        End Date *
                    </label>
                    <input type="date" name="end_date" required value="<?= date('Y-m-d') ?>"
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
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
