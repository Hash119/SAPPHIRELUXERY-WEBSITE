<?php
/**
 * Sapphire Aesthetic & Luxury - System Settings & Permission Rules
 */
require_once __DIR__ . '/config/auth.php';
require_admin();

$pageTitle = 'System Settings & Leave Permissions';
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($pdo)) {
    try {
        $settingsToUpdate = [
            'company_name'            => trim($_POST['company_name'] ?? 'Sapphire Aesthetic & Luxury'),
            'default_shift_start'     => $_POST['default_shift_start'] ?? '09:00:00',
            'default_shift_end'       => $_POST['default_shift_end'] ?? '18:00:00',
            'grace_period_minutes'    => (int)($_POST['grace_period_minutes'] ?? 15),
            'default_maternity_days'  => (int)($_POST['default_maternity_days'] ?? 84),
            'leave_approval_role'     => $_POST['leave_approval_role'] ?? 'admin_or_supervisor',
            'machine_sync_api_key'    => trim($_POST['machine_sync_api_key'] ?? 'sapphire_secret_sync_key_2026')
        ];

        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        foreach ($settingsToUpdate as $key => $val) {
            $stmt->execute([$key, $val]);
        }

        $message = "Settings and approval permissions saved successfully!";
    } catch (Exception $e) {
        $error = "Error saving settings: " . $e->getMessage();
    }
}

