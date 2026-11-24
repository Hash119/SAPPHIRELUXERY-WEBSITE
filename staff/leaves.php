<?php
/**
 * Sapphire Aesthetic & Luxury - Leave Management & Approvals
 */
require_once __DIR__ . '/config/auth.php';
require_login();

$user = current_user();
$canApprove = can_approve_leave($user);
$pageTitle = 'Leave Management & Approvals';
$message = '';
$error = '';

// Handle Approval / Rejection Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($pdo)) {
    $action = $_POST['action'] ?? '';
    $leaveId = (int)($_POST['leave_id'] ?? 0);

    if ($action === 'approve' && $canApprove) {
        try {
            $pdo->beginTransaction();

            // Fetch leave details
            $lStmt = $pdo->prepare("SELECT * FROM leaves WHERE id = ? FOR UPDATE");
            $lStmt->execute([$leaveId]);
            $leave = $lStmt->fetch();

            if ($leave && $leave['status'] === 'Pending') {
                // Update leave status
                $upStmt = $pdo->prepare("
                    UPDATE leaves 
                    SET status = 'Approved', approved_by = ?, approved_at = NOW() 
                    WHERE id = ?
                ");
                $upStmt->execute([$user['id'], $leaveId]);

                // Ensure leave_balances row exists & quotas are synced before deducting used days
                $year = (int)date('Y', strtotime($leave['start_date']));
                $leaveMonth = (int)date('m', strtotime($leave['start_date']));
                if (function_exists('sync_employee_leave_balances')) {
                    sync_employee_leave_balances($pdo, $leave['employee_id'], $year, $leaveMonth);
                }

                // Deduct from leave balance
                $col = match($leave['leave_type']) {
                    'Annual' => 'annual_used',
                    'Casual' => 'casual_used',
                    'Medical' => 'medical_used',
                    'Maternity' => 'maternity_used',
                    'Monthly' => 'monthly_used',
                    default => null
                };

                if ($col) {
                    $balStmt = $pdo->prepare("
                        UPDATE leave_balances 
                        SET {$col} = {$col} + ? 
                        WHERE employee_id = ? AND year = ?
                    ");
                    $balStmt->execute([$leave['total_days'], $leave['employee_id'], $year]);
                }

                // Update attendance logs for those dates
                $cur = strtotime($leave['start_date']);
                $end = strtotime($leave['end_date']);
                $empMachineId = $pdo->query("SELECT machine_user_id FROM employees WHERE id = " . (int)$leave['employee_id'])->fetchColumn();
                $isHalfDayLeave = ((float)$leave['total_days'] < 1);

                if ($isHalfDayLeave) {
                    // For half-day leaves, preserve any existing punch times; only insert Half Day if absent
                    $attLeaveStmt = $pdo->prepare("
                        INSERT INTO attendance_logs (employee_id, machine_user_id, date, status, source, remarks)
                        VALUES (?, ?, ?, 'Half Day', 'Manual', ?)
                        ON DUPLICATE KEY UPDATE remarks = CONCAT(COALESCE(remarks, ''), IF(remarks IS NULL OR remarks = '', '', ' • '), VALUES(remarks))
                    ");
                    $attLeaveStmt->execute([$leave['employee_id'], $empMachineId, $leave['start_date'], 'Half-Day ' . $leave['leave_type'] . ' Leave']);
                } else {
                    $attLeaveStmt = $pdo->prepare("
                        INSERT INTO attendance_logs (employee_id, machine_user_id, date, status, source, remarks)
                        VALUES (?, ?, ?, 'On Leave', 'Manual', ?)
                        ON DUPLICATE KEY UPDATE status = 'On Leave', remarks = VALUES(remarks)
                    ");

                    while ($cur <= $end) {
                        $dStr = date('Y-m-d', $cur);
                        $attLeaveStmt->execute([$leave['employee_id'], $empMachineId, $dStr, $leave['leave_type'] . ' Leave']);
                        $cur = strtotime('+1 day', $cur);
                    }
                }

                $pdo->commit();
                $message = "Leave #{$leaveId} approved successfully!";
            } else {
                $pdo->rollBack();
                $error = "Leave request is already processed or invalid.";
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = "Failed to approve: " . $e->getMessage();
        }
    } elseif ($action === 'reject' && $canApprove) {
        $reason = trim($_POST['rejection_reason'] ?? 'Not approved by management.');
        try {
            $stmt = $pdo->prepare("
                UPDATE leaves 
                SET status = 'Rejected', approved_by = ?, approved_at = NOW(), rejection_reason = ? 
                WHERE id = ?
            ");
            $stmt->execute([$user['id'], $reason, $leaveId]);
            $message = "Leave #{$leaveId} has been rejected.";
        } catch (Exception $e) {
            $error = "Failed to reject: " . $e->getMessage();
        }
    } elseif ($action === 'apply') {
        // Apply leave
        $empId = (int)($_POST['employee_id'] ?? $user['id']);
        $type = $_POST['leave_type'] ?? 'Casual';
        $duration = $_POST['duration'] ?? 'full';
        $start = $_POST['start_date'] ?? date('Y-m-d');
        $end = $_POST['end_date'] ?? $start;
        $reason = trim($_POST['reason'] ?? '');
        
        $startT = strtotime($start);
        $endT = strtotime($end);
        if ($duration === 'half' || $type === 'Half Day' || $type === 'Short Leave') {
            $days = 0.5;
            $end = $start;
        } else {
            $days = max(1, round(($endT - $startT) / 86400) + 1);
        }

        try {
            // Check dynamic leave entitlement & accrual rules
            $empFetch = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
            $empFetch->execute([$empId]);
            $empData = $empFetch->fetch();

            if ($empData) {
                $applyYear = (int)date('Y', $startT);
                $applyMonth = (int)date('m', $startT);
                $accrual = calculate_employee_annual_leave_accrual($empData, $applyYear, $applyMonth);
                sync_employee_leave_balances($pdo, $empId, $applyYear, $applyMonth);

                $balStmt = $pdo->prepare("SELECT * FROM leave_balances WHERE employee_id = ? AND year = ?");
                $balStmt->execute([$empId, $applyYear]);
                $empBal = $balStmt->fetch() ?: [
                    'annual_total' => $accrual['annual_total'], 'annual_used' => 0.0,
                    'casual_total' => $accrual['casual_total'], 'casual_used' => 0.0
                ];

                if ($type === 'Casual' && $accrual['casual_total'] <= 0) {
                    throw new Exception("{$empData['name']} is not yet eligible for Casual Leave. Casual leaves unlock on {$accrual['unlock_date']} (after 1-year appointment date).");
                }
                if ($type === 'Annual' && $accrual['annual_total'] <= 0) {
                    throw new Exception("{$empData['name']} is not yet eligible for Annual Leave. {$accrual['description']}");
                }
                if ($type === 'Annual') {
                    $annRem = max(0, (float)$empBal['annual_total'] - (float)$empBal['annual_used']);
                    if ($days > $annRem) {
                        throw new Exception("{$empData['name']} has only {$annRem} Annual day(s) remaining (Accrued/Entitlement: {$empBal['annual_total']}, Used: {$empBal['annual_used']}). Requested: {$days} day(s).");
                    }
                }
                if ($type === 'Casual') {
                    $casRem = max(0, (float)$empBal['casual_total'] - (float)$empBal['casual_used']);
                    if ($days > $casRem) {
                        throw new Exception("{$empData['name']} has only {$casRem} Casual day(s) remaining (Entitlement: {$empBal['casual_total']}, Used: {$empBal['casual_used']}). Requested: {$days} day(s).");
                    }
                }
            }

            if ($type === 'Monthly') {
                $monthStr = date('Y-m', $startT);
                $mlStmt = $pdo->prepare("SELECT COALESCE(SUM(total_days), 0) FROM leaves WHERE employee_id = ? AND leave_type = 'Monthly' AND status != 'Rejected' AND DATE_FORMAT(start_date, '%Y-%m') = ?");
                $mlStmt->execute([$empId, $monthStr]);
                $usedInMonth = (float)$mlStmt->fetchColumn();
                if ($usedInMonth + $days > 4) {
                    $reason .= " [Note: Exceeds 4 monthly leaves quota: " . ($usedInMonth + $days) . "/4 for " . date('M Y', $startT) . "]";
                }
            }

            $ins = $pdo->prepare("
                INSERT INTO leaves (employee_id, leave_type, start_date, end_date, total_days, reason, status)
                VALUES (?, ?, ?, ?, ?, ?, 'Pending')
            ");
            $ins->execute([$empId, $type, $start, $end, $days, $reason]);
            $message = "Leave application ({$type} - {$days} day" . ($days == 1 ? '' : 's') . ") submitted successfully for review.";
        } catch (Exception $e) {
            $error = "Failed to submit leave: " . $e->getMessage();
        }
    }
}

// Fetch Pending Leaves
$pendingLeaves = [];
$allLeaves = [];
$employeesList = [];

if (isset($pdo)) {
    try {
        $pStmt = $pdo->query("
            SELECT l.*, e.name as emp_name, e.calling_name, e.department, e.gender, e.machine_user_id, e.maternity_eligible
            FROM leaves l
            JOIN employees e ON l.employee_id = e.id
            WHERE l.status = 'Pending'
            ORDER BY l.applied_at ASC
        ");
        $pendingLeaves = $pStmt->fetchAll();

        $allStmt = $pdo->query("
            SELECT l.*, e.name as emp_name, e.calling_name, e.department, e.gender, e.machine_user_id,
                   a.name as approver_name
            FROM leaves l
            JOIN employees e ON l.employee_id = e.id
            LEFT JOIN employees a ON l.approved_by = a.id
            ORDER BY l.applied_at DESC
            LIMIT 50
        ");
        $allLeaves = $allStmt->fetchAll();

        $employeesList = $pdo->query("SELECT id, name, calling_name, machine_user_id, gender, maternity_eligible FROM employees WHERE status = 'active' ORDER BY machine_user_id ASC")->fetchAll();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl sm:text-2xl font-serif font-bold text-white">Leave Management & Approvals</h2>
            <p class="text-xs text-slate-400 mt-1">Review leave applications, authorize maternity leave, and track balances</p>
        </div>
        <button onclick="openApplyModal()" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-semibold text-slate-950 bg-gold-400 hover:bg-gold-500 shadow-lg shadow-gold-500/20 transition-all">
            <i class="fa-solid fa-plus"></i>
            <span>Submit Leave Request</span>
        </button>
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

    <!-- PENDING APPROVALS QUEUE (FOR AUTHORIZERS) -->
    <?php if ($canApprove): ?>
        <div class="bg-slate-900/70 border border-amber-500/30 rounded-2xl p-6 shadow-xl space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-lg bg-amber-500/10 text-amber-400 flex items-center justify-center text-sm">
                        <i class="fa-solid fa-bell"></i>
                    </span>
                    <div>
                        <h3 class="font-serif font-bold text-base text-white">Pending Leave Approvals Required</h3>
                        <p class="text-xs text-slate-400">Applications waiting for your review & approval</p>
                    </div>
                </div>
                <span class="px-2.5 py-1 text-xs font-bold bg-amber-500 text-slate-950 rounded-full">
                    <?= count($pendingLeaves) ?> Pending
                </span>
            </div>

            <?php if (empty($pendingLeaves)): ?>
                <div class="py-6 text-center text-xs text-slate-500">
                    <i class="fa-solid fa-circle-check text-emerald-400 text-base mb-1 block"></i>
                    All caught up! No pending leave requests at this moment.
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php foreach ($pendingLeaves as $pl): ?>
                        <div class="p-4 rounded-xl bg-slate-950/70 border border-slate-800 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <div class="font-medium text-white text-sm">
                                        <?= htmlspecialchars($pl['emp_name']) ?>
                                        <span class="text-xs text-slate-500 font-mono ml-1">#<?= $pl['machine_user_id'] ?></span>
                                    </div>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-semibold <?= $pl['leave_type'] === 'Maternity' ? 'bg-purple-500/20 text-purple-300 border border-purple-500/30' : 'bg-gold-500/10 text-gold-400 border border-gold-500/20' ?>">
                                        <?= htmlspecialchars($pl['leave_type']) ?> Leave
                                    </span>
                                </div>
                                <div class="text-xs text-slate-300 font-mono flex items-center gap-2 mb-2">
                                    <i class="fa-regular fa-calendar text-slate-500"></i>
                                    <span><?= $pl['start_date'] ?> to <?= $pl['end_date'] ?></span>
                                    <span class="text-slate-500">(<?= $pl['total_days'] ?> days)</span>
                                </div>
                                <p class="text-xs text-slate-400 bg-slate-900 p-2.5 rounded-lg border border-slate-800/80 mb-3 italic">
                                    "<?= htmlspecialchars($pl['reason']) ?>"
                                </p>
                            </div>

                            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-800/80">
                                <form method="POST" action="leaves.php" onsubmit="return confirm('Reject this leave request?');">
                                    <input type="hidden" name="action" value="reject">
                                    <input type="hidden" name="leave_id" value="<?= $pl['id'] ?>">
                                    <button type="submit" class="px-3 py-1.5 rounded-lg text-xs font-medium text-rose-400 hover:bg-rose-500/10 border border-rose-500/20 transition-colors">
                                        Reject
                                    </button>
                                </form>
                                <form method="POST" action="leaves.php" onsubmit="return confirm('Approve this leave request? Attendance will be updated automatically.');">
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="leave_id" value="<?= $pl['id'] ?>">
                                    <button type="submit" class="px-4 py-1.5 rounded-lg text-xs font-semibold text-slate-950 bg-emerald-400 hover:bg-emerald-300 shadow-lg shadow-emerald-500/20 transition-colors flex items-center gap-1.5">
                                        <i class="fa-solid fa-check"></i>
                                        <span>Approve</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- ALL LEAVES HISTORY TABLE -->
    <div class="bg-slate-900/70 border border-slate-800/80 rounded-2xl overflow-hidden shadow-xl">
        <div class="p-5 border-b border-slate-800 flex items-center justify-between">
            <h3 class="font-serif font-bold text-base text-white">All Leaves History</h3>
            <span class="text-xs text-slate-400"><?= count($allLeaves) ?> total records</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-300">
                <thead class="bg-slate-950/70 text-[11px] font-semibold uppercase tracking-wider text-slate-400 border-b border-slate-800">
                    <tr>
                        <th class="py-3 px-4">Staff Member</th>
                        <th class="py-3 px-4">Leave Type</th>
                        <th class="py-3 px-4">Dates</th>
                        <th class="py-3 px-4">Days</th>
                        <th class="py-3 px-4">Reason</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4">Authorized By</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 font-mono text-[11px]">
                    <?php if (empty($allLeaves)): ?>
                        <tr><td colspan="7" class="text-center py-8 text-slate-500 font-sans">No leave requests found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($allLeaves as $l): ?>
                            <tr class="hover:bg-slate-800/30 transition-colors">
                                <td class="py-3 px-4 font-sans">
                                    <div class="font-medium text-white"><?= htmlspecialchars($l['emp_name']) ?></div>
                                    <div class="text-[10px] text-slate-500 font-mono">#<?= $l['machine_user_id'] ?> &bull; <?= $l['department'] ?></div>
                                </td>
                                <td class="py-3 px-4 font-sans">
                                    <span class="inline-flex items-center gap-1 font-medium <?= $l['leave_type'] === 'Maternity' ? 'text-purple-300' : 'text-slate-200' ?>">
                                        <?= $l['leave_type'] === 'Maternity' ? '<i class="fa-solid fa-baby text-[10px]"></i>' : '' ?>
                                        <?= htmlspecialchars($l['leave_type']) ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-slate-300"><?= $l['start_date'] ?> &rarr; <?= $l['end_date'] ?></td>
                                <td class="py-3 px-4 text-white font-bold"><?= $l['total_days'] ?></td>
                                <td class="py-3 px-4 font-sans text-slate-400 max-w-xs truncate" title="<?= htmlspecialchars($l['reason']) ?>">
                                    <?= htmlspecialchars($l['reason']) ?>
                                </td>
                                <td class="py-3 px-4 font-sans">
                                    <?php
                                    $stBadge = match($l['status']) {
                                        'Approved' => 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30',
                                        'Rejected' => 'bg-rose-500/15 text-rose-400 border-rose-500/30',
                                        default => 'bg-amber-500/15 text-amber-400 border-amber-500/30'
                                    };
                                    ?>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-medium border <?= $stBadge ?>">
                                        <?= htmlspecialchars($l['status']) ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 font-sans text-slate-400">
                                    <?= htmlspecialchars($l['approver_name'] ?? ($l['status'] === 'Approved' ? 'Management' : '-')) ?>
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
            <h3 class="text-lg font-serif font-bold text-white">Submit Leave Request</h3>
            <button onclick="closeApplyModal()" class="text-slate-400 hover:text-white p-2">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="leaves.php" class="space-y-4">
            <input type="hidden" name="action" value="apply">

            <!-- Employee Selector -->
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                    Select Employee *
                </label>
                <select name="employee_id" id="sel_employee" onchange="updateMaternityAvailability()" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-100 focus:border-gold-500 focus:outline-none">
                    <?php foreach ($employeesList as $em): ?>
                        <option value="<?= $em['id'] ?>" data-gender="<?= $em['gender'] ?>" data-maternity="<?= $em['maternity_eligible'] ?>" <?= $em['id'] == $user['id'] ? 'selected' : '' ?>>
                            #<?= $em['machine_user_id'] ?> - <?= htmlspecialchars($em['name']) ?> (<?= $em['gender'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Leave Type -->
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                    Leave Type *
                </label>
                <select name="leave_type" id="sel_leave_type" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-100 focus:border-gold-500 focus:outline-none">
                    <option value="Monthly" class="text-gold-300 font-semibold">Monthly Leave (4 Days / Month)</option>
                    <option value="Casual">Casual Leave (7 Days - Confirmed Staff)</option>
                    <option value="Annual">Annual Leave (14 Days - Confirmed Staff)</option>
                    <option value="Medical">Medical / Sick Leave</option>
                    <option value="Maternity" id="opt_maternity" class="text-purple-300 font-semibold">Maternity Leave (84 Days - Ladies Only)</option>
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
                        <input type="radio" name="duration" value="full" checked onchange="toggleAdminDuration()" class="text-gold-400 focus:ring-gold-500">
                        <div>
                            <span class="text-xs font-semibold text-white block">Full Day(s)</span>
                            <span class="text-[10px] text-slate-400">1.0 day per date</span>
                        </div>
                    </label>
                    <label class="flex items-center gap-2 p-2.5 rounded-xl bg-slate-950 border border-slate-800 cursor-pointer hover:border-gold-500/50 transition-colors">
                        <input type="radio" name="duration" value="half" onchange="toggleAdminDuration()" class="text-gold-400 focus:ring-gold-500">
                        <div>
                            <span class="text-xs font-semibold text-amber-300 block">Half Day (0.5 Day)</span>
                            <span class="text-[10px] text-slate-400">Deducts 0.5 day</span>
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
                    <input type="date" name="start_date" id="adm_start_date" onchange="syncAdminEndDate()" required value="<?= date('Y-m-d') ?>"
                        class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-100 font-mono focus:border-gold-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                        End Date *
                    </label>
                    <input type="date" name="end_date" id="adm_end_date" required value="<?= date('Y-m-d') ?>"
                        class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-100 font-mono focus:border-gold-500 focus:outline-none">
                </div>
            </div>

            <!-- Reason -->
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                    Reason / Remarks *
                </label>
                <textarea name="reason" rows="3" required placeholder="State the reason for leave..."
                    class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-100 focus:border-gold-500 focus:outline-none"></textarea>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-800">
                <button type="button" onclick="closeApplyModal()" class="px-4 py-2.5 rounded-xl text-xs font-medium text-slate-400 hover:bg-slate-800">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2.5 rounded-xl text-xs font-semibold text-slate-950 bg-gold-400 hover:bg-gold-500">
                    Submit Request
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const applyModal = document.getElementById('applyModal');
    const selEmp = document.getElementById('sel_employee');
    const optMat = document.getElementById('opt_maternity');

    function updateMaternityAvailability() {
        const selected = selEmp.options[selEmp.selectedIndex];
        const gender = selected.getAttribute('data-gender');
        const mat = selected.getAttribute('data-maternity');

        if (gender === 'Female' && mat == '1') {
            optMat.disabled = false;
            optMat.classList.remove('hidden');
        } else {
            optMat.disabled = true;
            optMat.classList.add('hidden');
            if (document.getElementById('sel_leave_type').value === 'Maternity') {
                document.getElementById('sel_leave_type').value = 'Casual';
            }
        }
    }

    function openApplyModal() {
        updateMaternityAvailability();
        applyModal.classList.remove('hidden');
    }
    function closeApplyModal() {
        applyModal.classList.add('hidden');
    }
    function toggleAdminDuration() {
        const isHalf = document.querySelector('#applyModal input[name="duration"]:checked')?.value === 'half';
        const sIn = document.getElementById('adm_start_date');
        const eIn = document.getElementById('adm_end_date');
        if (isHalf && sIn && eIn) {
            eIn.value = sIn.value;
            eIn.readOnly = true;
            eIn.classList.add('opacity-60');
        } else if (eIn) {
            eIn.readOnly = false;
            eIn.classList.remove('opacity-60');
        }
    }
    function syncAdminEndDate() {
        const isHalf = document.querySelector('#applyModal input[name="duration"]:checked')?.value === 'half';
        const sIn = document.getElementById('adm_start_date');
        const eIn = document.getElementById('adm_end_date');
        if (sIn && eIn && (isHalf || eIn.value < sIn.value)) {
            eIn.value = sIn.value;
        }
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
