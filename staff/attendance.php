<?php
/**
 * Sapphire Aesthetic & Luxury - Daily & Monthly Attendance Records
 */
require_once __DIR__ . '/config/auth.php';
require_login();

$user = current_user();
if (!is_admin_or_manager($user)) {
    header('Location: employee_portal.php');
    exit;
}

$pageTitle = 'Daily Attendance Records';

$selectedDate = $_GET['date'] ?? date('Y-m-d');
$selectedDept = $_GET['department'] ?? '';
$selectedStatus = $_GET['status'] ?? '';

// Build Query
$sql = "
    SELECT a.*, e.machine_user_id, e.name as emp_name, e.calling_name, e.department, e.gender, e.shift_start, e.shift_end
    FROM attendance_logs a
    JOIN employees e ON a.employee_id = e.id
    WHERE a.date = :sel_date
";
$params = [':sel_date' => $selectedDate];

if (!empty($selectedDept)) {
    $sql .= " AND e.department = :dept";
    $params[':dept'] = $selectedDept;
}
if (!empty($selectedStatus)) {
    $sql .= " AND a.status = :stat";
    $params[':stat'] = $selectedStatus;
}
$sql .= " ORDER BY e.machine_user_id ASC";

$logs = [];
$departments = [];
if (isset($pdo)) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $logs = $stmt->fetchAll();

        // Get distinct departments
        $departments = $pdo->query("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL AND department != '' ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="space-y-6">
    <!-- Header with Filters -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl sm:text-2xl font-serif font-bold text-white">Daily Attendance Log</h2>
            <p class="text-xs text-slate-400 mt-1">Review check-in, check-out, late penalties, and OT for any given day</p>
        </div>

        <!-- Filter Form -->
        <form method="GET" action="attendance.php" class="flex flex-wrap items-center gap-3 bg-slate-900/80 p-2 rounded-2xl border border-slate-800">
            <div>
                <input type="date" name="date" value="<?= htmlspecialchars($selectedDate) ?>"
                    class="px-3 py-1.5 bg-slate-950 border border-slate-700 rounded-xl text-xs text-slate-100 font-mono focus:border-gold-500 focus:outline-none">
            </div>

            <div>
                <select name="department" class="px-3 py-1.5 bg-slate-950 border border-slate-700 rounded-xl text-xs text-slate-100 focus:border-gold-500 focus:outline-none">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= htmlspecialchars($dept) ?>" <?= $selectedDept === $dept ? 'selected' : '' ?>><?= htmlspecialchars($dept) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <select name="status" class="px-3 py-1.5 bg-slate-950 border border-slate-700 rounded-xl text-xs text-slate-100 focus:border-gold-500 focus:outline-none">
                    <option value="">All Statuses</option>
                    <option value="Present" <?= $selectedStatus === 'Present' ? 'selected' : '' ?>>Present</option>
                    <option value="Late" <?= $selectedStatus === 'Late' ? 'selected' : '' ?>>Late</option>
                    <option value="Absent" <?= $selectedStatus === 'Absent' ? 'selected' : '' ?>>Absent</option>
                    <option value="On Leave" <?= $selectedStatus === 'On Leave' ? 'selected' : '' ?>>On Leave</option>
                </select>
            </div>

            <button type="submit" class="px-3 py-1.5 bg-gold-400 hover:bg-gold-500 text-slate-950 font-semibold rounded-xl text-xs transition-colors">
                <i class="fa-solid fa-filter mr-1"></i> Filter
            </button>
        </form>
    </div>

    <!-- Attendance Table Card -->
    <div class="bg-slate-900/70 border border-slate-800/80 rounded-2xl overflow-hidden shadow-xl">
        <div class="p-4 border-b border-slate-800 flex items-center justify-between text-xs text-slate-400">
            <span>Showing records for <strong class="text-white"><?= date('l, F j, Y', strtotime($selectedDate)) ?></strong></span>
            <span>Total: <strong class="text-gold-400"><?= count($logs) ?> entries</strong></span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-300">
                <thead class="bg-slate-950/70 text-[11px] font-semibold uppercase tracking-wider text-slate-400 border-b border-slate-800">
                    <tr>
                        <th class="py-3.5 px-4">ID</th>
                        <th class="py-3.5 px-4">Staff Member</th>
                        <th class="py-3.5 px-4">Department</th>
                        <th class="py-3.5 px-4">Check In</th>
                        <th class="py-3.5 px-4">Check Out</th>
                        <th class="py-3.5 px-4">Work Hours</th>
                        <th class="py-3.5 px-4">Late Arrival</th>
                        <th class="py-3.5 px-4">Overtime</th>
                        <th class="py-3.5 px-4">Status</th>
                        <th class="py-3.5 px-4">Source</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 font-mono text-[11px]">
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="10" class="text-center py-10 text-slate-500 font-sans text-xs">
                                No attendance logs recorded for <?= htmlspecialchars($selectedDate) ?>. 
                                <a href="upload_attendance.php" class="text-gold-400 hover:underline ml-1">Upload USB Excel here &rarr;</a>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $row): ?>
                            <tr class="hover:bg-slate-800/40 transition-colors">
                                <td class="py-3.5 px-4 font-bold text-gold-400">#<?= htmlspecialchars($row['machine_user_id']) ?></td>
                                <td class="py-3.5 px-4 font-sans">
                                    <div class="font-medium text-white text-xs"><?= htmlspecialchars($row['emp_name']) ?></div>
                                    <div class="text-[10px] text-slate-500"><?= htmlspecialchars($row['calling_name'] ?? '') ?></div>
                                </td>
                                <td class="py-3.5 px-4 font-sans text-slate-400"><?= htmlspecialchars($row['department']) ?></td>
                                <td class="py-3.5 px-4 text-emerald-400 font-semibold">
                                    <?= format_time_12h($row['check_in']) ?>
                                </td>
                                <td class="py-3.5 px-4 text-slate-300">
                                    <?= format_time_12h($row['check_out']) ?>
                                </td>
                                <td class="py-3.5 px-4 text-white">
                                    <?= $row['work_hours'] > 0 ? number_format($row['work_hours'], 1) . ' hrs' : '-' ?>
                                </td>
                                <td class="py-3.5 px-4">
                                    <?php if ($row['late_minutes'] > 0): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-500/15 text-amber-300 border border-amber-500/30">
                                            +<?= format_minutes($row['late_minutes']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-slate-600">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 px-4">
                                    <?php if ($row['overtime_minutes'] > 0): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-indigo-500/15 text-indigo-300 border border-indigo-500/30">
                                            +<?= format_minutes($row['overtime_minutes']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-slate-600">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 px-4 font-sans">
                                    <?php
                                    $badge = match($row['status']) {
                                        'Present' => 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30',
                                        'Late' => 'bg-amber-500/15 text-amber-400 border-amber-500/30',
                                        'Half Day' => 'bg-blue-500/15 text-blue-400 border-blue-500/30',
                                        'On Leave' => 'bg-purple-500/15 text-purple-400 border-purple-500/30',
                                        default => 'bg-slate-800 text-slate-400 border-slate-700'
                                    };
                                    ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium border <?= $badge ?>">
                                        <?= htmlspecialchars($row['status']) ?>
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 font-sans text-[10px] text-slate-400">
                                    <?= htmlspecialchars($row['source']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
