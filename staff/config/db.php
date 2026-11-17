<?php
/**
 * Sapphire Aesthetic & Luxury - Database Connection Configuration
 */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('DB_HOST', 'localhost');
define('DB_NAME', 'u968226160_sapphire_hrm'); // Enter your Hostinger Database Name here
define('DB_USER', 'u968226160_shrm_admin');     // Enter your Hostinger MySQL Username here
define('DB_PASS', 'D3m4fSn5@S@');//entern your Hostinger MySQL Password here
define('DB_CHARSET', 'utf8mb4');

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => true,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

    // Auto-migrate plain_token column if not yet present
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM employees LIKE 'plain_token'")->fetchAll();
        if (empty($cols)) {
            $pdo->exec("ALTER TABLE employees ADD COLUMN plain_token VARCHAR(255) NULL DEFAULT '123456' AFTER password");
        }
    } catch (Exception $ign) {}

    // Ensure Sri Lanka statutory & Poya holidays table exists
    require_once __DIR__ . '/../includes/holidays.php';
    ensure_holidays_table($pdo);
} catch (PDOException $e) {
    // If running in development or before configuring Hostinger credentials, show a friendly setup message
    $db_connection_error = $e->getMessage();
}

/**
 * Get system setting by key
 */
function get_setting($key, $default = '') {
    global $pdo;
    if (!isset($pdo)) return $default;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return $val !== false ? $val : $default;
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * Format minutes into readable "X hrs Y mins" or "X mins"
 */
function format_minutes($total_mins) {
    $total_mins = intval($total_mins);
    if ($total_mins <= 0) return '0 min';
    $hrs = floor($total_mins / 60);
    $mins = $total_mins % 60;
    if ($hrs > 0 && $mins > 0) return "{$hrs}h {$mins}m";
    if ($hrs > 0) return "{$hrs}h";
    return "{$mins}m";
}

/**
 * Format time to 12-hour AM/PM format (e.g. 09:30 AM)
 */
function format_time_12h($time_str) {
    if (!$time_str || $time_str == '00:00:00') return '-';
    $t = strtotime($time_str);
    return $t ? date('h:i A', $t) : $time_str;
}
