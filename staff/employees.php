<?php
/**
 * Sapphire Aesthetic & Luxury - Employee Management & Permission Directory
 */
require_once __DIR__ . '/config/auth.php';
require_login();

$user = current_user();
if (!is_admin_or_manager($user)) {
    header('Location: employee_portal.php');
    exit;
}

$isAdmin = ($user['role'] === 'admin');
$pageTitle = 'Employee Directory & Permissions';
$message = '';
$error = '';

// Handle Form Submissions & Fast Toggles
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($pdo)) {
    $action = $_POST['action'] ?? '';

    // 1. Quick Toggle Active / Inactive Status (Admin only)
    if ($action === 'toggle_status' && $isAdmin) {
        $empId = (int)($_POST['emp_id'] ?? 0);
        $newStatus = ($_POST['new_status'] === 'active') ? 'active' : 'inactive';
        try {
            $stmt = $pdo->prepare("UPDATE employees SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $empId]);
            $empName = $pdo->query("SELECT name FROM employees WHERE id = {$empId}")->fetchColumn() ?: 'Employee';
            if ($newStatus === 'inactive') {
                $message = "🚫 Account for {$empName} has been Terminated / Deactivated. Portal login access is now blocked!";
            } else {
                $message = "✅ Account for {$empName} has been Activated. Login access restored.";
            }
        } catch (Exception $e) {
            $error = "Failed to update status: " . $e->getMessage();
        }
    }

    // 1.1 Quick Reset Password to 123456 (Admin only)
    elseif ($action === 'reset_password' && $isAdmin) {
        $empId = (int)($_POST['emp_id'] ?? 0);
        try {
            $defaultHash = password_hash('123456', PASSWORD_DEFAULT);
            try {
                $stmt = $pdo->prepare("UPDATE employees SET password = ?, plain_token = '123456' WHERE id = ?");
                $stmt->execute([$defaultHash, $empId]);
            } catch (Exception $e2) {
                $stmt = $pdo->prepare("UPDATE employees SET password = ? WHERE id = ?");
                $stmt->execute([$defaultHash, $empId]);
            }
            $empName = $pdo->query("SELECT name FROM employees WHERE id = {$empId}")->fetchColumn() ?: 'Employee';
            $message = "🔑 Password for {$empName} has been reset to default '123456'! On next login, they will be prompted to create their own secret password.";
        } catch (Exception $e) {
            $error = "Failed to reset password: " . $e->getMessage();
        }
    }

    // 1.2 Quick Confirm Employee (Admin only - completes probation & unlocks 14 Annual + 7 Casual leaves)
    elseif ($action === 'confirm_employee' && $isAdmin) {
        $empId = (int)($_POST['emp_id'] ?? 0);
        try {
            $stmt = $pdo->prepare("UPDATE employees SET employment_type = 'confirmed' WHERE id = ?");
            $stmt->execute([$empId]);
            
            // Sync leave balances for current year (unlocks 14 Annual + 7 Casual while preserving already-used accrued leaves)
            if (function_exists('sync_employee_leave_balances')) {
                sync_employee_leave_balances($pdo, $empId, (int)date('Y'), (int)date('m'));
            } else {
                $curYear = (int)date('Y');
                $balStmt = $pdo->prepare("
                    UPDATE leave_balances 
                    SET annual_total = 14.0, casual_total = 7.0 
                    WHERE employee_id = ? AND year = ?
                ");
                $balStmt->execute([$empId, $curYear]);
            }
            
            $empName = $pdo->query("SELECT name FROM employees WHERE id = {$empId}")->fetchColumn() ?: 'Employee';
            $message = "🎉 {$empName} has been officially Confirmed! Full 14 Annual & 7 Casual leaves unlocked (balanced against any leaves already taken).";
        } catch (Exception $e) {
            $error = "Failed to confirm employee: " . $e->getMessage();
        }
    }

    // 2. Quick Toggle Maternity Leave Option (Ladies only)
    elseif ($action === 'toggle_maternity' && $isAdmin) {
        $empId = (int)($_POST['emp_id'] ?? 0);
        $enable = (int)($_POST['enable'] ?? 0);
        try {
            $stmt = $pdo->prepare("UPDATE employees SET maternity_eligible = ? WHERE id = ? AND gender = 'Female'");
            $stmt->execute([$enable, $empId]);

            // Update leave balances quota
            $year = (int)date('Y');
            $quota = $enable ? 84.0 : 0.0;
            $balStmt = $pdo->prepare("
                INSERT INTO leave_balances (employee_id, year, maternity_total)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE maternity_total = VALUES(maternity_total)
            ");
            $balStmt->execute([$empId, $year, $quota]);

            $empName = $pdo->query("SELECT name FROM employees WHERE id = {$empId}")->fetchColumn();
            if ($enable) {
                $message = "🌸 Maternity Leave permission turned ON for {$empName}! She can now apply for maternity leave (84 days) from her portal.";
            } else {
                $message = "Maternity Leave option turned OFF for {$empName}.";
            }
        } catch (Exception $e) {
            $error = "Failed to update maternity permission: " . $e->getMessage();
        }
    }

    // 3. Create or Update Employee
    elseif ($action === 'create' || $action === 'update') {
        $machineId = (int)($_POST['machine_user_id'] ?? 0);
        $empNo = trim($_POST['emp_no'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $callingName = trim($_POST['calling_name'] ?? '');

        // Build email from editable prefix + locked domain suffix (default: @sapphire)
        $emailDomain = trim($_POST['email_domain'] ?? '@sapphire');
        if (empty($emailDomain) || $emailDomain[0] !== '@') {
            $emailDomain = '@sapphire';
        }
        $emailPrefixRaw = trim($_POST['email_prefix'] ?? '');
        if ($emailPrefixRaw !== '') {
            // Strip any accidental @domain pasted into the prefix box and sanitize
            $emailPrefix = strtolower(preg_replace('/@.*$/', '', $emailPrefixRaw));
            $emailPrefix = preg_replace('/[^a-z0-9._-]/', '', $emailPrefix);
            $email = ($emailPrefix !== '') ? ($emailPrefix . $emailDomain) : null;
        } else {
            // Fallback auto-generate from calling_name or first word of full name
            $baseName = !empty($callingName) ? $callingName : $name;
            $firstWord = preg_split('/\s+/', trim($baseName))[0] ?? '';
            $autoPrefix = strtolower(preg_replace('/[^a-z0-9._-]/', '', $firstWord));
            $email = ($autoPrefix !== '') ? ($autoPrefix . '@sapphire') : null;
        }

        $phone = trim($_POST['phone'] ?? '');
        $gender = $_POST['gender'] ?? 'Female';
        $department = trim($_POST['department'] ?? 'Sapphire');
        $designation = trim($_POST['designation'] ?? 'Staff');
        $joinDate = !empty($_POST['join_date']) ? $_POST['join_date'] : date('Y-m-d');
        $appointmentDate = !empty($_POST['appointment_date']) ? $_POST['appointment_date'] : null;
        $shiftStart = $_POST['shift_start'] ?? '09:00:00';
        $shiftEnd = $_POST['shift_end'] ?? '18:00:00';
        $graceMins = (int)($_POST['grace_period_mins'] ?? 15);
        $maternityEligible = ($gender === 'Female' && isset($_POST['maternity_eligible'])) ? 1 : 0;
        $role = $_POST['role'] ?? 'employee';
        $status = $_POST['status'] ?? 'active';
        $employmentType = ($_POST['employment_type'] ?? 'confirmed') === 'probation' ? 'probation' : 'confirmed';
        $probationMonths = (int)($_POST['probation_months'] ?? 6);
        if ($probationMonths !== 12) $probationMonths = 6;
        $probationEndDate = ($employmentType === 'probation') ? date('Y-m-d', strtotime("+{$probationMonths} months", strtotime($joinDate))) : null;

        // Admin Custom Leave Entitlement Override (optional)
        $customLeaveEnabled = ($isAdmin && isset($_POST['custom_leave_enabled'])) ? 1 : 0;
        $customAnnualQuota  = ($customLeaveEnabled && $_POST['custom_annual_quota']  !== '') ? max(0, (float)$_POST['custom_annual_quota'])  : 14.0;
        $customCasualQuota  = ($customLeaveEnabled && $_POST['custom_casual_quota']  !== '') ? max(0, (float)$_POST['custom_casual_quota'])  : 7.0;
        $customMonthlyQuota = ($customLeaveEnabled && $_POST['custom_monthly_quota'] !== '') ? max(0, (float)$_POST['custom_monthly_quota']) : 4.0;
        $customMedicalQuota = ($customLeaveEnabled && $_POST['custom_medical_quota'] !== '') ? max(0, (float)$_POST['custom_medical_quota']) : 7.0;

        $canApproveLeave = isset($_POST['can_approve_leave']) ? 1 : 0;
        $canApprovePunch = isset($_POST['can_approve_manual_punch']) ? 1 : 0;
        $password = trim($_POST['password'] ?? '');

        if (empty($empNo)) {
            $empNo = str_pad($machineId, 4, '0', STR_PAD_LEFT);
        }

        if ($action === 'create') {
            try {
                $plainPw = !empty($password) ? $password : '123456';
                $hash = password_hash($plainPw, PASSWORD_DEFAULT);
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO employees 
                        (machine_user_id, emp_no, name, calling_name, email, phone, gender, department, designation, employment_type, probation_months, probation_end_date, join_date, appointment_date, custom_leave_enabled, custom_annual_quota, custom_casual_quota, custom_monthly_quota, custom_medical_quota, shift_start, shift_end, grace_period_mins, maternity_eligible, role, can_approve_leave, can_approve_manual_punch, password, plain_token, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $machineId, $empNo, $name, $callingName, $email, $phone, $gender, $department, $designation, $employmentType, $probationMonths, $probationEndDate, $joinDate, $appointmentDate, $customLeaveEnabled, $customAnnualQuota, $customCasualQuota, $customMonthlyQuota, $customMedicalQuota, $shiftStart, $shiftEnd, $graceMins, $maternityEligible, $role, $canApproveLeave, $canApprovePunch, $hash, $plainPw, $status
                    ]);
                } catch (Exception $eCol) {
                    $stmt = $pdo->prepare("
                        INSERT INTO employees 
                        (machine_user_id, emp_no, name, calling_name, email, phone, gender, department, designation, employment_type, probation_months, probation_end_date, join_date, shift_start, shift_end, grace_period_mins, maternity_eligible, role, can_approve_leave, can_approve_manual_punch, password, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $machineId, $empNo, $name, $callingName, $email, $phone, $gender, $department, $designation, $employmentType, $probationMonths, $probationEndDate, $joinDate, $shiftStart, $shiftEnd, $graceMins, $maternityEligible, $role, $canApproveLeave, $canApprovePunch, $hash, $status
                    ]);
                }
                $newId = $pdo->lastInsertId();

                // Sync dynamic leave balances based on join_date, appointment_date, employment_type or custom override
                if (function_exists('sync_employee_leave_balances')) {
                    sync_employee_leave_balances($pdo, $newId, (int)date('Y'), (int)date('m'));
                }

                $emailNote = $email ? " | Email: {$email}" : "";
                $message = "Employee {$name} (#{$machineId}) registered successfully as " . ($customLeaveEnabled ? "Custom Leave Quota" : ($employmentType === 'probation' ? "Probation ({$probationMonths} Months)" : "Confirmed")) . "{$emailNote}!";
            } catch (Exception $e) {
                $error = "Failed to add employee: " . $e->getMessage();
            }
        } elseif ($action === 'update') {
            $empId = (int)($_POST['emp_id'] ?? 0);
            try {
                $sql = "UPDATE employees SET 
                        machine_user_id = ?, emp_no = ?, name = ?, calling_name = ?, email = ?, phone = ?, 
                        gender = ?, department = ?, designation = ?, employment_type = ?, probation_months = ?, 
                        probation_end_date = ?, join_date = ?, appointment_date = ?,
                        custom_leave_enabled = ?, custom_annual_quota = ?, custom_casual_quota = ?, custom_monthly_quota = ?, custom_medical_quota = ?,
                        shift_start = ?, shift_end = ?, grace_period_mins = ?, maternity_eligible = ?, role = ?, status = ?, can_approve_leave = ?, 
                        can_approve_manual_punch = ?";
                $params = [
                    $machineId, $empNo, $name, $callingName, $email, $phone, 
                    $gender, $department, $designation, $employmentType, $probationMonths, 
                    $probationEndDate, $joinDate, $appointmentDate,
                    $customLeaveEnabled, $customAnnualQuota, $customCasualQuota, $customMonthlyQuota, $customMedicalQuota,
                    $shiftStart, $shiftEnd, $graceMins, $maternityEligible, $role, $status, $canApproveLeave, $canApprovePunch
                ];

                if (!empty($password)) {
                    $sql .= ", password = ?, plain_token = ?";
                    $params[] = password_hash($password, PASSWORD_DEFAULT);
                    $params[] = $password;
                }
                $sql .= " WHERE id = ?";
                $params[] = $empId;

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

                // Sync dynamic leave balances according to employment type, appointment date, custom override & maternity
                if (function_exists('sync_employee_leave_balances')) {
                    sync_employee_leave_balances($pdo, $empId, (int)date('Y'), (int)date('m'));
                }

                // If Admin also adjusted current year's used leaves (e.g., opening balance from paper records)
                if ($isAdmin && isset($_POST['adjust_used_leaves']) && $_POST['adjust_used_leaves'] === '1') {
                    $adjAnnUsed = max(0, (float)($_POST['adj_annual_used'] ?? 0));
                    $adjCasUsed = max(0, (float)($_POST['adj_casual_used'] ?? 0));
                    $adjMedUsed = max(0, (float)($_POST['adj_medical_used'] ?? 0));
                    $adjStmt = $pdo->prepare("
                        UPDATE leave_balances
                        SET annual_used = ?, casual_used = ?, medical_used = ?
                        WHERE employee_id = ? AND year = ?
                    ");
                    $adjStmt->execute([$adjAnnUsed, $adjCasUsed, $adjMedUsed, $empId, (int)date('Y')]);
                }

                $message = "Employee details updated successfully (" . ($customLeaveEnabled ? "Custom Leave Quota" : ucfirst($employmentType)) . ")!";
            } catch (Exception $e) {
                $error = "Failed to update employee: " . $e->getMessage();
            }
        }
    }
}

// Filter Status
$statusFilter = $_GET['filter_status'] ?? 'all';

// Fetch all employees
$employees = [];
$nextMachineId = 1;
$nextEmpNo = 'EMP001';

if (isset($pdo)) {
    try {
        $curYr = (int)date('Y');
        $q = "SELECT e.*,
                     COALESCE(lb.annual_total, 14.0) AS bal_annual_total,
                     COALESCE(lb.annual_used, 0.0)   AS bal_annual_used,
                     COALESCE(lb.casual_total, 7.0)  AS bal_casual_total,
                     COALESCE(lb.casual_used, 0.0)   AS bal_casual_used,
                     COALESCE(lb.medical_total, 7.0) AS bal_medical_total,
                     COALESCE(lb.medical_used, 0.0)  AS bal_medical_used
              FROM employees e
              LEFT JOIN leave_balances lb ON lb.employee_id = e.id AND lb.year = {$curYr}";
        if ($statusFilter === 'active') $q .= " WHERE e.status = 'active'";
        elseif ($statusFilter === 'inactive') $q .= " WHERE e.status = 'inactive'";
        $q .= " ORDER BY e.status ASC, e.machine_user_id ASC";
        
        $employees = $pdo->query($q)->fetchAll();
        
        $maxM = $pdo->query("SELECT MAX(machine_user_id) FROM employees WHERE machine_user_id < 900")->fetchColumn();
        $nextMachineId = ($maxM ? (int)$maxM + 1 : 1);
        $nextEmpNo = sprintf('EMP%03d', $nextMachineId);
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="space-y-6">
    <!-- Header with Add Button & Filter -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl sm:text-2xl font-serif font-bold text-white">Employee Directory & Permissions</h2>
            <p class="text-xs text-slate-400 mt-1">Manage staff biometric IDs, active/inactive status, and activate maternity leave for ladies</p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <!-- Filter by Status -->
            <form method="GET" action="employees.php" class="flex items-center gap-2">
                <select name="filter_status" onchange="this.form.submit()" class="px-3 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-gold-500">
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Staff (<?= count($employees) ?>)</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active Only</option>
                    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive Only</option>
                </select>
            </form>

            <button onclick="openNewModal()" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-semibold text-slate-950 bg-gradient-to-r from-gold-500 to-amber-400 hover:from-gold-400 hover:to-amber-300 shadow-lg shadow-gold-500/20 transition-all">
                <i class="fa-solid fa-user-plus"></i>
                <span>Register New Employee</span>
            </button>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if ($message): ?>
        <div class="p-3.5 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-xs flex items-center gap-2">
            <i class="fa-solid fa-circle-check shrink-0"></i>
            <span><?= htmlspecialchars($message) ?></span>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="p-3.5 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs flex items-center gap-2">
            <i class="fa-solid fa-triangle-exclamation shrink-0"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- Employee Table Card -->
    <div class="bg-slate-900/70 border border-slate-800/80 rounded-2xl overflow-hidden shadow-xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-300">
                <thead class="bg-slate-950/70 text-[11px] font-semibold uppercase tracking-wider text-slate-400 border-b border-slate-800">
                    <tr>
                        <th class="py-3.5 px-4">Machine ID</th>
                        <th class="py-3.5 px-4">Employee</th>
                        <th class="py-3.5 px-4">Department / Role</th>
                        <th class="py-3.5 px-4">Shift</th>
                        <th class="py-3.5 px-4">Status (Active/Inactive)</th>
                        <th class="py-3.5 px-4">Maternity Leave Permission</th>
                        <th class="py-3.5 px-4">Leave Approver</th>
                        <th class="py-3.5 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    <?php if (empty($employees)): ?>
                        <tr><td colspan="8" class="text-center py-8 text-slate-500">No employees registered yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($employees as $emp): ?>
                            <tr class="hover:bg-slate-800/40 transition-colors <?= $emp['status'] === 'inactive' ? 'opacity-60 bg-slate-950/30' : '' ?>">
                                <td class="py-3.5 px-4 font-mono font-bold text-gold-400">#<?= htmlspecialchars($emp['machine_user_id']) ?></td>
                                <td class="py-3.5 px-4">
                                    <div class="font-medium text-white text-sm flex items-center gap-2">
                                        <?= htmlspecialchars($emp['name']) ?>
                                        <span class="text-[10px] <?= $emp['gender'] === 'Female' ? 'text-pink-400' : 'text-blue-400' ?>">
                                            <i class="fa-solid <?= $emp['gender'] === 'Female' ? 'fa-venus' : 'fa-mars' ?>"></i>
                                        </span>
                                    </div>
                                    <div class="text-[10px] text-slate-500 font-mono mt-0.5 flex items-center gap-2 flex-wrap">
                                        <span>Code: <strong><?= htmlspecialchars($emp['emp_no']) ?></strong></span>
                                        <span>&bull; <?= htmlspecialchars($emp['designation']) ?></span>
                                        <?php if (!empty($emp['email'])): ?>
                                            <span class="text-cyan-400/90"><i class="fa-regular fa-envelope text-[9px]"></i> <?= htmlspecialchars($emp['email']) ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($emp['appointment_date'])): ?>
                                            <span class="text-slate-400" title="Date of Appointment"><i class="fa-regular fa-calendar-check text-[9px] text-gold-400"></i> Appt: <?= htmlspecialchars($emp['appointment_date']) ?></span>
                                        <?php endif; ?>
                                        <?php if ($isAdmin): ?>
                                            <span class="inline-flex items-center gap-1 text-[10px] text-slate-300 font-mono bg-slate-800/90 px-1.5 py-0.5 rounded border border-slate-700" title="Emergency Password Lookup (Developer & Admin)">
                                                <i class="fa-solid fa-key text-[9px] text-gold-400"></i> PW: <strong class="text-gold-300"><?= htmlspecialchars($emp['plain_token'] ?? '123456') ?></strong>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4">
                                    <div class="text-slate-300"><?= htmlspecialchars($emp['department']) ?></div>
                                    <div class="flex items-center gap-1.5 flex-wrap mt-0.5">
                                        <span class="inline-block px-1.5 py-0.2 rounded text-[10px] uppercase font-semibold <?= $emp['role'] === 'admin' ? 'bg-amber-500/20 text-amber-300' : ($emp['role'] === 'manager' || $emp['role'] === 'supervisor' ? 'bg-indigo-500/20 text-indigo-300' : 'bg-slate-800 text-slate-400') ?>">
                                            <?= htmlspecialchars($emp['role']) ?>
                                        </span>
                                        <?php if (!empty($emp['custom_leave_enabled']) && (int)$emp['custom_leave_enabled'] === 1): ?>
                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.2 rounded text-[10px] font-semibold bg-purple-500/15 text-purple-300 border border-purple-500/30" title="Custom Admin Leave Quota: Annual <?= $emp['custom_annual_quota'] ?>, Casual <?= $emp['custom_casual_quota'] ?>, Monthly <?= $emp['custom_monthly_quota'] ?>">
                                                <i class="fa-solid fa-sliders text-[9px]"></i> Custom Leaves
                                            </span>
                                        <?php elseif (($emp['employment_type'] ?? 'confirmed') === 'probation'): ?>
                                            <?php
                                            $pMonths = (int)($emp['probation_months'] ?? 6);
                                            $jDate = $emp['join_date'] ?? date('Y-m-d');
                                            $pEnd = !empty($emp['probation_end_date']) ? $emp['probation_end_date'] : date('Y-m-d', strtotime("+{$pMonths} months", strtotime($jDate)));
                                            $today = date('Y-m-d');
                                            $isDue = ($today >= $pEnd);
                                            $daysLeft = round((strtotime($pEnd) - strtotime($today)) / 86400);
                                            ?>
                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.2 rounded text-[10px] font-semibold bg-amber-500/15 text-amber-300 border border-amber-500/30" title="Probation: <?= $pMonths ?> Months (Ends: <?= $pEnd ?>)">
                                                <i class="fa-solid fa-clock-rotate-left text-[9px]"></i> Probation <?= $pMonths ?>M (<?= $isDue ? 'Due' : "{$daysLeft}d left" ?>)
                                            </span>
                                            <?php if ($isDue && $isAdmin): ?>
                                                <form method="POST" action="employees.php" class="inline-block">
                                                    <input type="hidden" name="action" value="confirm_employee">
                                                    <input type="hidden" name="emp_id" value="<?= $emp['id'] ?>">
                                                    <button type="submit" onclick="return confirm('Confirm <?= htmlspecialchars(addslashes($emp['name'])) ?> as a Permanent Staff Member? This will immediately unlock their 14 Annual and 7 Casual leaves.');"
                                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 hover:bg-emerald-500/30 transition-colors animate-pulse" title="Probation period completed! Click to Confirm">
                                                        <i class="fa-solid fa-award text-[9px]"></i> Confirm Now
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="inline-block px-1.5 py-0.2 rounded text-[10px] font-semibold bg-sky-500/15 text-sky-300 border border-sky-500/30" title="Confirmed: 14 Annual + 7 Casual + 4 Monthly Leaves">
                                                <i class="fa-solid fa-circle-check text-[9px]"></i> Confirmed
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 font-mono text-[11px] text-slate-400">
                                    <?= substr($emp['shift_start'], 0, 5) ?> - <?= substr($emp['shift_end'], 0, 5) ?>
                                </td>

                                <!-- 1. ACTIVE / INACTIVE STATUS TOGGLE -->
                                <td class="py-3.5 px-4">
                                    <?php if ($isAdmin): ?>
                                        <form method="POST" action="employees.php" class="inline-block">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="emp_id" value="<?= $emp['id'] ?>">
                                            <?php if ($emp['status'] === 'active'): ?>
                                                <input type="hidden" name="new_status" value="inactive">
                                                <button type="submit" onclick="return confirm('Deactivate / Terminate <?= htmlspecialchars(addslashes($emp['name'])) ?>? (Portal login access will be blocked immediately).');"
                                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-500/15 text-emerald-400 border border-emerald-500/30 hover:bg-rose-500/20 hover:text-rose-300 hover:border-rose-500/30 transition-colors group" title="Click to terminate / deactivate">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 group-hover:bg-rose-400"></span>
                                                    <span>Active</span>
                                                </button>
                                            <?php else: ?>
                                                <input type="hidden" name="new_status" value="active">
                                                <button type="submit" onclick="return confirm('Re-activate <?= htmlspecialchars(addslashes($emp['name'])) ?>?');"
                                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-rose-500/20 text-rose-300 border border-rose-500/40 hover:bg-emerald-500/20 hover:text-emerald-300 transition-colors" title="Terminated / Inactive - Click to restore login access">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-rose-400"></span>
                                                    <span>Terminated / Inactive</span>
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    <?php else: ?>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold <?= $emp['status'] === 'active' ? 'bg-emerald-500/15 text-emerald-400' : 'bg-rose-500/20 text-rose-300' ?>">
                                            <?= $emp['status'] === 'active' ? 'Active' : 'Terminated / Inactive' ?>
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- 2. MATERNITY LEAVE PERMISSION TOGGLE (ON DEMAND) -->
                                <td class="py-3.5 px-4">
                                    <?php if ($emp['gender'] === 'Female'): ?>
                                        <?php if ($isAdmin): ?>
                                            <form method="POST" action="employees.php" class="inline-block">
                                                <input type="hidden" name="action" value="toggle_maternity">
                                                <input type="hidden" name="emp_id" value="<?= $emp['id'] ?>">
                                                <?php if ($emp['maternity_eligible']): ?>
                                                    <!-- Currently ON: Shows active status & Turn Off option -->
                                                    <input type="hidden" name="enable" value="0">
                                                    <div class="flex items-center gap-1.5">
                                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-purple-500/20 text-purple-300 border border-purple-500/40 shadow-sm">
                                                            <i class="fa-solid fa-baby"></i> Maternity Active (84 Days)
                                                        </span>
                                                        <button type="submit" onclick="return confirm('Turn OFF maternity option for <?= htmlspecialchars(addslashes($emp['name'])) ?>?');"
                                                            class="p-1 text-slate-500 hover:text-rose-400 text-xs" title="Turn Off Maternity Option">
                                                            <i class="fa-solid fa-power-off"></i>
                                                        </button>
                                                    </div>
                                                <?php else: ?>
                                                    <!-- Currently OFF: 1-Click button for Admin to turn it on when she requests -->
                                                    <input type="hidden" name="enable" value="1">
                                                    <button type="submit" onclick="return confirm('Activate Maternity Leave (84 Days) for <?= htmlspecialchars(addslashes($emp['name'])) ?>? She will now be able to choose Maternity Leave in her portal.');"
                                                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-medium bg-slate-800/80 hover:bg-purple-900/40 text-slate-300 hover:text-purple-300 border border-slate-700/80 hover:border-purple-500/40 transition-colors">
                                                        <i class="fa-solid fa-toggle-off text-slate-500"></i>
                                                        <span>Turn ON Maternity</span>
                                                    </button>
                                                <?php endif; ?>
                                            </form>
                                        <?php else: ?>
                                            <?php if ($emp['maternity_eligible']): ?>
                                                <span class="text-purple-300 text-[11px] font-semibold"><i class="fa-solid fa-baby"></i> Active</span>
                                            <?php else: ?>
                                                <span class="text-slate-500 text-[11px]">Normal Leaves Only</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-slate-600 text-[11px]">N/A (Male)</span>
                                    <?php endif; ?>
                                </td>

                                <td class="py-3.5 px-4">
                                    <?php if ($emp['can_approve_leave'] || $emp['role'] === 'admin'): ?>
                                        <span class="inline-flex items-center gap-1 text-[11px] font-medium text-emerald-400">
                                            <i class="fa-solid fa-check"></i> Can Approve
                                        </span>
                                    <?php else: ?>
                                        <span class="text-slate-600 text-[11px]">No</span>
                                    <?php endif; ?>
                                </td>

                                <td class="py-3.5 px-4 text-right whitespace-nowrap space-x-1">
                                    <?php if ($isAdmin && $emp['role'] !== 'admin'): ?>
                                        <form method="POST" action="employees.php" class="inline-block" onsubmit="return confirm('Reset password for <?= htmlspecialchars(addslashes($emp['name'])) ?> to default 123456?');">
                                            <input type="hidden" name="action" value="reset_password">
                                            <input type="hidden" name="emp_id" value="<?= $emp['id'] ?>">
                                            <button type="submit" class="px-2.5 py-1 rounded-lg text-xs font-medium text-amber-400 hover:bg-amber-500/15 border border-amber-500/30 transition-colors" title="1-Click Reset Password to default 123456">
                                                <i class="fa-solid fa-key"></i> Reset PW
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <button onclick='openEditModal(<?= json_encode($emp) ?>)' class="px-2.5 py-1 rounded-lg text-xs font-medium text-gold-400 hover:bg-gold-500/10 border border-gold-500/20 transition-colors">
                                        <i class="fa-solid fa-pen-to-square"></i> Edit
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Add / Edit Employee -->
<div id="employeeModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm hidden overflow-y-auto">
    <div class="relative w-full max-w-2xl bg-slate-900 border border-slate-800 rounded-2xl p-6 sm:p-8 shadow-2xl my-8">
        <div class="flex items-center justify-between pb-4 border-b border-slate-800 mb-6">
            <h3 id="modalTitle" class="text-lg font-serif font-bold text-white">Register New Employee</h3>
            <button onclick="closeModal()" class="text-slate-400 hover:text-white p-2 text-base">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="employees.php" class="space-y-4">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="emp_id" id="formEmpId" value="">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- Machine User ID -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                        Machine Biometric ID *
                    </label>
                    <input type="number" name="machine_user_id" id="f_machine_user_id" required value="<?= $nextMachineId ?>"
                        class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-100 font-mono focus:border-gold-500 focus:outline-none">
                    <p class="text-[10px] text-slate-500 mt-1">Must match the ID enrolled on the fingerprint device</p>
                </div>

                <!-- Office Emp Code -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                        Employee Code *
                    </label>
                    <input type="text" name="emp_no" id="f_emp_no" required value="<?= $nextEmpNo ?>"
                        class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-100 font-mono focus:border-gold-500 focus:outline-none">
                </div>

                <!-- Full Name -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                        Full Name *
                    </label>
                    <input type="text" name="name" id="f_name" required placeholder="e.g. Udara Perera" oninput="autoFillEmailPrefix()"
                        class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-100 focus:border-gold-500 focus:outline-none">
                </div>

                <!-- Calling Name -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                        Calling / Display Name
                    </label>
                    <input type="text" name="calling_name" id="f_calling_name" placeholder="e.g. Udara" oninput="autoFillEmailPrefix()"
                        class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-100 focus:border-gold-500 focus:outline-none">
                </div>

                <!-- Staff Email (Editable Prefix + Locked @sapphire) -->
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                        Staff Email / Login ID
                    </label>
                    <input type="hidden" name="email_domain" id="f_email_domain" value="@sapphire">
                    <div class="flex items-stretch rounded-xl overflow-hidden border border-slate-800 bg-slate-950 focus-within:border-gold-500 transition-colors">
                        <input type="text" name="email_prefix" id="f_email_prefix" placeholder="e.g. udara"
                            oninput="onEmailPrefixInput()"
                            class="flex-1 min-w-0 px-3.5 py-2.5 bg-transparent text-sm text-slate-100 font-mono focus:outline-none">
                        <span id="f_email_domain_badge" class="px-3.5 py-2.5 bg-slate-900 border-l border-slate-800 text-xs font-mono font-bold text-gold-400 flex items-center select-none">
                            @sapphire
                        </span>
                    </div>
                    <p class="text-[10px] text-slate-500 mt-1">
                        Auto-fills from user name (e.g. <span class="font-mono text-gold-400">udara@sapphire</span>). You can edit the prefix before <span class="font-mono text-slate-300">@sapphire</span> anytime.
                    </p>
                </div>

                <!-- Join Date -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                        Join Date *
                    </label>
                    <input type="date" name="join_date" id="f_join_date" required value="<?= date('Y-m-d') ?>" onchange="onJoinDateChange()"
                        class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-100 font-mono focus:border-gold-500 focus:outline-none">
                    <p class="text-[10px] text-slate-500 mt-1">Date employee joined (start of probation/service)</p>
                </div>

                <!-- Date of Appointment -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                        Date of Appointment
                    </label>
                    <input type="date" name="appointment_date" id="f_appointment_date" oninput="onAppointmentDateInput()"
                        class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-100 font-mono focus:border-gold-500 focus:outline-none">
                    <p class="text-[10px] text-slate-500 mt-1">Full 14 Annual + 7 Casual unlock from 1st of following month</p>
                </div>

                <!-- Gender -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                        Gender *
                    </label>
                    <select name="gender" id="f_gender" onchange="handleGenderChange()" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-100 focus:border-gold-500 focus:outline-none">
                        <option value="Female">Female (Ladies)</option>
                        <option value="Male">Male</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <!-- Department -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                        Department *
                    </label>
                    <input type="text" name="department" id="f_department" value="Sapphire" required
                        class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-100 focus:border-gold-500 focus:outline-none">
                </div>

                <!-- Designation -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                        Designation
                    </label>
                    <input type="text" name="designation" id="f_designation" placeholder="e.g. Senior Aesthetician"
                        class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-100 focus:border-gold-500 focus:outline-none">
                </div>

                <!-- Phone -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                        Contact Phone
                    </label>
                    <input type="text" name="phone" id="f_phone" placeholder="07XXXXXXXX"
                        class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-100 focus:border-gold-500 focus:outline-none">
                </div>

                <!-- Shift Start -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                        Shift Start Time
                    </label>
                    <input type="time" name="shift_start" id="f_shift_start" value="09:00"
                        class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-100 focus:border-gold-500 focus:outline-none">
                </div>

                <!-- Shift End -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                        Shift End Time
                    </label>
                    <input type="time" name="shift_end" id="f_shift_end" value="18:00"
                        class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-100 focus:border-gold-500 focus:outline-none">
                </div>

                <!-- Role -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                        System Role
                    </label>
                    <select name="role" id="f_role" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-100 focus:border-gold-500 focus:outline-none">
                        <option value="employee">Staff / Employee</option>
                        <option value="supervisor">Supervisor</option>
                        <option value="manager">Manager</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>

                <!-- Employment Status (Confirmed vs Probation) -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                        Employment Status (Leave Quota)
                    </label>
                    <select name="employment_type" id="f_employment_type" onchange="handleEmploymentTypeChange()" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-100 focus:border-gold-500 focus:outline-none">
                        <option value="confirmed">Confirmed (Annual 14 + Casual 7 + Monthly 4)</option>
                        <option value="probation">Probation (Monthly 4 Only - No Annual/Casual)</option>
                    </select>
                </div>

                <!-- Probation Period Duration (6 Months vs 12 Months) -->
                <div id="probationDurationBox" class="sm:col-span-2 p-3.5 rounded-xl bg-amber-950/20 border border-amber-500/30 hidden">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-amber-300 mb-2">
                        <i class="fa-solid fa-clock-rotate-left mr-1"></i> Probation Period Duration
                    </label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="flex items-center gap-2.5 p-2.5 rounded-lg bg-slate-900 border border-slate-700 cursor-pointer hover:border-amber-500/60 transition-colors">
                            <input type="radio" name="probation_months" id="f_prob_6" value="6" checked class="text-amber-500 focus:ring-amber-500">
                            <div>
                                <span class="text-xs text-slate-100 font-semibold block">6 Months Probation</span>
                                <span class="text-[10px] text-slate-400">Standard 6-month trial</span>
                            </div>
                        </label>
                        <label class="flex items-center gap-2.5 p-2.5 rounded-lg bg-slate-900 border border-slate-700 cursor-pointer hover:border-amber-500/60 transition-colors">
                            <input type="radio" name="probation_months" id="f_prob_12" value="12" class="text-amber-500 focus:ring-amber-500">
                            <div>
                                <span class="text-xs text-slate-100 font-semibold block">12 Months (1 Year)</span>
                                <span class="text-[10px] text-slate-400">12-month extended trial</span>
                            </div>
                        </label>
                    </div>
                    <p class="text-[11px] text-amber-400/80 mt-2">
                        💡 Probation employees receive only 4 Monthly Leaves per month. Once their 6 or 12 months complete, the system will alert you to officially confirm them and unlock their 14 Annual and 7 Casual leaves.
                    </p>
                </div>

                <!-- Status (Active / Inactive) -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                        Account Status
                    </label>
                    <select name="status" id="f_status" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-100 focus:border-gold-500 focus:outline-none">
                        <option value="active">Active (Can log in & attend)</option>
                        <option value="inactive">Inactive (Deactivated / Resigned)</option>
                    </select>
                </div>

                <!-- Password -->
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                        Staff Portal Password
                    </label>
                    <input type="text" name="password" id="f_password" placeholder="Default: 123456"
                        class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-100 focus:border-gold-500 focus:outline-none">
                    <p class="text-[10px] text-slate-500 mt-1">Leave empty to keep existing password when editing</p>
                </div>
            </div>

            <!-- ADMIN CUSTOM LEAVE ENTITLEMENT OVERRIDE (OPTIONAL) -->
            <?php if ($isAdmin): ?>
            <div class="p-4 rounded-xl bg-indigo-950/25 border border-indigo-500/30 mt-4 space-y-3">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="custom_leave_enabled" id="f_custom_leave_enabled" onchange="handleCustomLeaveToggle()" class="w-4 h-4 rounded text-indigo-500 focus:ring-indigo-500 bg-slate-900 border-slate-700">
                    <div>
                        <span class="text-xs font-semibold text-indigo-300 flex items-center gap-1.5">
                            <i class="fa-solid fa-sliders"></i> Customize Leave Entitlement (Admin Override)
                        </span>
                        <p class="text-[11px] text-slate-400 mt-0.5">
                            Optional: Turn ON to manually set custom Annual, Casual, Monthly &amp; Medical leave quotas for this employee instead of automatic probation/accrual rules.
                        </p>
                    </div>
                </label>

                <div id="customLeaveFields" class="hidden pt-2 border-t border-indigo-500/20 space-y-3">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <div>
                            <label class="block text-[10px] font-semibold uppercase tracking-wider text-indigo-300 mb-1">Annual / Yr</label>
                            <input type="number" step="0.5" min="0" max="60" name="custom_annual_quota" id="f_custom_annual" value="14"
                                class="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-lg text-xs text-white font-mono focus:border-indigo-400 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-semibold uppercase tracking-wider text-indigo-300 mb-1">Casual / Yr</label>
                            <input type="number" step="0.5" min="0" max="30" name="custom_casual_quota" id="f_custom_casual" value="7"
                                class="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-lg text-xs text-white font-mono focus:border-indigo-400 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-semibold uppercase tracking-wider text-gold-300 mb-1">Monthly / Mo</label>
                            <input type="number" step="0.5" min="0" max="15" name="custom_monthly_quota" id="f_custom_monthly" value="4"
                                class="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-lg text-xs text-white font-mono focus:border-indigo-400 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-semibold uppercase tracking-wider text-indigo-300 mb-1">Medical / Yr</label>
                            <input type="number" step="0.5" min="0" max="60" name="custom_medical_quota" id="f_custom_medical" value="7"
                                class="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-lg text-xs text-white font-mono focus:border-indigo-400 focus:outline-none">
                        </div>
                    </div>
                </div>

                <!-- Optional: Adjust Already-Used Leaves This Year (Visible when editing) -->
                <div id="adjustUsedBox" class="hidden pt-2 border-t border-indigo-500/20">
                    <label class="flex items-center gap-2 cursor-pointer mb-2">
                        <input type="checkbox" name="adjust_used_leaves" id="f_adjust_used_leaves" value="1" onchange="handleAdjustUsedToggle()" class="w-3.5 h-3.5 rounded text-amber-500 bg-slate-900 border-slate-700">
                        <span class="text-[11px] font-semibold text-amber-300">Adjust <?= date('Y') ?> Leaves Already Taken (Opening Balance)</span>
                    </label>
                    <div id="adjustUsedFields" class="hidden grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-[10px] text-slate-400 mb-1">Annual Used</label>
                            <input type="number" step="0.5" min="0" max="60" name="adj_annual_used" id="f_adj_annual_used" value="0"
                                class="w-full px-2.5 py-1.5 bg-slate-950 border border-slate-700 rounded-lg text-xs text-amber-300 font-mono">
                        </div>
                        <div>
                            <label class="block text-[10px] text-slate-400 mb-1">Casual Used</label>
                            <input type="number" step="0.5" min="0" max="30" name="adj_casual_used" id="f_adj_casual_used" value="0"
                                class="w-full px-2.5 py-1.5 bg-slate-950 border border-slate-700 rounded-lg text-xs text-amber-300 font-mono">
                        </div>
                        <div>
                            <label class="block text-[10px] text-slate-400 mb-1">Medical Used</label>
                            <input type="number" step="0.5" min="0" max="60" name="adj_medical_used" id="f_adj_medical_used" value="0"
                                class="w-full px-2.5 py-1.5 bg-slate-950 border border-slate-700 rounded-lg text-xs text-amber-300 font-mono">
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- MATERNITY LEAVE TOGGLE IN MODAL -->
            <div id="maternityBox" class="p-4 rounded-xl bg-purple-950/30 border border-purple-800/40 mt-4">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="maternity_eligible" id="f_maternity_eligible" class="w-4 h-4 rounded text-purple-600 focus:ring-purple-500 bg-slate-900 border-slate-700">
                    <div>
                        <span class="text-xs font-semibold text-purple-300 flex items-center gap-1.5">
                            <i class="fa-solid fa-baby"></i> Enable Maternity Leave Option for this Employee
                        </span>
                        <p class="text-[11px] text-purple-400/80 mt-0.5">
                            Check this only if she has requested maternity leave. Once enabled, she can choose "Maternity Leave" (84 Days) in her staff portal.
                        </p>
                    </div>
                </label>
            </div>

            <!-- PERMISSION CHECKBOXES -->
            <div class="p-4 rounded-xl bg-slate-950/70 border border-slate-800 space-y-2 mt-4">
                <div class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Management Permissions</div>
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="can_approve_leave" id="f_can_approve_leave" class="w-4 h-4 rounded text-gold-500 focus:ring-gold-500 bg-slate-900 border-slate-700">
                    <span class="text-xs text-slate-300">Can approve / reject employee leave requests</span>
                </label>
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="can_approve_manual_punch" id="f_can_approve_manual_punch" class="w-4 h-4 rounded text-gold-500 focus:ring-gold-500 bg-slate-900 border-slate-700">
                    <span class="text-xs text-slate-300">Can authorize manual in / out attendance requests</span>
                </label>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-800">
                <button type="button" onclick="closeModal()" class="px-4 py-2.5 rounded-xl text-xs font-medium text-slate-400 hover:bg-slate-800">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2.5 rounded-xl text-xs font-semibold text-slate-950 bg-gold-400 hover:bg-gold-500 shadow-lg shadow-gold-500/20">
                    Save Employee
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const modal = document.getElementById('employeeModal');
    const maternityBox = document.getElementById('maternityBox');
    const probationDurationBox = document.getElementById('probationDurationBox');
    let emailPrefixManuallyEdited = false;
    let appointmentDateManuallyEdited = false;

    function sanitizeEmailPrefix(raw) {
        if (!raw) return '';
        const firstWord = raw.trim().split(/\s+/)[0] || '';
        return firstWord.toLowerCase().replace(/[^a-z0-9._-]/g, '');
    }

    function autoFillEmailPrefix() {
        const isCreating = document.getElementById('formAction').value === 'create';
        if (!isCreating || emailPrefixManuallyEdited) return;
        const calling = document.getElementById('f_calling_name').value.trim();
        const full = document.getElementById('f_name').value.trim();
        const prefix = sanitizeEmailPrefix(calling || full);
        document.getElementById('f_email_prefix').value = prefix;
    }

    function onEmailPrefixInput() {
        emailPrefixManuallyEdited = true;
        const el = document.getElementById('f_email_prefix');
        // Strip @ and anything after it so @sapphire suffix stays clean & locked
        if (el.value.includes('@')) {
            el.value = el.value.split('@')[0];
        }
        el.value = el.value.toLowerCase().replace(/[^a-z0-9._-]/g, '');
    }

    function addOneYear(dateStr) {
        if (!dateStr || !/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) return '';
        const parts = dateStr.split('-').map(Number);
        const nextYr = parts[0] + 1;
        const mm = String(parts[1]).padStart(2, '0');
        const dd = String(parts[2]).padStart(2, '0');
        return `${nextYr}-${mm}-${dd}`;
    }

    function onJoinDateChange() {
        if (!appointmentDateManuallyEdited) {
            const jVal = document.getElementById('f_join_date').value;
            document.getElementById('f_appointment_date').value = addOneYear(jVal);
        }
    }

    function onAppointmentDateInput() {
        appointmentDateManuallyEdited = true;
    }

    function handleCustomLeaveToggle() {
        const chk = document.getElementById('f_custom_leave_enabled');
        const box = document.getElementById('customLeaveFields');
        if (!chk || !box) return;
        if (chk.checked) {
            box.classList.remove('hidden');
        } else {
            box.classList.add('hidden');
        }
    }

    function handleAdjustUsedToggle() {
        const chk = document.getElementById('f_adjust_used_leaves');
        const box = document.getElementById('adjustUsedFields');
        if (!chk || !box) return;
        if (chk.checked) {
            box.classList.remove('hidden');
        } else {
            box.classList.add('hidden');
        }
    }

    function handleGenderChange() {
        const gender = document.getElementById('f_gender').value;
        const matCheckbox = document.getElementById('f_maternity_eligible');
        if (gender === 'Male') {
            maternityBox.classList.add('hidden');
            matCheckbox.checked = false;
        } else {
            maternityBox.classList.remove('hidden');
        }
    }

    function handleEmploymentTypeChange() {
        const type = document.getElementById('f_employment_type').value;
        if (type === 'probation') {
            probationDurationBox.classList.remove('hidden');
        } else {
            probationDurationBox.classList.add('hidden');
        }
    }

    function openNewModal() {
        emailPrefixManuallyEdited = false;
        appointmentDateManuallyEdited = false;
        const todayStr = '<?= date('Y-m-d') ?>';
        document.getElementById('modalTitle').innerText = 'Register New Employee';
        document.getElementById('formAction').value = 'create';
        document.getElementById('formEmpId').value = '';
        document.getElementById('f_machine_user_id').value = '<?= $nextMachineId ?>';
        document.getElementById('f_emp_no').value = '<?= $nextEmpNo ?>';
        document.getElementById('f_name').value = '';
        document.getElementById('f_calling_name').value = '';
        document.getElementById('f_email_prefix').value = '';
        document.getElementById('f_email_domain').value = '@sapphire';
        document.getElementById('f_email_domain_badge').innerText = '@sapphire';
        document.getElementById('f_join_date').value = todayStr;
        document.getElementById('f_appointment_date').value = addOneYear(todayStr);
        document.getElementById('f_phone').value = '';
        document.getElementById('f_designation').value = '';
        document.getElementById('f_gender').value = 'Female';
        document.getElementById('f_role').value = 'employee';
        document.getElementById('f_employment_type').value = 'confirmed';
        document.getElementById('f_prob_6').checked = true;
        document.getElementById('f_status').value = 'active';
        document.getElementById('f_can_approve_leave').checked = false;
        document.getElementById('f_can_approve_manual_punch').checked = false;
        document.getElementById('f_maternity_eligible').checked = false;
        document.getElementById('f_password').value = '123456';

        const custChk = document.getElementById('f_custom_leave_enabled');
        if (custChk) {
            custChk.checked = false;
            document.getElementById('f_custom_annual').value = '14';
            document.getElementById('f_custom_casual').value = '7';
            document.getElementById('f_custom_monthly').value = '4';
            document.getElementById('f_custom_medical').value = '7';
            handleCustomLeaveToggle();
        }
        const adjBox = document.getElementById('adjustUsedBox');
        if (adjBox) adjBox.classList.add('hidden');
        const adjChk = document.getElementById('f_adjust_used_leaves');
        if (adjChk) { adjChk.checked = false; handleAdjustUsedToggle(); }

        handleGenderChange();
        handleEmploymentTypeChange();
        modal.classList.remove('hidden');
    }

    function openEditModal(emp) {
        emailPrefixManuallyEdited = true;
        appointmentDateManuallyEdited = Boolean(emp.appointment_date);
        document.getElementById('modalTitle').innerText = 'Edit Employee #' + emp.machine_user_id;
        document.getElementById('formAction').value = 'update';
        document.getElementById('formEmpId').value = emp.id;
        document.getElementById('f_machine_user_id').value = emp.machine_user_id;
        document.getElementById('f_emp_no').value = emp.emp_no;
        document.getElementById('f_name').value = emp.name;
        document.getElementById('f_calling_name').value = emp.calling_name || '';

        // Populate email prefix + locked domain suffix
        const rawEmail = (emp.email || '').trim();
        if (rawEmail && rawEmail.includes('@')) {
            const idx = rawEmail.indexOf('@');
            const pref = rawEmail.substring(0, idx);
            const dom = rawEmail.substring(idx);
            document.getElementById('f_email_prefix').value = pref;
            // Keep existing non-@sapphire domain for legacy admin accounts, otherwise @sapphire
            const finalDom = (dom.toLowerCase() === '@sapphire' || emp.role !== 'admin') ? '@sapphire' : dom;
            document.getElementById('f_email_domain').value = finalDom;
            document.getElementById('f_email_domain_badge').innerText = finalDom;
        } else {
            document.getElementById('f_email_prefix').value = sanitizeEmailPrefix(emp.calling_name || emp.name);
            document.getElementById('f_email_domain').value = '@sapphire';
            document.getElementById('f_email_domain_badge').innerText = '@sapphire';
        }

        const jDate = emp.join_date || '<?= date('Y-m-d') ?>';
        document.getElementById('f_join_date').value = jDate;
        document.getElementById('f_appointment_date').value = emp.appointment_date || addOneYear(jDate);

        document.getElementById('f_gender').value = emp.gender;
        document.getElementById('f_department').value = emp.department;
        document.getElementById('f_designation').value = emp.designation || '';
        document.getElementById('f_phone').value = emp.phone || '';
        document.getElementById('f_shift_start').value = (emp.shift_start || '09:00:00').substring(0, 5);
        document.getElementById('f_shift_end').value = (emp.shift_end || '18:00:00').substring(0, 5);
        document.getElementById('f_role').value = emp.role;
        document.getElementById('f_employment_type').value = emp.employment_type || 'confirmed';
        if (emp.probation_months == 12) {
            document.getElementById('f_prob_12').checked = true;
        } else {
            document.getElementById('f_prob_6').checked = true;
        }
        document.getElementById('f_status').value = emp.status || 'active';
        document.getElementById('f_can_approve_leave').checked = emp.can_approve_leave == 1;
        document.getElementById('f_can_approve_manual_punch').checked = emp.can_approve_manual_punch == 1;
        document.getElementById('f_maternity_eligible').checked = emp.maternity_eligible == 1;
        document.getElementById('f_password').value = '';

        // Populate Admin custom leave entitlement & opening used balances
        const custChk = document.getElementById('f_custom_leave_enabled');
        if (custChk) {
            custChk.checked = emp.custom_leave_enabled == 1;
            document.getElementById('f_custom_annual').value = emp.custom_annual_quota ?? emp.bal_annual_total ?? 14;
            document.getElementById('f_custom_casual').value = emp.custom_casual_quota ?? emp.bal_casual_total ?? 7;
            document.getElementById('f_custom_monthly').value = emp.custom_monthly_quota ?? 4;
            document.getElementById('f_custom_medical').value = emp.custom_medical_quota ?? emp.bal_medical_total ?? 7;
            handleCustomLeaveToggle();
        }
        const adjBox = document.getElementById('adjustUsedBox');
        if (adjBox) adjBox.classList.remove('hidden');
        const adjChk = document.getElementById('f_adjust_used_leaves');
        if (adjChk) {
            adjChk.checked = false;
            document.getElementById('f_adj_annual_used').value = emp.bal_annual_used ?? 0;
            document.getElementById('f_adj_casual_used').value = emp.bal_casual_used ?? 0;
            document.getElementById('f_adj_medical_used').value = emp.bal_medical_used ?? 0;
            handleAdjustUsedToggle();
        }

        handleGenderChange();
        handleEmploymentTypeChange();
        modal.classList.remove('hidden');
    }

    function closeModal() {
        modal.classList.add('hidden');
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
