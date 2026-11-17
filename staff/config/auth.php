<?php
/**
 * Sapphire Aesthetic & Luxury - Session and Authentication Helpers
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function current_user() {
    if (!is_logged_in()) return null;
    return $_SESSION['user'] ?? null;
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: index.php');
        exit;
    }
    // Enforce mandatory password change if still on default password
    $curPage = basename($_SERVER['PHP_SELF'] ?? '');
    if (!empty($_SESSION['must_change_password']) && $curPage !== 'change_password.php' && $curPage !== 'logout.php') {
        header('Location: change_password.php');
        exit;
    }
}

function require_admin() {
    require_login();
    $user = current_user();
    if (!$user || $user['role'] !== 'admin') {
        header('Location: dashboard.php?error=unauthorized');
        exit;
    }
}

function can_approve_leave($user = null) {
    if (!$user) $user = current_user();
    if (!$user) return false;
    if ($user['role'] === 'admin') return true;
    return !empty($user['can_approve_leave']) && $user['can_approve_leave'] == 1;
}

function can_approve_manual_punch($user = null) {
    if (!$user) $user = current_user();
    if (!$user) return false;
    if ($user['role'] === 'admin') return true;
    return !empty($user['can_approve_manual_punch']) && $user['can_approve_manual_punch'] == 1;
}

function is_admin_or_manager($user = null) {
    if (!$user) $user = current_user();
    if (!$user) return false;
    return in_array($user['role'], ['admin', 'manager', 'supervisor']);
}
