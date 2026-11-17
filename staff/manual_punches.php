<?php
/**
 * Sapphire Aesthetic & Luxury - Manual In/Out Attendance & Authorization History
 */
require_once __DIR__ . '/config/auth.php';
require_login();

$user = current_user();
$canAuthorize = can_approve_manual_punch($user);
$pageTitle = 'Manual Attendance & Authorization History';
$message = '';
$error = '';

// Handle Form Submissions (Request / Authorize / Reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($pdo)) {
    $action = $_POST['action'] ?? '';
    $punchId = (int)($_POST['punch_id'] ?? 0);

    if ($action === 'authorize' && $canAuthorize) {
        try {
            $pdo->beginTransaction();

            $pStmt = $pdo->prepare("SELECT * FROM manual_punches WHERE id = ? FOR UPDATE");
            $pStmt->execute([$punchId]);
            $punch = $pStmt->fetch();

            if ($punch && $punch['status'] === 'Pending') {
                // Update manual_punches record
                $upStmt = $pdo->prepare("
                    UPDATE manual_punches 
                    SET status = 'Approved', authorized_by = ?, authorized_at = NOW() 
                    WHERE id = ?
                ");
                $upStmt->execute([$user['id'], $punchId]);

                // Calculate hours & update attendance_logs
                $empId = $punch['employee_id'];
                $dateStr = $punch['date'];
                $checkIn = $punch['requested_in'];
                $checkOut = $punch['requested_out'];

                $empInfo = $pdo->query("SELECT machine_user_id, shift_start, shift_end, grace_period_mins FROM employees WHERE id = " . (int)$empId)->fetch();
                $mUserId = $empInfo['machine_user_id'] ?? 0;

                $workHours = 0.00;
                $lateMins = 0;
                $earlyMins = 0;
                $otMins = 0;
                $status = 'Present';

                $shiftStart = strtotime($dateStr . ' ' . ($empInfo['shift_start'] ?? '09:00:00'));
                $graceStart = strtotime($dateStr . ' ' . date('H:i:s', $shiftStart + (($empInfo['grace_period_mins'] ?? 15) * 60)));
                $shiftEnd   = strtotime($dateStr . ' ' . ($empInfo['shift_end'] ?? '18:00:00'));

                if ($checkIn && $checkOut) {
                    $inT = strtotime($dateStr . ' ' . $checkIn);
                    $outT = strtotime($dateStr . ' ' . $checkOut);
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
                } elseif ($checkIn) {
                    $inT = strtotime($dateStr . ' ' . $checkIn);
                    if ($inT > $graceStart) {
                        $lateMins = round(($inT - $shiftStart) / 60);
                        $status = 'Late';
                    }
                }

                $rawPunches = trim(($checkIn ? substr($checkIn, 0, 5) : '') . ',' . ($checkOut ? substr($checkOut, 0, 5) : ''), ',');

                $attStmt = $pdo->prepare("
                    INSERT INTO attendance_logs 
                    (employee_id, machine_user_id, date, check_in, check_out, work_hours, late_minutes, early_leave_minutes, overtime_minutes, status, source, raw_punches, remarks)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Manual', ?, ?)
                    ON DUPLICATE KEY UPDATE 
                    check_in = VALUES(check_in), 
                    check_out = VALUES(check_out),
                    work_hours = VALUES(work_hours),
                    late_minutes = VALUES(late_minutes),
                    early_leave_minutes = VALUES(early_leave_minutes),
                    overtime_minutes = VALUES(overtime_minutes),
                    status = VALUES(status),
                    source = 'Manual',
                    remarks = VALUES(remarks)
                ");

                $remarks = "Manual Punch authorized by " . ($user['calling_name'] ?? $user['name']);
                $attStmt->execute([
                    $empId, $mUserId, $dateStr, $checkIn, $checkOut, $workHours, $lateMins, $earlyMins, $otMins, $status, $rawPunches, $remarks
                ]);

                $pdo->commit();
                $message = "Manual attendance #{$punchId} authorized and logged successfully!";
            } else {
                $pdo->rollBack();
                $error = "Record is not pending.";
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    } elseif ($action === 'reject' && $canAuthorize) {
        $reason = trim($_POST['remarks'] ?? 'Rejected by management');
        try {
            $stmt = $pdo->prepare("UPDATE manual_punches SET status = 'Rejected', authorized_by = ?, authorized_at = NOW(), remarks = ? WHERE id = ?");
            $stmt->execute([$user['id'], $reason, $punchId]);
            $message = "Manual attendance #{$punchId} has been rejected.";
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    } elseif ($action === 'request') {
        $empId = (int)($_POST['employee_id'] ?? $user['id']);
        $pDate = $_POST['date'] ?? date('Y-m-d');
        $timeIn = !empty($_POST['requested_in']) ? $_POST['requested_in'] . ':00' : null;
        $timeOut = !empty($_POST['requested_out']) ? $_POST['requested_out'] . ':00' : null;
        $reason = trim($_POST['reason'] ?? '');

        try {
            $ins = $pdo->prepare("
                INSERT INTO manual_punches (employee_id, date, requested_in, requested_out, reason, status, requested_by)
                VALUES (?, ?, ?, ?, ?, 'Pending', ?)
            ");
            $ins->execute([$empId, $pDate, $timeIn, $timeOut, $reason, $user['id']]);
            $message = "Manual punch request submitted for authorization.";
        } catch (Exception $e) {
            $error = "Failed to submit request: " . $e->getMessage();
        }
    }
}

// Fetch pending and all records
$pendingPunches = [];
$historyPunches = [];
$staffList = [];

if (isset($pdo)) {
    try {
        $pendingPunches = $pdo->query("
            SELECT m.*, e.name as emp_name, e.calling_name, e.machine_user_id, e.department,
                   r.name as requester_name
            FROM manual_punches m
            JOIN employees e ON m.employee_id = e.id
            JOIN employees r ON m.requested_by = r.id
            WHERE m.status = 'Pending'
            ORDER BY m.requested_at ASC
        ")->fetchAll();

        $historyPunches = $pdo->query("
            SELECT m.*, e.name as emp_name, e.calling_name, e.machine_user_id, e.department,
                   r.name as requester_name, a.name as authorizer_name
            FROM manual_punches m
            JOIN employees e ON m.employee_id = e.id
            JOIN employees r ON m.requested_by = r.id
            LEFT JOIN employees a ON m.authorized_by = a.id
            ORDER BY m.requested_at DESC
            LIMIT 50
        ")->fetchAll();

        $staffList = $pdo->query("SELECT id, name, machine_user_id, department FROM employees WHERE status = 'active' ORDER BY machine_user_id ASC")->fetchAll();
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
            <h2 class="text-xl sm:text-2xl font-serif font-bold text-white">Manual In/Out & Audit History</h2>
            <p class="text-xs text-slate-400 mt-1">Submit missed punches, authorize manual adjustments, and maintain full audit history</p>
        </div>
        <button onclick="openPunchModal()" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-semibold text-slate-950 bg-amber-400 hover:bg-amber-300 shadow-lg shadow-amber-500/20 transition-all">
            <i class="fa-solid fa-clock-rotate-left"></i>
            <span>Request Manual Punch</span>
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

    <!-- PENDING AUTHORIZATION QUEUE -->
    <?php if ($canAuthorize): ?>
        <div class="bg-slate-900/70 border border-amber-500/30 rounded-2xl p-6 shadow-xl space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-lg bg-amber-500/10 text-amber-400 flex items-center justify-center text-sm">
                        <i class="fa-solid fa-fingerprint"></i>
                    </span>
                    <div>
                        <h3 class="font-serif font-bold text-base text-white">Pending Manual Punch Authorizations</h3>
                        <p class="text-xs text-slate-400">Review employee missed check-in/out requests</p>
                    </div>
                </div>
                <span class="px-2.5 py-1 text-xs font-bold bg-amber-500 text-slate-950 rounded-full">
                    <?= count($pendingPunches) ?> Pending
                </span>
            </div>

            <?php if (empty($pendingPunches)): ?>
                <div class="py-6 text-center text-xs text-slate-500">
                    <i class="fa-solid fa-check-circle text-emerald-400 text-base mb-1 block"></i>
                    No manual punches waiting for authorization.
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php foreach ($pendingPunches as $pp): ?>
                        <div class="p-4 rounded-xl bg-slate-950/70 border border-slate-800 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <div class="font-medium text-white text-sm">
                                        <?= htmlspecialchars($pp['emp_name']) ?>
                                        <span class="text-xs text-slate-500 font-mono ml-1">#<?= $pp['machine_user_id'] ?></span>
                                    </div>
                                    <span class="text-xs font-mono text-slate-400"><?= $pp['date'] ?></span>
                                </div>

                                <div class="grid grid-cols-2 gap-2 my-2.5 p-2.5 rounded-lg bg-slate-900 border border-slate-800 font-mono text-xs">
                                    <div>
                                        <span class="text-[10px] text-slate-500 uppercase block">Check-In</span>
                                        <span class="text-emerald-400 font-bold"><?= format_time_12h($pp['requested_in']) ?></span>
                                    </div>
                                    <div>
                                        <span class="text-[10px] text-slate-500 uppercase block">Check-Out</span>
                                        <span class="text-slate-300 font-bold"><?= format_time_12h($pp['requested_out']) ?></span>
                                    </div>
                                </div>

                                <p class="text-xs text-slate-400 italic mb-3">
                                    "<?= htmlspecialchars($pp['reason']) ?>"
                                </p>
                                <div class="text-[10px] text-slate-500">
                                    Requested by: <?= htmlspecialchars($pp['requester_name']) ?> on <?= date('d M, h:i A', strtotime($pp['requested_at'])) ?>
                                </div>
                            </div>

                            <div class="flex items-center justify-end gap-2 pt-3 mt-3 border-t border-slate-800/80">
                                <form method="POST" action="manual_punches.php" onsubmit="return confirm('Reject this manual punch?');">
                                    <input type="hidden" name="action" value="reject">
                                    <input type="hidden" name="punch_id" value="<?= $pp['id'] ?>">
                                    <button type="submit" class="px-3 py-1.5 rounded-lg text-xs font-medium text-rose-400 hover:bg-rose-500/10 border border-rose-500/20">
                                        Reject
                                    </button>
                                </form>
                                <form method="POST" action="manual_punches.php" onsubmit="return confirm('Authorize this manual attendance punch? Logs will be updated.');">
                                    <input type="hidden" name="action" value="authorize">
                                    <input type="hidden" name="punch_id" value="<?= $pp['id'] ?>">
                                    <button type="submit" class="px-4 py-1.5 rounded-lg text-xs font-semibold text-slate-950 bg-emerald-400 hover:bg-emerald-300 shadow-lg shadow-emerald-500/20 flex items-center gap-1.5">
                                        <i class="fa-solid fa-check"></i>
                                        <span>Authorize & Log</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- COMPLETE AUDIT TRAIL / HISTORY TABLE -->
    <div class="bg-slate-900/70 border border-slate-800/80 rounded-2xl overflow-hidden shadow-xl">
        <div class="p-5 border-b border-slate-800 flex items-center justify-between">
            <div>
                <h3 class="font-serif font-bold text-base text-white">Manual Punch Audit Trail & History</h3>
                <p class="text-xs text-slate-400 mt-0.5">Permanent record of who requested and who authorized manual punches</p>
            </div>
            <span class="text-xs text-slate-400"><?= count($historyPunches) ?> entries</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-300">
                <thead class="bg-slate-950/70 text-[11px] font-semibold uppercase tracking-wider text-slate-400 border-b border-slate-800">
                    <tr>
                        <th class="py-3 px-4">Staff Member</th>
                        <th class="py-3 px-4">Date</th>
                        <th class="py-3 px-4">Req. In</th>
                        <th class="py-3 px-4">Req. Out</th>
                        <th class="py-3 px-4">Reason / Notes</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4">Authorized By</th>
                        <th class="py-3 px-4">Authorized At</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 font-mono text-[11px]">
                    <?php if (empty($historyPunches)): ?>
                        <tr><td colspan="8" class="text-center py-8 text-slate-500 font-sans">No manual punch history recorded yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($historyPunches as $hp): ?>
                            <tr class="hover:bg-slate-800/30 transition-colors">
                                <td class="py-3 px-4 font-sans">
                                    <div class="font-medium text-white"><?= htmlspecialchars($hp['emp_name']) ?></div>
                                    <div class="text-[10px] text-slate-500 font-mono">#<?= $hp['machine_user_id'] ?></div>
                                </td>
                                <td class="py-3 px-4 text-slate-300"><?= $hp['date'] ?></td>
                                <td class="py-3 px-4 text-emerald-400"><?= format_time_12h($hp['requested_in']) ?></td>
                                <td class="py-3 px-4 text-slate-300"><?= format_time_12h($hp['requested_out']) ?></td>
                                <td class="py-3 px-4 font-sans text-slate-400 max-w-xs truncate" title="<?= htmlspecialchars($hp['reason']) ?>">
                                    <?= htmlspecialchars($hp['reason']) ?>
                                </td>
                                <td class="py-3 px-4 font-sans">
                                    <?php
                                    $bClass = match($hp['status']) {
                                        'Approved' => 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30',
                                        'Rejected' => 'bg-rose-500/15 text-rose-400 border-rose-500/30',
                                        default => 'bg-amber-500/15 text-amber-400 border-amber-500/30'
                                    };
                                    ?>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-medium border <?= $bClass ?>">
                                        <?= htmlspecialchars($hp['status']) ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 font-sans text-slate-300">
                                    <?= htmlspecialchars($hp['authorizer_name'] ?? ($hp['status'] === 'Approved' ? 'Management' : '-')) ?>
                                </td>
                                <td class="py-3 px-4 text-slate-500 text-[10px]">
                                    <?= $hp['authorized_at'] ? date('d M Y, h:i A', strtotime($hp['authorized_at'])) : '-' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Request Manual Punch -->
<div id="punchModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm hidden overflow-y-auto">
    <div class="relative w-full max-w-lg bg-slate-900 border border-slate-800 rounded-2xl p-6 sm:p-8 shadow-2xl">
        <div class="flex items-center justify-between pb-4 border-b border-slate-800 mb-6">
            <h3 class="text-lg font-serif font-bold text-white">Request Manual Attendance Punch</h3>
            <button onclick="closePunchModal()" class="text-slate-400 hover:text-white p-2">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="manual_punches.php" class="space-y-4">
            <input type="hidden" name="action" value="request">

            <!-- Employee -->
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                    Employee *
                </label>
                <select name="employee_id" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-100 focus:border-gold-500 focus:outline-none">
                    <?php foreach ($staffList as $st): ?>
                        <option value="<?= $st['id'] ?>" <?= $st['id'] == $user['id'] ? 'selected' : '' ?>>
                            #<?= $st['machine_user_id'] ?> - <?= htmlspecialchars($st['name']) ?> (<?= $st['department'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Date -->
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                    Attendance Date *
                </label>
                <input type="date" name="date" required value="<?= date('Y-m-d') ?>"
                    class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-100 font-mono focus:border-gold-500 focus:outline-none">
            </div>

            <!-- Times In / Out -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                        Time In
                    </label>
                    <input type="time" name="requested_in" value="09:00"
                        class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-100 font-mono focus:border-gold-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                        Time Out
                    </label>
                    <input type="time" name="requested_out" value="18:00"
                        class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-100 font-mono focus:border-gold-500 focus:outline-none">
                </div>
            </div>

            <!-- Reason -->
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                    Reason for Manual Punch *
                </label>
                <textarea name="reason" rows="3" required placeholder="e.g. Fingerprint scan failed, power outage, offsite client treatment..."
                    class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-100 focus:border-gold-500 focus:outline-none"></textarea>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-800">
                <button type="button" onclick="closePunchModal()" class="px-4 py-2.5 rounded-xl text-xs font-medium text-slate-400 hover:bg-slate-800">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2.5 rounded-xl text-xs font-semibold text-slate-950 bg-amber-400 hover:bg-amber-300">
                    Submit for Authorization
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const punchModal = document.getElementById('punchModal');
    function openPunchModal() { punchModal.classList.remove('hidden'); }
    function closePunchModal() { punchModal.classList.add('hidden'); }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
