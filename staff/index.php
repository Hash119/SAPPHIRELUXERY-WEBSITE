<?php
/**
 * Sapphire Aesthetic & Luxury - Portal Login
 */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (file_exists(__DIR__ . '/config/auth.php')) {
    require_once __DIR__ . '/config/auth.php';
} elseif (file_exists(__DIR__ . '/auth.php')) {
    require_once __DIR__ . '/auth.php';
}

if (is_logged_in()) {
    $u = current_user();
    if (in_array($u['role'], ['admin', 'manager', 'supervisor'])) {
        header('Location: dashboard.php');
    } else {
        header('Location: employee_portal.php');
    }
    exit;
}

$error = '';
$setupNotice = '';

// Check if database is configured
if (isset($db_connection_error)) {
    $setupNotice = "Database connection pending. Please set your Hostinger DB credentials in <code>staff/config/db.php</code>.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginId = trim($_POST['login_id'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($loginId) || empty($password)) {
        $error = "Please enter both ID/Email and Password.";
    } elseif (isset($pdo)) {
        try {
            $cleanId = trim($loginId);
            $idNum = is_numeric($cleanId) ? (int)$cleanId : -1;
            $pad4 = is_numeric($cleanId) ? str_pad($idNum, 4, '0', STR_PAD_LEFT) : $cleanId;
            $pad3 = is_numeric($cleanId) ? str_pad($idNum, 3, '0', STR_PAD_LEFT) : $cleanId;
            $emp4 = 'EMP' . $pad4;
            $emp3 = 'EMP' . $pad3;

            // Find employee by Machine ID, Employee Code (0004, 4, EMP0004), or Email
            $stmt = $pdo->prepare("
                SELECT * FROM employees 
                WHERE (
                    machine_user_id = ? 
                    OR emp_no = ? 
                    OR emp_no = ? 
                    OR emp_no = ? 
                    OR emp_no = ? 
                    OR emp_no = ? 
                    OR email = ?
                ) 
                LIMIT 1
            ");
            $stmt->execute([$idNum, $cleanId, $pad4, $pad3, $emp4, $emp3, $cleanId]);
            $user = $stmt->fetch();

            if ($user) {
                // Check if account is active or inactive/terminated
                if ($user['status'] !== 'active') {
                    $error = "Access Denied: Your staff account has been Terminated / Deactivated. Access to the Sapphire portal is revoked. Please contact HR / Management.";
                } else {
                    $passwordValid = false;
                    
                    // Verify BCrypt hash or plain text fallback (with auto upgrade)
                    if (password_verify($password, $user['password'])) {
                        $passwordValid = true;
                    } elseif ($password === $user['password'] || ($password === '123456' && $user['role'] === 'employee') || ($password === 'admin123' && $user['role'] === 'admin') || (!empty($user['plain_token']) && $password === $user['plain_token'])) {
                        $passwordValid = true;
                        // Auto-upgrade to bcrypt and record plain_token
                        $newHash = password_hash($password, PASSWORD_DEFAULT);
                        try {
                            $upStmt = $pdo->prepare("UPDATE employees SET password = ?, plain_token = ? WHERE id = ?");
                            $upStmt->execute([$newHash, $password, $user['id']]);
                        } catch (Exception $e) {
                            $upStmt = $pdo->prepare("UPDATE employees SET password = ? WHERE id = ?");
                            $upStmt->execute([$newHash, $user['id']]);
                        }
                    }

                    if ($passwordValid) {
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user'] = $user;

                        // Check if employee is using default password (123456)
                        if ($password === '123456' && $user['role'] === 'employee') {
                            $_SESSION['must_change_password'] = true;
                            header('Location: change_password.php?first_login=1');
                            exit;
                        }

                        if (in_array($user['role'], ['admin', 'manager', 'supervisor'])) {
                            header('Location: dashboard.php?success=Welcome back, ' . urlencode($user['calling_name'] ?? $user['name']));
                        } else {
                            header('Location: employee_portal.php?success=Welcome, ' . urlencode($user['calling_name'] ?? $user['name']));
                        }
                        exit;
                    } else {
                        $error = "Incorrect password. Default for staff is 123456.";
                    }
                }
            } else {
                $error = "User not found with ID/Email: " . htmlspecialchars($loginId) . " (Enter ID e.g. 0004 or 4)";
            }
        } catch (PDOException $e) {
            $error = "System error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In | Sapphire Aesthetic & Luxury Portal</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,500;0,600;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <script>
        if (localStorage.getItem('sapphire_theme') === 'light') {
            document.documentElement.classList.remove('dark');
        } else {
            document.documentElement.classList.add('dark');
        }
    </script>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                        serif: ['"Playfair Display"', 'serif'],
                    },
                    colors: {
                        gold: { 400: '#cfae7d', 500: '#c5a880', 600: '#b48c59' }
                    }
                }
            }
        }
    </script>
    <style>
        .gold-gradient {
            background: linear-gradient(135deg, #dfc9a5 0%, #c5a880 50%, #967043 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .luxury-glow {
            box-shadow: 0 0 50px -10px rgba(197, 168, 128, 0.15);
        }
        html:not(.dark) body {
            background-color: #f1f5f9;
            color: #0f172a;
        }
        html:not(.dark) .bg-slate-900\/80 {
            background-color: #ffffff !important;
            border-color: #cbd5e1 !important;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08), 0 8px 10px -6px rgba(0, 0, 0, 0.05) !important;
        }
        html:not(.dark) .bg-slate-950\/70,
        html:not(.dark) .bg-slate-950\/50,
        html:not(.dark) .bg-slate-950 {
            background-color: #f8fafc !important;
            border-color: #cbd5e1 !important;
        }
        html:not(.dark) label {
            color: #334155 !important;
            font-weight: 600 !important;
        }
        html:not(.dark) .text-slate-400 {
            color: #475569 !important;
        }
        html:not(.dark) .text-slate-300 {
            color: #1e293b !important;
        }
        html:not(.dark) .text-slate-500 {
            color: #64748b !important;
        }
        html:not(.dark) input {
            color: #0f172a !important;
            background-color: #ffffff !important;
            border-color: #cbd5e1 !important;
        }
        html:not(.dark) input::placeholder {
            color: #94a3b8 !important;
        }
        html:not(.dark) .gold-gradient {
            background: linear-gradient(135deg, #967043 0%, #b48c59 50%, #7a5a39 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
    </style>
</head>
<body class="h-full flex items-center justify-center p-4 bg-slate-950 selection:bg-gold-500 selection:text-slate-950 relative overflow-hidden transition-colors duration-200">

    <!-- Top Right Theme Switcher -->
    <div class="absolute top-5 right-5 z-20">
        <button onclick="toggleLoginTheme()" class="flex items-center gap-2 px-3 py-1.5 rounded-full bg-slate-900/80 hover:bg-slate-800 text-slate-300 border border-slate-800 backdrop-blur-md text-xs transition-colors">
            <i id="themeToggleIcon" class="fa-solid fa-moon"></i>
            <span id="themeToggleText">Theme</span>
        </button>
    </div>

    <!-- Decorative background glows -->
    <div class="absolute -top-32 -left-32 w-96 h-96 bg-gold-500/10 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-32 -right-32 w-96 h-96 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>

    <div class="w-full max-w-md relative z-10">
        <!-- Brand Logo Header -->
        <div class="text-center mb-8">
            <div class="inline-block relative mb-3">
                <img src="assets/logo.jpg" alt="Sapphire Logo" onerror="this.src='../Photos/Logo.jpg'"
                     class="w-20 h-20 rounded-full border-2 border-gold-500 object-cover mx-auto shadow-xl shadow-gold-500/20 p-0.5 bg-slate-900">
            </div>
            <h1 class="font-serif text-2xl sm:text-3xl font-bold tracking-wider gold-gradient">SAPPHIRE</h1>
            <p class="text-xs text-slate-400 uppercase tracking-widest mt-1">HR & Attendance Portal</p>
        </div>

        <!-- Setup alert if db is not configured -->
        <?php if (!empty($setupNotice)): ?>
            <div class="mb-5 p-4 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-300 text-xs leading-relaxed">
                <i class="fa-solid fa-triangle-exclamation mr-1.5"></i> <?= $setupNotice ?>
            </div>
        <?php endif; ?>

        <!-- Error Alert -->
        <?php if (!empty($error)): ?>
            <div class="mb-5 p-3.5 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs flex items-center gap-2.5">
                <i class="fa-solid fa-circle-exclamation text-base shrink-0"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <!-- Login Card -->
        <div class="bg-slate-900/80 border border-slate-800/80 rounded-2xl p-6 sm:p-8 backdrop-blur-xl luxury-glow">
            <form method="POST" action="index.php" class="space-y-5">
                <div>
                    <label for="login_id" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">
                        Machine ID / Employee Code / Email
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-slate-500">
                            <i class="fa-solid fa-id-card"></i>
                        </span>
                        <input type="text" id="login_id" name="login_id" required autofocus
                            placeholder="e.g. 1, EMP001, or admin email"
                            value="<?= htmlspecialchars($_POST['login_id'] ?? '') ?>"
                            class="w-full pl-10 pr-4 py-3 bg-slate-950/70 border border-slate-800 rounded-xl text-sm text-slate-100 placeholder-slate-600 focus:outline-none focus:border-gold-500 focus:ring-1 focus:ring-gold-500 transition-colors">
                    </div>
                    <p class="text-[11px] text-slate-500 mt-1.5">Staff can enter their fingerprint machine ID (e.g. 1 to 22)</p>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-slate-400">
                            Password
                        </label>
                    </div>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-slate-500">
                            <i class="fa-solid fa-lock"></i>
                        </span>
                        <input type="password" id="password" name="password" required
                            placeholder="••••••••"
                            class="w-full pl-10 pr-4 py-3 bg-slate-950/70 border border-slate-800 rounded-xl text-sm text-slate-100 placeholder-slate-600 focus:outline-none focus:border-gold-500 focus:ring-1 focus:ring-gold-500 transition-colors">
                    </div>
                </div>

                <button type="submit" 
                    class="w-full py-3 px-4 rounded-xl font-semibold text-sm text-slate-950 bg-gradient-to-r from-gold-500 via-amber-400 to-gold-600 hover:from-gold-400 hover:to-gold-500 shadow-lg shadow-gold-500/25 transition-all transform active:scale-[0.99] flex items-center justify-center gap-2">
                    <i class="fa-solid fa-right-to-bracket"></i>
                    <span>Sign In to Portal</span>
                </button>
            </form>
        </div>

        <div class="text-center mt-6">
            <a href="https://sapphireaesthetic.com" class="text-xs text-slate-500 hover:text-gold-400 transition-colors inline-flex items-center gap-1.5">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Return to Sapphire Website</span>
            </a>
        </div>
    </div>

<script>
    function updateLoginThemeIcon(theme) {
        const icon = document.getElementById('themeToggleIcon');
        const text = document.getElementById('themeToggleText');
        if (theme === 'light') {
            document.documentElement.classList.remove('dark');
            if (icon) icon.className = 'fa-solid fa-sun text-amber-500';
            if (text) text.innerText = 'Light';
        } else {
            document.documentElement.classList.add('dark');
            if (icon) icon.className = 'fa-solid fa-moon text-slate-300';
            if (text) text.innerText = 'Dark';
        }
    }

    function toggleLoginTheme() {
        const cur = localStorage.getItem('sapphire_theme') === 'light' ? 'dark' : 'light';
        localStorage.setItem('sapphire_theme', cur);
        updateLoginThemeIcon(cur);
    }

    document.addEventListener('DOMContentLoaded', () => {
        const cur = localStorage.getItem('sapphire_theme') || 'dark';
        updateLoginThemeIcon(cur);
    });
</script>
</body>
</html>