// Fetch current settings
$currentSettings = [];
if (isset($pdo)) {
    try {
        $currentSettings = $pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Exception $e) {}
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="max-w-4xl mx-auto space-y-6">
    <!-- Header -->
    <div>
        <h2 class="text-xl sm:text-2xl font-serif font-bold text-white">System Settings & Leave Permissions</h2>
        <p class="text-xs text-slate-400 mt-1">Configure company working hours, grace period, maternity rules, and leave approver permissions</p>
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

    <form method="POST" action="settings.php" class="space-y-6">
        <!-- 1. LEAVE APPROVAL PERMISSIONS -->
        <div class="bg-slate-900/70 border border-slate-800/80 rounded-2xl p-6 sm:p-8 space-y-4">
            <div class="flex items-center gap-3 pb-4 border-b border-slate-800">
                <span class="w-10 h-10 rounded-xl bg-gold-500/10 text-gold-400 flex items-center justify-center text-lg">
                    <i class="fa-solid fa-user-shield"></i>
                </span>
                <div>
                    <h3 class="font-serif font-bold text-base text-white">Leave Approval Permission Rules</h3>
                    <p class="text-xs text-slate-400">Decide who in the organization is authorized to approve leave applications</p>
                </div>
            </div>

            <div class="space-y-3 pt-2">
                <label class="flex items-start gap-3 p-4 rounded-xl bg-slate-950/60 border border-slate-800 hover:border-gold-500/40 cursor-pointer transition-all">
                    <input type="radio" name="leave_approval_role" value="admin_only"
                        <?= ($currentSettings['leave_approval_role'] ?? '') === 'admin_only' ? 'checked' : '' ?>
                        class="mt-1 w-4 h-4 text-gold-500 focus:ring-gold-500 bg-slate-900 border-slate-700">
                    <div>
                        <span class="text-xs font-semibold text-white block">Super Admin Only</span>
                        <p class="text-[11px] text-slate-400 mt-0.5">Only Super Administrators can approve or reject leaves. Supervisors cannot approve.</p>
                    </div>
                </label>

                <label class="flex items-start gap-3 p-4 rounded-xl bg-slate-950/60 border border-slate-800 hover:border-gold-500/40 cursor-pointer transition-all">
                    <input type="radio" name="leave_approval_role" value="admin_or_supervisor"
                        <?= ($currentSettings['leave_approval_role'] ?? 'admin_or_supervisor') === 'admin_or_supervisor' ? 'checked' : '' ?>
                        class="mt-1 w-4 h-4 text-gold-500 focus:ring-gold-500 bg-slate-900 border-slate-700">
                    <div>
                        <span class="text-xs font-semibold text-white block">Admin & Designated Authorizers (Recommended)</span>
                        <p class="text-[11px] text-slate-400 mt-0.5">Super Admin plus any staff member who has the <em>"Can Approve Leave"</em> permission enabled in the Employees directory (e.g. Supervisors, Managers).</p>
                    </div>
                </label>
            </div>
        </div>

        <!-- 2. SHIFTS, GRACE PERIOD & MATERNITY -->
        <div class="bg-slate-900/70 border border-slate-800/80 rounded-2xl p-6 sm:p-8 space-y-4">
            <div class="flex items-center gap-3 pb-4 border-b border-slate-800">
                <span class="w-10 h-10 rounded-xl bg-blue-500/10 text-blue-400 flex items-center justify-center text-lg">
                    <i class="fa-solid fa-clock"></i>
                </span>
                <div>
                    <h3 class="font-serif font-bold text-base text-white">Duty Shifts, Grace Period & Maternity</h3>
                    <p class="text-xs text-slate-400">Default working hours used by the calculation engine</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                        Default Morning Shift Start
                    </label>
                    <input type="time" name="default_shift_start" value="<?= htmlspecialchars($currentSettings['default_shift_start'] ?? '09:00:00') ?>"
                        class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-100 font-mono focus:border-gold-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                        Default Evening Shift End
                    </label>
                    <input type="time" name="default_shift_end" value="<?= htmlspecialchars($currentSettings['default_shift_end'] ?? '18:00:00') ?>"
                        class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-100 font-mono focus:border-gold-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                        Grace Period (Minutes)
                    </label>
                    <input type="number" name="grace_period_minutes" value="<?= htmlspecialchars($currentSettings['grace_period_minutes'] ?? '15') ?>"
                        class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-100 font-mono focus:border-gold-500 focus:outline-none">
                    <p class="text-[10px] text-slate-500 mt-1">Check-ins within 15 mins (e.g. up to 09:15) are counted as on-time</p>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                        Maternity Leave Quota (Working Days)
                    </label>
                    <input type="number" name="default_maternity_days" value="<?= htmlspecialchars($currentSettings['default_maternity_days'] ?? '84') ?>"
                        class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-100 font-mono focus:border-gold-500 focus:outline-none">
                    <p class="text-[10px] text-purple-400/80 mt-1">Sri Lanka Shop & Office Act standard: 84 working days (12 weeks)</p>
                </div>
            </div>
        </div>

        <!-- 3. BIOMETRIC MACHINE CLOUD SYNC API -->
        <div class="bg-slate-900/70 border border-slate-800/80 rounded-2xl p-6 sm:p-8 space-y-4">
            <div class="flex items-center gap-3 pb-4 border-b border-slate-800">
                <span class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center text-lg">
                    <i class="fa-solid fa-server"></i>
                </span>
                <div>
                    <h3 class="font-serif font-bold text-base text-white">Office PC Auto-Sync API</h3>
                    <p class="text-xs text-slate-400">Allows the salon PC to automatically push machine punches to Hostinger</p>
                </div>
            </div>

            <div class="space-y-3 pt-2">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                        Machine Sync Secret Key
                    </label>
                    <input type="text" name="machine_sync_api_key" value="<?= htmlspecialchars($currentSettings['machine_sync_api_key'] ?? 'sapphire_secret_sync_key_2026') ?>"
                        class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-emerald-400 font-mono focus:border-gold-500 focus:outline-none">
                </div>

                <div class="p-4 rounded-xl bg-slate-950 border border-slate-800 text-xs text-slate-400 space-y-1.5">
                    <p><strong class="text-white">Endpoint URL:</strong> <code class="text-gold-400 font-mono">https://staff.sapphireaesthetic.com/api/sync_attendance.php</code></p>
                    <p>The office PC script in <code>sync_tool/machine_sync.py</code> will use this secret key to upload real-time punch records.</p>
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="px-6 py-3 rounded-xl text-xs font-semibold text-slate-950 bg-gradient-to-r from-gold-500 to-amber-400 hover:from-gold-400 hover:to-amber-300 shadow-lg shadow-gold-500/20 transition-all flex items-center gap-2">
                <i class="fa-solid fa-floppy-disk"></i>
                <span>Save All Settings</span>
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
