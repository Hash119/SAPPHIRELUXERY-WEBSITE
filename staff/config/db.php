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

    // Auto-migrate employment_type (confirmed / probation), appointment_date & custom leave overrides
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM employees LIKE 'employment_type'")->fetchAll();
        if (empty($cols)) {
            $pdo->exec("ALTER TABLE employees ADD COLUMN employment_type ENUM('confirmed', 'probation') NOT NULL DEFAULT 'confirmed' AFTER designation");
        }
        $colsProb = $pdo->query("SHOW COLUMNS FROM employees LIKE 'probation_months'")->fetchAll();
        if (empty($colsProb)) {
            $pdo->exec("ALTER TABLE employees ADD COLUMN probation_months INT NOT NULL DEFAULT 6 AFTER employment_type");
            $pdo->exec("ALTER TABLE employees ADD COLUMN probation_end_date DATE NULL AFTER probation_months");
        }
        $colsApp = $pdo->query("SHOW COLUMNS FROM employees LIKE 'appointment_date'")->fetchAll();
        if (empty($colsApp)) {
            $pdo->exec("ALTER TABLE employees ADD COLUMN appointment_date DATE NULL AFTER join_date");
        }
        $colsCust = $pdo->query("SHOW COLUMNS FROM employees LIKE 'custom_leave_enabled'")->fetchAll();
        if (empty($colsCust)) {
            $pdo->exec("ALTER TABLE employees ADD COLUMN custom_leave_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER appointment_date");
            $pdo->exec("ALTER TABLE employees ADD COLUMN custom_annual_quota DECIMAL(4,1) NULL DEFAULT 14.0 AFTER custom_leave_enabled");
            $pdo->exec("ALTER TABLE employees ADD COLUMN custom_casual_quota DECIMAL(4,1) NULL DEFAULT 7.0 AFTER custom_annual_quota");
            $pdo->exec("ALTER TABLE employees ADD COLUMN custom_monthly_quota DECIMAL(4,1) NULL DEFAULT 4.0 AFTER custom_casual_quota");
            $pdo->exec("ALTER TABLE employees ADD COLUMN custom_medical_quota DECIMAL(4,1) NULL DEFAULT 14.0 AFTER custom_monthly_quota");
        }
    } catch (Exception $ign) {}

    // Auto-migrate leaves table leave_type enum to include 'Monthly'
    try {
        $pdo->exec("ALTER TABLE leaves MODIFY COLUMN leave_type ENUM('Monthly', 'Annual', 'Casual', 'Medical', 'Maternity', 'Half Day', 'Short Leave', 'No Pay') NOT NULL");
    } catch (Exception $ign) {}

    // Auto-migrate leave_balances table to include monthly_quota & monthly_used
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM leave_balances LIKE 'monthly_quota'")->fetchAll();
        if (empty($cols)) {
            $pdo->exec("ALTER TABLE leave_balances ADD COLUMN monthly_quota DECIMAL(4,1) NOT NULL DEFAULT 4.0 AFTER casual_used");
            $pdo->exec("ALTER TABLE leave_balances ADD COLUMN monthly_used DECIMAL(4,1) NOT NULL DEFAULT 0.0 AFTER monthly_quota");
        }
    } catch (Exception $ign) {}

    // Ensure Sri Lanka statutory & Poya holidays table exists
    require_once __DIR__ . '/../includes/holidays.php';
    ensure_holidays_table($pdo);

    // Dynamic Leave Calculator
    require_once __DIR__ . '/../includes/leave_calculator.php';
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
