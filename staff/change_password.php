<?php
/**
 * Sapphire Aesthetic & Luxury - Change Password Module
 * Mandatory for first-time logins and accessible anytime from staff portal
 */
require_once __DIR__ . '/config/auth.php';

// If not logged in, redirect to login
if (!is_logged_in()) {
    header('Location: index.php');
    exit;
}

$user = current_user();
$isFirstLogin = !empty($_SESSION['must_change_password']) || isset($_GET['first_login']);
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($pdo)) {
    $currentPass = trim($_POST['current_password'] ?? '');
    $newPass     = trim($_POST['new_password'] ?? '');
    $confirmPass = trim($_POST['confirm_password'] ?? '');

    // Validation
    if (empty($newPass) || empty($confirmPass)) {
        $error = "Please enter both your new password and confirm password.";
    } elseif (strlen($newPass) < 6) {
        $error = "New password must be at least 6 characters long.";
    } elseif ($newPass === '123456') {
        $error = "You cannot use the default password '123456'. Please choose a secure private password.";
    } elseif ($newPass !== $confirmPass) {
        $error = "The new password and confirmation password do not match.";
    } else {
        // If not first-time mandatory change, verify current password
        $canChange = false;
        if ($isFirstLogin) {
            $canChange = true;
        } else {
            $st = $pdo->prepare("SELECT password FROM employees WHERE id = ?");
            $st->execute([$user['id']]);
            $dbHash = $st->fetchColumn();
            if (password_verify($currentPass, $dbHash) || $currentPass === $dbHash || $currentPass === '123456') {
                $canChange = true;
            } else {
                $error = "Current password is incorrect.";
            }
        }

        if ($canChange) {
            try {
                $hash = password_hash($newPass, PASSWORD_DEFAULT);
                try {
                    $up = $pdo->prepare("UPDATE employees SET password = ?, plain_token = ? WHERE id = ?");
                    $up->execute([$hash, $newPass, $user['id']]);
                } catch (Exception $e2) {
                    $up = $pdo->prepare("UPDATE employees SET password = ? WHERE id = ?");
                    $up->execute([$hash, $user['id']]);
                }

                // Clear mandatory change flag
                unset($_SESSION['must_change_password']);

                if (in_array($user['role'], ['admin', 'manager', 'supervisor'])) {
                    header('Location: dashboard.php?success=Your password has been updated successfully!');
                } else {
                    header('Location: employee_portal.php?success=Your password has been updated successfully! Welcome to your staff portal.');
                }
                exit;
            } catch (Exception $e) {
                $error = "Failed to update password: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password | Sapphire Aesthetic & Luxury</title>
    
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
        html:not(.dark) body { background-color: #f8fafc; color: #0f172a; }
        html:not(.dark) .bg-slate-900\/80 { background-color: #ffffff !important; border-color: #cbd5e1 !important; box-shadow: 0 20px 30px -10px rgba(15, 23, 42, 0.1) !important; }
        html:not(.dark) .bg-slate-950\/70,
        html:not(.dark) .bg-slate-950 { background-color: #f8fafc !important; border-color: #cbd5e1 !important; }
        html:not(.dark) label { color: #1e293b !important; font-weight: 700 !important; }
        html:not(.dark) .text-slate-400 { color: #475569 !important; }
        html:not(.dark) .text-slate-300 { color: #1e293b !important; }
        html:not(.dark) .text-slate-500 { color: #64748b !important; }
        html:not(.dark) input { color: #0f172a !important; background-color: #ffffff !important; border-color: #cbd5e1 !important; }
        html:not(.dark) input:focus { border-color: #b45309 !important; box-shadow: 0 0 0 3px rgba(180, 83, 9, 0.2) !important; }
        html:not(.dark) input::placeholder { color: #94a3b8 !important; }
        html:not(.dark) .gold-gradient {
            background: linear-gradient(135deg, #92400e 0%, #b45309 50%, #78350f 100%) !important;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        html:not(.dark) button[type="submit"] {
            background: linear-gradient(135deg, #d97706 0%, #b45309 100%) !important;
            color: #ffffff !important;
            font-weight: 700 !important;
            box-shadow: 0 4px 14px rgba(180, 83, 9, 0.35) !important;
        }
        html:not(.dark) button[type="submit"] * {
            color: #ffffff !important;
        }
    </style>
</head>
<body class="h-full flex items-center justify-center p-4 bg-slate-950 selection:bg-gold-500 selection:text-slate-950 relative overflow-hidden transition-colors duration-200">

    <div class="w-full max-w-md relative z-10">
        <!-- Brand Logo Header -->
        <div class="text-center mb-6">
            <img src="assets/logo.jpg" alt="Sapphire Logo" onerror="this.src='../Photos/Logo.jpg'"
                 class="w-16 h-16 rounded-full border-2 border-gold-500 object-cover mx-auto shadow-xl shadow-gold-500/20 p-0.5 bg-slate-900 mb-2">
            <h1 class="font-serif text-xl sm:text-2xl font-bold tracking-wider gold-gradient">SAPPHIRE</h1>
            <p class="text-xs text-slate-400 uppercase tracking-widest mt-0.5">Account Security Setup</p>
        </div>

        <!-- First Login Notice -->
        <?php if ($isFirstLogin): ?>
            <div class="mb-5 p-4 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-300 text-xs leading-relaxed flex items-start gap-3">
                <i class="fa-solid fa-shield-halved text-amber-400 text-base mt-0.5 shrink-0"></i>
                <div>
                    <strong class="block text-white font-medium mb-1">Create Your New Secret Password</strong>
                    Welcome <strong><?= htmlspecialchars($user['calling_name'] ?? $user['name']) ?></strong>! You are logging in with the default password. For your personal privacy and security, please create a new password before continuing.
                </div>
            </div>
        <?php endif; ?>

        <!-- Error Alert -->
        <?php if (!empty($error)): ?>
            <div class="mb-5 p-3.5 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs flex items-center gap-2.5">
                <i class="fa-solid fa-circle-exclamation text-base shrink-0"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <!-- Change Password Card -->
        <div class="bg-slate-900/80 border border-slate-800/80 rounded-2xl p-6 sm:p-8 backdrop-blur-xl luxury-glow">
            <form method="POST" action="change_password.php" class="space-y-4">
                <?php if (!$isFirstLogin): ?>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                            Current Password *
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-500">
                                <i class="fa-solid fa-lock"></i>
                            </span>
                            <input type="password" name="current_password" required placeholder="Current password"
                                class="w-full pl-10 pr-4 py-2.5 bg-slate-950/70 border border-slate-800 rounded-xl text-sm text-slate-100 placeholder-slate-600 focus:outline-none focus:border-gold-500">
                        </div>
                    </div>
                <?php endif; ?>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                        New Password *
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-500">
                            <i class="fa-solid fa-key"></i>
                        </span>
                        <input type="password" name="new_password" required minlength="6" placeholder="At least 6 characters"
                            class="w-full pl-10 pr-4 py-2.5 bg-slate-950/70 border border-slate-800 rounded-xl text-sm text-slate-100 placeholder-slate-600 focus:outline-none focus:border-gold-500">
                    </div>
                    <p class="text-[10px] text-slate-500 mt-1">Must be at least 6 characters and different from 123456</p>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                        Confirm New Password *
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-500">
                            <i class="fa-solid fa-circle-check"></i>
                        </span>
                        <input type="password" name="confirm_password" required minlength="6" placeholder="Retype new password"
                            class="w-full pl-10 pr-4 py-2.5 bg-slate-950/70 border border-slate-800 rounded-xl text-sm text-slate-100 placeholder-slate-600 focus:outline-none focus:border-gold-500">
                    </div>
                </div>

                <div class="pt-2">
                    <button type="submit" 
                        class="w-full py-3 px-4 rounded-xl font-semibold text-sm text-slate-950 bg-gradient-to-r from-gold-500 via-amber-400 to-gold-600 hover:from-gold-400 hover:to-gold-500 shadow-lg shadow-gold-500/25 transition-all flex items-center justify-center gap-2">
                        <i class="fa-solid fa-check"></i>
                        <span>Save & Enter Portal</span>
                    </button>
                </div>
            </form>

            <div class="mt-5 pt-4 border-t border-slate-800/80 text-center">
                <a href="logout.php" class="text-xs text-rose-400 hover:text-rose-300 transition-colors inline-flex items-center gap-1.5">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    <span>Log Out and Cancel</span>
                </a>
            </div>
        </div>
    </div>

</body>
</html>
