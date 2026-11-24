<?php
/**
 * Sapphire Aesthetic & Luxury - Management Dashboard
 */
require_once __DIR__ . '/config/auth.php';
require_login();

$user = current_user();
if (!is_admin_or_manager($user)) {
    header('Location: employee_portal.php');
    exit;
}

$pageTitle = 'Executive Dashboard';
$today = date('Y-m-d');

// 1. Fetch Key Metrics
$totalEmployees = 0;
$todayPresent = 0;
$todayLate = 0;
$todayAbsent = 0;
$todayOnLeave = 0;
$pendingLeaves = 0;
$pendingPunches = 0;

if (isset($pdo)) {
    try {
        $totalEmployees = (int)$pdo->query("SELECT COUNT(*) FROM employees WHERE status = 'active'")->fetchColumn();
        
        // Today's attendance counts
        $attStmt = $pdo->prepare("
            SELECT status, COUNT(*) as cnt 
            FROM attendance_logs 
            WHERE date = ? 
            GROUP BY status
        ");
        $attStmt->execute([$today]);
        $statusCounts = $attStmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $todayPresent = ($statusCounts['Present'] ?? 0) + ($statusCounts['Late'] ?? 0);
        $todayLate    = $statusCounts['Late'] ?? 0;
        $todayAbsent  = $statusCounts['Absent'] ?? 0;
        $todayOnLeave = $statusCounts['On Leave'] ?? 0;

        // Pending approvals
        $pendingLeaves = (int)$pdo->query("SELECT COUNT(*) FROM leaves WHERE status = 'Pending'")->fetchColumn();
        $pendingPunches = (int)$pdo->query("SELECT COUNT(*) FROM manual_punches WHERE status = 'Pending'")->fetchColumn();

        // 2. Fetch Today's Live Attendance Table + Today's Approved Leaves
        $todayLogsStmt = $pdo->prepare("
            SELECT e.id as emp_id, e.machine_user_id, e.name, e.calling_name, e.department, e.designation, e.gender, e.role, e.shift_start, e.shift_end,
                   a.check_in, a.check_out, a.work_hours, a.late_minutes, a.early_leave_minutes, a.overtime_minutes,
                   COALESCE(a.status, 'Absent') as att_status, a.source
            FROM employees e
            LEFT JOIN attendance_logs a ON e.id = a.employee_id AND a.date = ?
            WHERE e.status = 'active'
            ORDER BY e.machine_user_id ASC
        ");
        $todayLogsStmt->execute([$today]);
        $todayLogs = $todayLogsStmt->fetchAll();

        // Fetch approved leaves covering today
        $todayLeavesMap = [];
        try {
            $tlStmt = $pdo->prepare("SELECT employee_id, leave_type, total_days FROM leaves WHERE status = 'Approved' AND ? BETWEEN start_date AND end_date");
            $tlStmt->execute([$today]);
            foreach ($tlStmt->fetchAll() as $tlRow) {
                $todayLeavesMap[$tlRow['employee_id']] = $tlRow;
            }
        } catch (Exception $e) {}

        // Split into Today Present vs Today Absent / Not In Yet (excluding system admin #999 from absent list)
        $todayPresentList = [];
        $todayAbsentList = [];
        foreach ($todayLogs as $row) {
            $hasPunch = (!empty($row['check_in']) && $row['check_in'] !== '00:00:00') || (!empty($row['check_out']) && $row['check_out'] !== '00:00:00');
            if ($hasPunch || in_array($row['att_status'], ['Present', 'Late', 'Half Day'])) {
                $todayPresentList[] = $row;
            } elseif ((int)$row['machine_user_id'] !== 999) {
                $row['approved_leave'] = $todayLeavesMap[$row['emp_id']] ?? null;
                $todayAbsentList[] = $row;
            }
        }
        $todayPresent = count($todayPresentList);
        $todayAbsent  = count($todayAbsentList);

        // 3. Fetch Recent Leave Requests
        $recentLeavesStmt = $pdo->query("
            SELECT l.*, e.name as emp_name, e.calling_name, e.department
            FROM leaves l
            JOIN employees e ON l.employee_id = e.id
            ORDER BY l.applied_at DESC
            LIMIT 5
        ");
        $recentLeaves = $recentLeavesStmt->fetchAll();

        // 4. Fetch 7-day attendance trend for Chart.js
        $trendDates = [];
        $trendPresent = [];
        $trendLate = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i days"));
            $trendDates[] = date('D (d)', strtotime($d));

            $st = $pdo->prepare("SELECT SUM(CASE WHEN status IN ('Present','Late') THEN 1 ELSE 0 END) as pres, SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as lte FROM attendance_logs WHERE date = ?");
            $st->execute([$d]);
            $r = $st->fetch();
            $trendPresent[] = (int)($r['pres'] ?? 0);
            $trendLate[] = (int)($r['lte'] ?? 0);
        }

    } catch (Exception $e) {
        $dbError = $e->getMessage();
    }
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- Welcome Banner -->
<div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-slate-900 via-slate-900/90 to-slate-800 border border-slate-800 p-6 sm:p-8 mb-8">
    <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-gold-500/10 border border-gold-500/20 text-gold-400 text-xs font-medium mb-3">
                <i class="fa-solid fa-sparkles"></i>
                <span>Sapphire Luxury Management System</span>
            </div>
            <h2 class="text-2xl sm:text-3xl font-serif font-bold text-white tracking-wide">
                Good day, <?= htmlspecialchars($user['calling_name'] ?? $user['name']) ?>
            </h2>
            <p class="text-sm text-slate-400 mt-1">
                Overview for <span class="text-slate-200 font-medium"><?= date('l, F j, Y') ?></span>. All biometric sync channels are ready.
            </p>
        </div>

        <!-- Quick Action Buttons -->
        <div class="flex flex-wrap items-center gap-3">
            <a href="upload_attendance.php" class="px-4 py-2.5 rounded-xl text-xs font-semibold text-slate-950 bg-gradient-to-r from-emerald-400 to-teal-400 hover:from-emerald-300 hover:to-teal-300 shadow-lg shadow-emerald-500/20 transition-all flex items-center gap-2">
                <i class="fa-solid fa-file-excel"></i>
                <span>Upload USB Excel</span>
            </a>
            <a href="employees.php" class="px-4 py-2.5 rounded-xl text-xs font-semibold text-white bg-slate-800 hover:bg-slate-700 border border-slate-700 transition-all flex items-center gap-2">
                <i class="fa-solid fa-user-plus"></i>
                <span>Add Employee</span>
            </a>
            <a href="reports.php" class="px-4 py-2.5 rounded-xl text-xs font-semibold text-gold-400 bg-gold-500/10 hover:bg-gold-500/20 border border-gold-500/20 transition-all flex items-center gap-2">
                <i class="fa-solid fa-download"></i>
                <span>Export Report</span>
            </a>
        </div>
    </div>
</div>

<!-- KPI Cards -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-8">
    <!-- Total Staff -->
    <div class="bg-slate-900/70 border border-slate-800/80 rounded-2xl p-5 hover:border-slate-700 transition-all">
        <div class="flex items-center justify-between">
            <span class="text-xs font-medium uppercase tracking-wider text-slate-400">Total Staff</span>
            <span class="w-10 h-10 rounded-xl bg-blue-500/10 text-blue-400 flex items-center justify-center">
                <i class="fa-solid fa-users text-sm"></i>
            </span>
        </div>
        <div class="mt-3">
            <span class="text-2xl sm:text-3xl font-bold text-white"><?= $totalEmployees ?></span>
            <span class="text-xs text-slate-400 ml-2">Active</span>
        </div>
    </div>

    <!-- Present Today -->
    <div class="bg-slate-900/70 border border-slate-800/80 rounded-2xl p-5 hover:border-slate-700 transition-all">
        <div class="flex items-center justify-between">
            <span class="text-xs font-medium uppercase tracking-wider text-slate-400">Present Today</span>
            <span class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center">
                <i class="fa-solid fa-user-check text-sm"></i>
            </span>
        </div>
        <div class="mt-3">
            <span class="text-2xl sm:text-3xl font-bold text-emerald-400"><?= $todayPresent ?></span>
            <span class="text-xs text-slate-400 ml-2">Attended</span>
        </div>
    </div>

    <!-- Late Today -->
    <div class="bg-slate-900/70 border border-slate-800/80 rounded-2xl p-5 hover:border-slate-700 transition-all">
        <div class="flex items-center justify-between">
            <span class="text-xs font-medium uppercase tracking-wider text-slate-400">Late Arrivals</span>
            <span class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center">
                <i class="fa-solid fa-clock text-sm"></i>
            </span>
        </div>
        <div class="mt-3">
            <span class="text-2xl sm:text-3xl font-bold text-amber-400"><?= $todayLate ?></span>
            <span class="text-xs text-slate-400 ml-2">After Grace</span>
        </div>
    </div>

    <!-- Pending Approvals -->
    <div class="bg-slate-900/70 border border-slate-800/80 rounded-2xl p-5 hover:border-slate-700 transition-all">
        <div class="flex items-center justify-between">
            <span class="text-xs font-medium uppercase tracking-wider text-slate-400">Pending Actions</span>
            <span class="w-10 h-10 rounded-xl bg-rose-500/10 text-rose-400 flex items-center justify-center">
                <i class="fa-solid fa-bell text-sm"></i>
            </span>
        </div>
        <div class="mt-3 flex items-center gap-3">
            <div>
                <a href="leaves.php" class="text-lg font-bold text-rose-400 hover:underline"><?= $pendingLeaves ?> Leaves</a>
            </div>
            <span class="text-slate-600">|</span>
            <div>
                <a href="manual_punches.php" class="text-lg font-bold text-amber-400 hover:underline"><?= $pendingPunches ?> Punches</a>
            </div>
        </div>
    </div>
</div>

<!-- TODAY'S LIVE ROLL CALL: 2 COMPACT TABS (PRESENT VS ABSENT) -->
<div class="bg-slate-900/70 border border-slate-800/80 rounded-2xl overflow-hidden mb-8 shadow-xl">
    <div class="p-4 sm:p-5 border-b border-slate-800/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h3 class="font-serif font-bold text-base text-white flex items-center gap-2">
                <i class="fa-solid fa-clipboard-user text-gold-400"></i>
                <span>Today's Live Staff Status (<?= date('d M Y') ?>)</span>
            </h3>
            <p class="text-[11px] text-slate-400 mt-0.5">Switch tabs to quickly view who is present today and who is absent / on leave</p>
        </div>

        <!-- Compact 2-Tab Switcher -->
        <div class="inline-flex p-1 bg-slate-950 border border-slate-800 rounded-xl self-start sm:self-auto">
            <button type="button" id="tabBtnPresent" onclick="switchTodayTab('present')"
                class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition-all flex items-center gap-2 bg-emerald-500/20 text-emerald-300 border border-emerald-500/40">
                <i class="fa-solid fa-user-check text-[11px]"></i>
                <span>Present Today</span>
                <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono font-bold bg-emerald-500 text-slate-950"><?= count($todayPresentList ?? []) ?></span>
            </button>
            <button type="button" id="tabBtnAbsent" onclick="switchTodayTab('absent')"
                class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition-all flex items-center gap-2 text-slate-400 hover:text-rose-300 border border-transparent">
                <i class="fa-solid fa-user-xmark text-[11px]"></i>
                <span>Absent / Not In</span>
                <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono font-bold bg-rose-500/20 text-rose-300"><?= count($todayAbsentList ?? []) ?></span>
            </button>
        </div>
    </div>

    <!-- TAB PANEL 1: PRESENT TODAY -->
    <div id="tabPanelPresent" class="p-4">
        <?php if (empty($todayPresentList)): ?>
            <div class="py-6 text-center text-xs text-slate-500">
                <i class="fa-regular fa-clock text-lg mb-1.5 block text-slate-600"></i>
                No staff punches recorded yet for today (<?= date('d M Y') ?>). Upload today's USB Excel report to refresh.
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                <?php foreach ($todayPresentList as $pRow): ?>
                    <div class="p-3 rounded-xl bg-slate-950/70 border border-emerald-500/20 hover:border-emerald-500/40 transition-colors flex items-center justify-between gap-2">
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5">
                                <span class="text-[10px] font-mono font-bold text-gold-400">#<?= htmlspecialchars($pRow['machine_user_id']) ?></span>
                                <span class="text-xs font-semibold text-white truncate"><?= htmlspecialchars($pRow['name']) ?></span>
                            </div>
                            <div class="text-[10px] text-slate-400 mt-0.5 font-mono">
                                In: <span class="text-emerald-400 font-semibold"><?= format_time_12h($pRow['check_in']) ?></span>
                                <?php if (!empty($pRow['check_out']) && $pRow['check_out'] !== '00:00:00'): ?>
                                    &bull; Out: <span class="text-slate-200"><?= format_time_12h($pRow['check_out']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="shrink-0 text-right">
                            <?php if (($pRow['late_minutes'] ?? 0) > 0): ?>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-500/15 text-amber-300 border border-amber-500/30 block">
                                    Late +<?= format_minutes($pRow['late_minutes']) ?>
                                </span>
                            <?php else: ?>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/15 text-emerald-300 border border-emerald-500/30 block">
                                    On Time
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- TAB PANEL 2: ABSENT / NOT IN TODAY -->
    <div id="tabPanelAbsent" class="p-4 hidden">
        <?php if (empty($todayAbsentList)): ?>
            <div class="py-6 text-center text-xs text-emerald-400">
                <i class="fa-solid fa-circle-check text-lg mb-1.5 block"></i>
                100% Attendance! All active staff members are present today.
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                <?php foreach ($todayAbsentList as $aRow): ?>
                    <?php $hasLeave = !empty($aRow['approved_leave']) || $aRow['att_status'] === 'On Leave'; ?>
                    <div class="p-3 rounded-xl bg-slate-950/70 border <?= $hasLeave ? 'border-purple-500/25' : 'border-rose-500/25' ?> flex items-center justify-between gap-2">
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5">
                                <span class="text-[10px] font-mono font-bold text-slate-400">#<?= htmlspecialchars($aRow['machine_user_id']) ?></span>
                                <span class="text-xs font-semibold text-white truncate"><?= htmlspecialchars($aRow['name']) ?></span>
                            </div>
                            <div class="text-[10px] text-slate-400 mt-0.5 truncate">
                                <?= htmlspecialchars($aRow['designation'] ?: $aRow['department']) ?> &bull; Shift <?= substr($aRow['shift_start'] ?? '09:00', 0, 5) ?>
                            </div>
                        </div>
                        <div class="shrink-0">
                            <?php if (!empty($aRow['approved_leave'])): ?>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-purple-500/15 text-purple-300 border border-purple-500/30">
                                    <?= htmlspecialchars($aRow['approved_leave']['leave_type']) ?> Leave
                                </span>
                            <?php elseif ($aRow['att_status'] === 'On Leave'): ?>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-purple-500/15 text-purple-300 border border-purple-500/30">
                                    On Leave
                                </span>
                            <?php else: ?>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-rose-500/15 text-rose-300 border border-rose-500/30">
                                    Absent
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Charts & Highlights Row -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    <!-- Attendance Trend Chart (2 Cols) -->
    <div class="lg:col-span-2 bg-slate-900/70 border border-slate-800/80 rounded-2xl p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-serif font-bold text-base text-white">7-Day Attendance Trend</h3>
            <span class="text-xs text-slate-400">Present vs Late</span>
        </div>
        <div class="h-64">
            <canvas id="trendChart"></canvas>
        </div>
    </div>

    <!-- Quick Sync / Biometric Status (1 Col) -->
    <div class="bg-slate-900/70 border border-slate-800/80 rounded-2xl p-6 flex flex-col justify-between">
        <div>
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-serif font-bold text-base text-white">Biometric Machine</h3>
                <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">VFT Ready</span>
            </div>
            <p class="text-xs text-slate-400 leading-relaxed mb-4">
                Connect the machine via USB pen drive or direct office Wi-Fi / LAN to sync punches anytime.
            </p>
            <div class="space-y-3">
                <div class="p-3 bg-slate-950/60 rounded-xl border border-slate-800 flex items-center justify-between text-xs">
                    <span class="text-slate-400">Default Morning Shift</span>
                    <span class="font-mono text-gold-400 font-semibold">09:00 AM - 06:00 PM</span>
                </div>
                <div class="p-3 bg-slate-950/60 rounded-xl border border-slate-800 flex items-center justify-between text-xs">
                    <span class="text-slate-400">Grace Allowed</span>
                    <span class="font-mono text-slate-200">15 minutes</span>
                </div>
                <div class="p-3 bg-slate-950/60 rounded-xl border border-slate-800 flex items-center justify-between text-xs">
                    <span class="text-slate-400">Maternity Allowance</span>
                    <span class="font-mono text-purple-400">84 Working Days</span>
                </div>
            </div>
        </div>

        <div class="pt-5 mt-4 border-t border-slate-800/80">
            <a href="upload_attendance.php" class="w-full py-2.5 px-4 rounded-xl text-xs font-semibold text-slate-950 bg-gold-400 hover:bg-gold-500 flex items-center justify-center gap-2 transition-all">
                <i class="fa-solid fa-cloud-arrow-up"></i>
                <span>Import 1_StandardReport.xlsx</span>
            </a>
        </div>
    </div>
</div>

<!-- Today's Attendance Table -->
<div class="bg-slate-900/70 border border-slate-800/80 rounded-2xl overflow-hidden mb-8">
    <div class="p-5 border-b border-slate-800/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h3 class="font-serif font-bold text-base text-white">Today's Staff Attendance (<?= date('d M Y') ?>)</h3>
            <p class="text-xs text-slate-400 mt-0.5">Real-time check-in, check-out, and late calculations</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="attendance.php" class="text-xs text-gold-400 hover:text-gold-300 font-medium">View Full Calendar &rarr;</a>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs text-slate-300">
            <thead class="bg-slate-950/70 text-[11px] font-semibold uppercase tracking-wider text-slate-400 border-b border-slate-800/80">
                <tr>
                    <th class="py-3 px-4">ID</th>
                    <th class="py-3 px-4">Employee</th>
                    <th class="py-3 px-4">Department</th>
                    <th class="py-3 px-4">Check In</th>
                    <th class="py-3 px-4">Check Out</th>
                    <th class="py-3 px-4">Hours</th>
                    <th class="py-3 px-4">Late</th>
                    <th class="py-3 px-4">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
                <?php if (empty($todayLogs)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-8 text-slate-500">
                            No active employees found. Please seed or add employees.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($todayLogs as $row): ?>
                        <tr class="hover:bg-slate-800/30 transition-colors">
                            <td class="py-3 px-4 font-mono font-medium text-slate-400">#<?= htmlspecialchars($row['machine_user_id']) ?></td>
                            <td class="py-3 px-4">
                                <div class="font-medium text-white"><?= htmlspecialchars($row['name']) ?></div>
                                <div class="text-[10px] text-slate-500"><?= htmlspecialchars($row['calling_name'] ?? '') ?></div>
                            </td>
                            <td class="py-3 px-4 text-slate-400"><?= htmlspecialchars($row['department']) ?></td>
                            <td class="py-3 px-4 font-mono text-emerald-400 font-medium">
                                <?= format_time_12h($row['check_in']) ?>
                            </td>
                            <td class="py-3 px-4 font-mono text-slate-300">
                                <?= format_time_12h($row['check_out']) ?>
                            </td>
                            <td class="py-3 px-4 font-mono">
                                <?= $row['work_hours'] > 0 ? number_format($row['work_hours'], 1) . ' hrs' : '-' ?>
                            </td>
                            <td class="py-3 px-4">
                                <?php if ($row['late_minutes'] > 0): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                        +<?= format_minutes($row['late_minutes']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-slate-600">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4">
                                <?php
                                $badgeClass = match($row['att_status']) {
                                    'Present' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                                    'Late' => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
                                    'Half Day' => 'bg-blue-500/10 text-blue-400 border-blue-500/20',
                                    'On Leave' => 'bg-purple-500/10 text-purple-400 border-purple-500/20',
                                    default => 'bg-slate-800 text-slate-400 border-slate-700'
                                };
                                ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-medium border <?= $badgeClass ?>">
                                    <?= htmlspecialchars($row['att_status']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Chart & Tab Switcher Script -->
<script>
    function switchTodayTab(tab) {
        const pPanel = document.getElementById('tabPanelPresent');
        const aPanel = document.getElementById('tabPanelAbsent');
        const pBtn = document.getElementById('tabBtnPresent');
        const aBtn = document.getElementById('tabBtnAbsent');
        sessionStorage.setItem('sapphire_dash_tab', tab);

        if (tab === 'present') {
            pPanel.classList.remove('hidden');
            aPanel.classList.add('hidden');
            pBtn.className = 'px-3.5 py-1.5 rounded-lg text-xs font-semibold transition-all flex items-center gap-2 bg-emerald-500/20 text-emerald-300 border border-emerald-500/40';
            aBtn.className = 'px-3.5 py-1.5 rounded-lg text-xs font-semibold transition-all flex items-center gap-2 text-slate-400 hover:text-rose-300 border border-transparent';
        } else {
            aPanel.classList.remove('hidden');
            pPanel.classList.add('hidden');
            aBtn.className = 'px-3.5 py-1.5 rounded-lg text-xs font-semibold transition-all flex items-center gap-2 bg-rose-500/20 text-rose-300 border border-rose-500/40';
            pBtn.className = 'px-3.5 py-1.5 rounded-lg text-xs font-semibold transition-all flex items-center gap-2 text-slate-400 hover:text-emerald-300 border border-transparent';
        }
    }

    // Restore selected Present/Absent tab after 5-second live refresh
    document.addEventListener('DOMContentLoaded', () => {
        const savedTab = sessionStorage.getItem('sapphire_dash_tab');
        if (savedTab === 'absent') switchTodayTab('absent');
    });

    const ctx = document.getElementById('trendChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($trendDates) ?>,
            datasets: [
                {
                    label: 'Present Staff',
                    data: <?= json_encode($trendPresent) ?>,
                    backgroundColor: 'rgba(16, 185, 129, 0.7)',
                    borderColor: '#10b981',
                    borderWidth: 1,
                    borderRadius: 6,
                },
                {
                    label: 'Late Arrivals',
                    data: <?= json_encode($trendLate) ?>,
                    backgroundColor: 'rgba(245, 158, 11, 0.7)',
                    borderColor: '#f59e0b',
                    borderWidth: 1,
                    borderRadius: 6,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: { color: '#94a3b8', font: { family: 'Plus Jakarta Sans', size: 11 } }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(51, 65, 85, 0.4)' },
                    ticks: { color: '#64748b' }
                },
                x: {
                    grid: { display: false },
                    ticks: { color: '#64748b' }
                }
            }
        }
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
