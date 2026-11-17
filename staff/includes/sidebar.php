<?php
/**
 * Sapphire Aesthetic & Luxury - Sidebar Navigation
 */
require_once __DIR__ . '/../config/auth.php';
$user = current_user();
$isAdmin = $user && $user['role'] === 'admin';
$isManager = $user && in_array($user['role'], ['admin', 'manager', 'supervisor']);
$canApprove = can_approve_leave($user);
$currentPage = basename($_SERVER['PHP_SELF']);

// Pending counts for badges
$pendingLeavesCount = 0;
$pendingPunchesCount = 0;
if (isset($pdo) && $isManager) {
    try {
        $pendingLeavesCount = $pdo->query("SELECT COUNT(*) FROM leaves WHERE status = 'Pending'")->fetchColumn();
        $pendingPunchesCount = $pdo->query("SELECT COUNT(*) FROM manual_punches WHERE status = 'Pending'")->fetchColumn();
    } catch (Exception $e) {}
}
?>

<!-- Desktop Sidebar -->
<aside class="hidden md:flex md:w-64 md:flex-col bg-slate-900/95 border-r border-slate-800/80 backdrop-blur-md">
    <!-- Brand Header with Sapphire Logo -->
    <div class="flex items-center justify-between px-5 py-4 border-b border-slate-800/80">
        <a href="<?= $isManager ? 'dashboard.php' : 'employee_portal.php' ?>" class="flex items-center gap-3">
            <img src="assets/logo.jpg" alt="Sapphire Logo" onerror="this.src='../Photos/Logo.jpg'"
                 class="w-10 h-10 rounded-full border-2 border-gold-500 object-cover shadow-md shadow-gold-500/20">
            <div>
                <span class="block text-base font-serif font-bold tracking-wider gold-gradient">SAPPHIRE</span>
                <span class="block text-[10px] tracking-widest text-slate-400 uppercase">HR & Attendance</span>
            </div>
        </a>
    </div>

    <!-- User Snippet -->
    <div class="px-5 py-3.5 border-b border-slate-800/60 bg-slate-950/40">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-full bg-slate-800 border border-gold-500/30 flex items-center justify-center text-gold-400 font-semibold text-sm">
                <?= strtoupper(substr($user['calling_name'] ?? $user['name'] ?? 'U', 0, 1)) ?>
            </div>
            <div class="overflow-hidden">
                <p class="text-sm font-medium text-slate-200 truncate"><?= htmlspecialchars($user['calling_name'] ?? $user['name'] ?? 'Staff') ?></p>
                <div class="flex items-center gap-1.5 mt-0.5">
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium 
                        <?= $user['role'] === 'admin' ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20' : 
                           ($user['role'] === 'manager' || $user['role'] === 'supervisor' ? 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/20' : 'bg-slate-700/50 text-slate-300') ?>">
                        <?= ucfirst($user['role'] ?? 'Staff') ?>
                    </span>
                    <span class="text-[10px] text-slate-500 truncate">#<?= htmlspecialchars($user['machine_user_id'] ?? '') ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 px-3 py-4 space-y-1.5 overflow-y-auto">
        <?php if ($isManager): ?>
            <div class="px-3 pt-2 pb-1 text-[11px] font-semibold tracking-wider text-slate-400 uppercase">Management</div>
            
            <a href="dashboard.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $currentPage === 'dashboard.php' ? 'bg-gold-500/15 text-gold-400 border border-gold-500/30' : 'text-slate-300 hover:bg-slate-800/70 hover:text-white' ?>">
                <i class="fa-solid fa-chart-pie w-5 text-center text-slate-400"></i>
                <span>Dashboard</span>
            </a>

            <a href="attendance.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $currentPage === 'attendance.php' ? 'bg-gold-500/15 text-gold-400 border border-gold-500/30' : 'text-slate-300 hover:bg-slate-800/70 hover:text-white' ?>">
                <i class="fa-solid fa-calendar-check w-5 text-center text-slate-400"></i>
                <span>Daily Attendance</span>
            </a>

            <a href="upload_attendance.php" class="flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $currentPage === 'upload_attendance.php' ? 'bg-gold-500/15 text-gold-400 border border-gold-500/30' : 'text-slate-300 hover:bg-slate-800/70 hover:text-white' ?>">
                <div class="flex items-center gap-3">
                    <i class="fa-solid fa-file-excel w-5 text-center text-emerald-400"></i>
                    <span>Upload Machine Excel</span>
                </div>
                <span class="px-1.5 py-0.5 text-[10px] font-bold bg-emerald-500/20 text-emerald-300 rounded border border-emerald-500/30">USB</span>
            </a>

            <a href="employees.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $currentPage === 'employees.php' ? 'bg-gold-500/15 text-gold-400 border border-gold-500/30' : 'text-slate-300 hover:bg-slate-800/70 hover:text-white' ?>">
                <i class="fa-solid fa-users w-5 text-center text-slate-400"></i>
                <span>Staff & Maternity Rules</span>
            </a>

            <a href="leaves.php" class="flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $currentPage === 'leaves.php' ? 'bg-gold-500/15 text-gold-400 border border-gold-500/30' : 'text-slate-300 hover:bg-slate-800/70 hover:text-white' ?>">
                <div class="flex items-center gap-3">
                    <i class="fa-solid fa-calendar-minus w-5 text-center text-slate-400"></i>
                    <span>Leave Approvals</span>
                </div>
                <?php if ($pendingLeavesCount > 0): ?>
                    <span class="px-2 py-0.5 text-xs font-semibold bg-rose-500 text-white rounded-full"><?= $pendingLeavesCount ?></span>
                <?php endif; ?>
            </a>

            <a href="manual_punches.php" class="flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $currentPage === 'manual_punches.php' ? 'bg-gold-500/15 text-gold-400 border border-gold-500/30' : 'text-slate-300 hover:bg-slate-800/70 hover:text-white' ?>">
                <div class="flex items-center gap-3">
                    <i class="fa-solid fa-fingerprint w-5 text-center text-slate-400"></i>
                    <span>Manual In/Out & History</span>
                </div>
                <?php if ($pendingPunchesCount > 0): ?>
                    <span class="px-2 py-0.5 text-xs font-semibold bg-amber-500 text-slate-950 rounded-full"><?= $pendingPunchesCount ?></span>
                <?php endif; ?>
            </a>

            <a href="reports.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $currentPage === 'reports.php' ? 'bg-gold-500/15 text-gold-400 border border-gold-500/30' : 'text-slate-300 hover:bg-slate-800/70 hover:text-white' ?>">
                <i class="fa-solid fa-file-invoice w-5 text-center text-slate-400"></i>
                <span>Reports (Late & OT)</span>
            </a>

            <?php if ($isAdmin): ?>
            <a href="settings.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $currentPage === 'settings.php' ? 'bg-gold-500/15 text-gold-400 border border-gold-500/30' : 'text-slate-300 hover:bg-slate-800/70 hover:text-white' ?>">
                <i class="fa-solid fa-sliders w-5 text-center text-slate-400"></i>
                <span>Settings & Permissions</span>
            </a>
            <?php endif; ?>

            <div class="px-3 pt-4 pb-1 text-[11px] font-semibold tracking-wider text-slate-400 uppercase">Self Service</div>
        <?php endif; ?>

        <a href="employee_portal.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $currentPage === 'employee_portal.php' ? 'bg-gold-500/15 text-gold-400 border border-gold-500/30' : 'text-slate-300 hover:bg-slate-800/70 hover:text-white' ?>">
            <i class="fa-solid fa-id-badge w-5 text-center text-gold-400"></i>
            <span>My Portal & Leave Apply</span>
        </a>
    </nav>

    <!-- Bottom Footer (Theme Switcher + Logout) -->
    <div class="p-3 border-t border-slate-800/80 bg-slate-950/50 space-y-1">
        <button onclick="toggleTheme()" class="w-full flex items-center justify-between px-3 py-2 text-xs font-medium text-slate-400 hover:text-gold-400 rounded-lg hover:bg-slate-800/50 transition-colors">
            <span class="flex items-center gap-2.5">
                <i id="themeIcon" class="fa-solid fa-moon w-4 text-center"></i>
                <span id="themeLabel">Dark Theme</span>
            </span>
            <span class="text-[10px] text-slate-500 uppercase font-mono">Switch</span>
        </button>

        <a href="https://sapphireaesthetic.com" target="_blank" class="flex items-center gap-2.5 px-3 py-2 text-xs font-medium text-slate-400 hover:text-gold-400 rounded-lg hover:bg-slate-800/50 transition-colors">
            <i class="fa-solid fa-arrow-up-right-from-square w-4"></i>
            <span>Sapphire Website</span>
        </a>
        <a href="logout.php" class="flex items-center gap-2.5 px-3 py-2 text-xs font-medium text-rose-400 hover:text-rose-300 rounded-lg hover:bg-rose-500/10 transition-colors">
            <i class="fa-solid fa-right-from-bracket w-4"></i>
            <span>Sign Out</span>
        </a>
    </div>
</aside>

<!-- Main Content Wrapper -->
<div class="flex-1 flex flex-col overflow-hidden bg-slate-950">
    <!-- Top Mobile / Global Bar -->
    <header class="flex items-center justify-between px-4 sm:px-6 py-3.5 bg-slate-900/80 border-b border-slate-800/80 backdrop-blur-md">
        <div class="flex items-center gap-3">
            <button id="mobileMenuBtn" class="md:hidden p-2 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800">
                <i class="fa-solid fa-bars text-lg"></i>
            </button>
            <h1 class="text-lg font-semibold text-white font-serif"><?= htmlspecialchars($pageTitle ?? 'Overview') ?></h1>
        </div>

        <div class="flex items-center gap-3">
            <!-- Theme Toggle in Header -->
            <button onclick="toggleTheme()" class="p-2 rounded-xl bg-slate-800/60 hover:bg-slate-800 text-slate-300 hover:text-gold-400 border border-slate-700/60 transition-colors" title="Toggle Dark/Light Mode">
                <i id="headerThemeIcon" class="fa-solid fa-moon text-sm"></i>
            </button>

            <div class="hidden sm:flex items-center gap-2 text-xs text-slate-400 bg-slate-800/60 px-3 py-1.5 rounded-full border border-slate-700/50">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>System Online</span>
            </div>

            <a href="employee_portal.php" class="flex items-center gap-2 px-3 py-1.5 text-xs font-medium text-gold-400 bg-gold-500/10 hover:bg-gold-500/20 rounded-lg border border-gold-500/20 transition-colors">
                <i class="fa-solid fa-user-circle"></i>
                <span class="hidden sm:inline"><?= htmlspecialchars($user['calling_name'] ?? 'My Profile') ?></span>
            </a>
        </div>
    </header>

    <!-- Mobile Drawer Sidebar -->
    <div id="mobileDrawer" class="fixed inset-0 z-50 flex md:hidden hidden">
        <div id="mobileBackdrop" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm"></div>
        <div class="relative flex-1 flex flex-col max-w-xs w-full bg-slate-900 border-r border-slate-800 shadow-2xl">
            <div class="flex items-center justify-between p-4 border-b border-slate-800">
                <div class="flex items-center gap-2.5">
                    <img src="assets/logo.jpg" alt="Logo" class="w-8 h-8 rounded-full border border-gold-500 object-cover">
                    <span class="font-serif font-bold gold-gradient text-base">SAPPHIRE HR</span>
                </div>
                <button id="mobileCloseBtn" class="text-slate-400 hover:text-white p-2">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>
            <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
                <?php if ($isManager): ?>
                    <a href="dashboard.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm text-slate-300 hover:bg-slate-800">
                        <i class="fa-solid fa-chart-pie w-5"></i> Dashboard
                    </a>
                    <a href="attendance.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm text-slate-300 hover:bg-slate-800">
                        <i class="fa-solid fa-calendar-check w-5"></i> Daily Attendance
                    </a>
                    <a href="upload_attendance.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm text-slate-300 hover:bg-slate-800">
                        <i class="fa-solid fa-file-excel w-5 text-emerald-400"></i> Upload Machine Excel
                    </a>
                    <a href="employees.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm text-slate-300 hover:bg-slate-800">
                        <i class="fa-solid fa-users w-5"></i> Staff & Maternity
                    </a>
                    <a href="leaves.php" class="flex items-center justify-between px-3 py-2.5 rounded-lg text-sm text-slate-300 hover:bg-slate-800">
                        <span class="flex items-center gap-3"><i class="fa-solid fa-calendar-minus w-5"></i> Leaves</span>
                        <?php if ($pendingLeavesCount > 0): ?><span class="bg-rose-500 text-white text-xs px-2 py-0.5 rounded-full"><?= $pendingLeavesCount ?></span><?php endif; ?>
                    </a>
                    <a href="manual_punches.php" class="flex items-center justify-between px-3 py-2.5 rounded-lg text-sm text-slate-300 hover:bg-slate-800">
                        <span class="flex items-center gap-3"><i class="fa-solid fa-fingerprint w-5"></i> Manual Punches</span>
                        <?php if ($pendingPunchesCount > 0): ?><span class="bg-amber-500 text-slate-950 text-xs px-2 py-0.5 rounded-full"><?= $pendingPunchesCount ?></span><?php endif; ?>
                    </a>
                    <a href="reports.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm text-slate-300 hover:bg-slate-800">
                        <i class="fa-solid fa-file-invoice w-5"></i> Reports
                    </a>
                    <?php if ($isAdmin): ?>
                    <a href="settings.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm text-slate-300 hover:bg-slate-800">
                        <i class="fa-solid fa-sliders w-5"></i> Settings
                    </a>
                    <?php endif; ?>
                <?php endif; ?>
                <a href="employee_portal.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm text-gold-400 bg-gold-500/10">
                    <i class="fa-solid fa-id-badge w-5"></i> My Staff Portal
                </a>
                <button onclick="toggleTheme()" class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm text-slate-300 hover:bg-slate-800 mt-2">
                    <i class="fa-solid fa-circle-half-stroke w-5"></i> Switch Dark / Light Theme
                </button>
                <a href="logout.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm text-rose-400 hover:bg-rose-500/10 mt-3">
                    <i class="fa-solid fa-right-from-bracket w-5"></i> Sign Out
                </a>
            </nav>
        </div>
    </div>

    <!-- Main Scrollable Body Area -->
    <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
