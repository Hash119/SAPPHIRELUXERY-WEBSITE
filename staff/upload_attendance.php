<?php
/**
 * Sapphire Aesthetic & Luxury - 1-Click Biometric Excel Importer
 * Parses VFT / ZKTeco SSR Excel reports (1_StandardReport.xlsx)
 */
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/excel_reader.php';
require_login();

$user = current_user();
if (!is_admin_or_manager($user)) {
    header('Location: employee_portal.php');
    exit;
}

$pageTitle = 'Upload Machine Attendance (USB Excel)';
$result = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = "File upload failed with error code: " . $file['error'];
    } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'xlsx') {
            $error = "Please upload a valid Excel file (.xlsx format exported from the attendance device).";
        } else {
            try {
                // Parse Excel
                $parsed = ZkReportReader::parse($file['tmp_name']);
                
                // Commit to MySQL Database
                $insertedCount = 0;
                $updatedCount = 0;
                $totalLate = 0;
                $importedRowsPreview = [];

                if (isset($pdo)) {
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

                    foreach ($parsed['employees'] as $mId => $empData) {
                        // Check if employee exists, if not auto-create
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

                            // Leave balances
                            $pdo->prepare("INSERT INTO leave_balances (employee_id, year) VALUES (?, ?)")->execute([$newEmpId, $parsed['year']]);
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

                            if (count($importedRowsPreview) < 20) {
                                $importedRowsPreview[] = [
                                    'machine_id' => $mId,
                                    'name' => $empData['name'],
                                    'date' => $rec['date'],
                                    'check_in' => $rec['check_in'],
                                    'check_out' => $rec['check_out'],
                                    'late_mins' => $rec['late_minutes'],
                                    'work_hours' => $rec['work_hours'],
                                    'status' => $rec['status']
                                ];
                            }
                        }
                    }

                    $pdo->commit();

                    $result = [
                        'file_name' => $file['name'],
                        'start_date' => $parsed['start_date'],
                        'end_date' => $parsed['end_date'],
                        'total_employees' => $parsed['total_employees_found'],
                        'total_records' => $insertedCount,
                        'total_late' => $totalLate,
                        'preview' => $importedRowsPreview
                    ];
                }
            } catch (Exception $e) {
                if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
                $error = "Error parsing Excel: " . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="max-w-4xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl sm:text-2xl font-serif font-bold text-white">Import Machine Attendance</h2>
            <p class="text-xs text-slate-400 mt-1">Upload the standard Excel report (<code>1_StandardReport.xlsx</code>) exported from the fingerprint device</p>
        </div>
        <a href="attendance.php" class="text-xs text-gold-400 hover:text-gold-300 font-medium">View Attendance &rarr;</a>
    </div>

    <!-- Error Alert -->
    <?php if ($error): ?>
        <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs flex items-center gap-3">
            <i class="fa-solid fa-circle-exclamation text-base"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- Success Result Box -->
    <?php if ($result): ?>
        <div class="bg-slate-900/80 border border-emerald-500/30 rounded-2xl p-6 shadow-xl space-y-6">
            <div class="flex items-center gap-3 text-emerald-400">
                <div class="w-10 h-10 rounded-xl bg-emerald-500/10 flex items-center justify-center text-lg">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div>
                    <h3 class="font-serif font-bold text-base text-white">Import Successfully Completed!</h3>
                    <p class="text-xs text-slate-400"><?= htmlspecialchars($result['file_name']) ?> has been parsed and synced to database.</p>
                </div>
            </div>

            <!-- Stats grid -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="bg-slate-950 p-4 rounded-xl border border-slate-800">
                    <span class="block text-[11px] text-slate-400 uppercase">Period</span>
                    <span class="block text-xs font-mono font-bold text-gold-400 mt-1">
                        <?= $result['start_date'] ?> ~ <?= $result['end_date'] ?>
                    </span>
                </div>
                <div class="bg-slate-950 p-4 rounded-xl border border-slate-800">
                    <span class="block text-[11px] text-slate-400 uppercase">Staff Processed</span>
                    <span class="block text-lg font-bold text-white mt-1"><?= $result['total_employees'] ?></span>
                </div>
                <div class="bg-slate-950 p-4 rounded-xl border border-slate-800">
                    <span class="block text-[11px] text-slate-400 uppercase">Attendance Days</span>
                    <span class="block text-lg font-bold text-emerald-400 mt-1"><?= $result['total_records'] ?></span>
                </div>
                <div class="bg-slate-950 p-4 rounded-xl border border-slate-800">
                    <span class="block text-[11px] text-slate-400 uppercase">Late Arrivals</span>
                    <span class="block text-lg font-bold text-amber-400 mt-1"><?= $result['total_late'] ?></span>
                </div>
            </div>

            <!-- Preview table -->
            <div>
                <h4 class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-3">Recent Imported Records Preview</h4>
                <div class="overflow-x-auto rounded-xl border border-slate-800">
                    <table class="w-full text-left text-xs text-slate-300">
                        <thead class="bg-slate-950 text-slate-400 font-semibold text-[10px] uppercase">
                            <tr>
                                <th class="py-2.5 px-3">ID</th>
                                <th class="py-2.5 px-3">Name</th>
                                <th class="py-2.5 px-3">Date</th>
                                <th class="py-2.5 px-3">In</th>
                                <th class="py-2.5 px-3">Out</th>
                                <th class="py-2.5 px-3">Work Hours</th>
                                <th class="py-2.5 px-3">Late Mins</th>
                                <th class="py-2.5 px-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60 bg-slate-950/40 font-mono text-[11px]">
                            <?php foreach ($result['preview'] as $row): ?>
                                <tr>
                                    <td class="py-2 px-3 text-gold-400 font-bold">#<?= $row['machine_id'] ?></td>
                                    <td class="py-2 px-3 text-white font-sans"><?= htmlspecialchars($row['name']) ?></td>
                                    <td class="py-2 px-3 text-slate-400"><?= $row['date'] ?></td>
                                    <td class="py-2 px-3 text-emerald-400"><?= substr($row['check_in'] ?? '', 0, 5) ?: '-' ?></td>
                                    <td class="py-2 px-3 text-slate-300"><?= substr($row['check_out'] ?? '', 0, 5) ?: '-' ?></td>
                                    <td class="py-2 px-3"><?= $row['work_hours'] ?> h</td>
                                    <td class="py-2 px-3 text-amber-400"><?= $row['late_mins'] > 0 ? '+' . $row['late_mins'] . 'm' : '-' ?></td>
                                    <td class="py-2 px-3">
                                        <span class="px-1.5 py-0.2 rounded text-[10px] <?= $row['status'] === 'Late' ? 'bg-amber-500/20 text-amber-400' : 'bg-emerald-500/20 text-emerald-400' ?>">
                                            <?= $row['status'] ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-800">
                <a href="reports.php" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-950 bg-gold-400 hover:bg-gold-500">
                    View Monthly Summary Report &rarr;
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Upload Card -->
    <div class="bg-slate-900/70 border border-slate-800/80 rounded-2xl p-6 sm:p-8">
        <form method="POST" action="upload_attendance.php" enctype="multipart/form-data" id="uploadForm">
            <!-- Drag & Drop Zone -->
            <div id="dropZone" class="border-2 border-dashed border-slate-700 hover:border-gold-500 rounded-2xl p-8 sm:p-12 text-center transition-all bg-slate-950/40 cursor-pointer">
                <input type="file" name="excel_file" id="fileInput" accept=".xlsx" class="hidden" required>
                
                <div class="w-16 h-16 rounded-2xl bg-gold-500/10 text-gold-400 flex items-center justify-center mx-auto mb-4 text-2xl shadow-lg shadow-gold-500/10">
                    <i class="fa-solid fa-file-excel"></i>
                </div>

                <h3 class="text-base font-semibold text-white mb-1">Click to select or drag & drop machine file here</h3>
                <p class="text-xs text-slate-400 mb-4">Select <code>1_StandardReport.xlsx</code> downloaded onto your USB flash drive</p>

                <div id="fileSelectedDisplay" class="hidden inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-xs font-mono font-medium mb-3">
                    <i class="fa-solid fa-check"></i>
                    <span id="fileNameLabel">File selected</span>
                </div>

                <div>
                    <button type="button" onclick="document.getElementById('fileInput').click()" class="px-5 py-2.5 rounded-xl text-xs font-semibold text-white bg-slate-800 hover:bg-slate-700 border border-slate-700 transition-colors">
                        Browse Computer
                    </button>
                </div>
            </div>

            <!-- Information Guide -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-6 pt-6 border-t border-slate-800 text-xs text-slate-400">
                <div class="flex items-start gap-2.5">
                    <span class="w-6 h-6 rounded-full bg-slate-800 text-gold-400 flex items-center justify-center text-[11px] font-bold shrink-0">1</span>
                    <p>Insert pen drive into the attendance machine, select <strong>Download Report</strong>.</p>
                </div>
                <div class="flex items-start gap-2.5">
                    <span class="w-6 h-6 rounded-full bg-slate-800 text-gold-400 flex items-center justify-center text-[11px] font-bold shrink-0">2</span>
                    <p>Plug pen drive into PC and upload <code>1_StandardReport.xlsx</code> here.</p>
                </div>
                <div class="flex items-start gap-2.5">
                    <span class="w-6 h-6 rounded-full bg-slate-800 text-gold-400 flex items-center justify-center text-[11px] font-bold shrink-0">3</span>
                    <p>System automatically extracts daily in/out stamps and calculates late/OT minutes.</p>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="mt-6 flex justify-end">
                <button type="submit" id="submitBtn" class="px-6 py-3 rounded-xl text-xs font-semibold text-slate-950 bg-gradient-to-r from-gold-500 to-amber-400 hover:from-gold-400 hover:to-amber-300 shadow-lg shadow-gold-500/20 transition-all flex items-center gap-2">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                    <span>Process & Sync Attendance Now</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    const display = document.getElementById('fileSelectedDisplay');
    const label = document.getElementById('fileNameLabel');

    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('border-gold-500', 'bg-gold-500/5');
    });

    dropZone.addEventListener('dragleave', () => {
        dropZone.classList.remove('border-gold-500', 'bg-gold-500/5');
    });

    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('border-gold-500', 'bg-gold-500/5');
        if (e.dataTransfer.files.length) {
            fileInput.files = e.dataTransfer.files;
            updateFileLabel();
        }
    });

    fileInput.addEventListener('change', updateFileLabel);

    function updateFileLabel() {
        if (fileInput.files.length) {
            label.innerText = fileInput.files[0].name + ' (' + (fileInput.files[0].size / 1024).toFixed(1) + ' KB)';
            display.classList.remove('hidden');
        }
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
