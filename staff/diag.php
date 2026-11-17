<?php
/**
 * Sapphire Aesthetic & Luxury - System Diagnostic & Self-Test
 */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$configFile = file_exists(__DIR__ . '/config/db.php') ? __DIR__ . '/config/db.php' : (file_exists(__DIR__ . '/db.php') ? __DIR__ . '/db.php' : null);
if ($configFile) {
    require_once $configFile;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sapphire HRMS - System Self Test</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #090d16; color: #e2e8f0; padding: 40px 20px; line-height: 1.6; }
        .card { max-width: 650px; margin: 0 auto; background: #0f172a; border: 1px solid #1e293b; border-radius: 16px; padding: 32px; box-shadow: 0 20px 40px rgba(0,0,0,0.4); }
        h1 { margin-top: 0; font-size: 20px; color: #f8fafc; border-bottom: 1px solid #1e293b; padding-bottom: 16px; }
        .check { display: flex; align-items: flex-start; gap: 12px; padding: 12px 0; border-bottom: 1px solid #1e293b; font-size: 14px; }
        .ok { color: #10b981; font-weight: bold; }
        .bad { color: #f43f5e; font-weight: bold; }
        .warn { color: #f59e0b; font-weight: bold; }
        .btn { display: inline-block; background: #c5a880; color: #090d16; font-weight: bold; padding: 10px 20px; border-radius: 8px; text-decoration: none; margin-top: 20px; }
        code { background: #020617; padding: 2px 6px; border-radius: 4px; color: #fbbf24; font-size: 13px; }
    </style>
</head>
<body>
<div class="card">
    <h1>🔍 Sapphire HRMS Diagnostic Check</h1>

    <!-- 1. PHP Version -->
    <div class="check">
        <span class="ok">✅</span>
        <div><strong>PHP Version:</strong> <?= phpversion() ?> (Required: 7.4 or higher)</div>
    </div>

    <!-- 2. Config File -->
    <div class="check">
        <?php if ($configFile): ?>
            <span class="ok">✅</span>
            <div><strong>Config File:</strong> Found at <code><?= htmlspecialchars($configFile) ?></code></div>
        <?php else: ?>
            <span class="bad">❌</span>
            <div><strong>Config File:</strong> Missing! Neither <code>config/db.php</code> nor <code>db.php</code> found. Make sure folders were extracted properly.</div>
        <?php endif; ?>
    </div>

    <!-- 3. Database Credentials -->
    <div class="check">
        <div>
            <strong>Configured DB Details:</strong><br>
            Host: <code><?= defined('DB_HOST') ? DB_HOST : 'N/A' ?></code><br>
            Database: <code><?= defined('DB_NAME') ? DB_NAME : 'N/A' ?></code><br>
            User: <code><?= defined('DB_USER') ? DB_USER : 'N/A' ?></code><br>
            Password: <code><?= defined('DB_PASS') ? (strlen(DB_PASS) > 0 ? '•••••••• (' . strlen(DB_PASS) . ' chars)' : 'EMPTY!') : 'N/A' ?></code>
        </div>
    </div>

    <!-- 4. MySQL Connection Test -->
    <div class="check">
        <?php if (isset($pdo) && $pdo): ?>
            <span class="ok">✅</span>
            <div>
                <strong style="color: #10b981;">Database Connected Successfully!</strong><br>
                <?php
                try {
                    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                    echo "Found " . count($tables) . " tables in database: <code>" . implode(', ', $tables) . "</code><br>";
                    if (in_array('employees', $tables)) {
                        $empCnt = $pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn();
                        echo "<span class='ok'>✅ Employees Table Ready: {$empCnt} employees registered.</span>";
                    } else {
                        echo "<span class='bad'>❌ 'employees' table not found! Please import database.sql via phpMyAdmin.</span>";
                    }
                } catch (Exception $ex) {
                    echo "<span class='bad'>Error querying tables: " . $ex->getMessage() . "</span>";
                }
                ?>
            </div>
        <?php else: ?>
            <span class="bad">❌</span>
            <div>
                <strong style="color: #f43f5e;">Database Connection Failed!</strong><br>
                <div style="background: rgba(244, 63, 94, 0.1); border: 1px solid rgba(244, 63, 94, 0.3); padding: 10px; border-radius: 8px; margin-top: 6px; font-size: 13px;">
                    <?= htmlspecialchars($db_connection_error ?? 'Unknown error') ?>
                </div>
                <p style="font-size: 12px; color: #94a3b8; margin-top: 8px;">
                    👉 <strong>How to fix:</strong> Open Hostinger <strong>hPanel -> Databases -> MySQL Databases</strong>. Copy the exact <strong>Database name</strong> and <strong>Username</strong> created there and paste into <code>config/db.php</code>.
                </p>
            </div>
        <?php endif; ?>
    </div>

    <div style="text-align: center; margin-top: 24px;">
        <a href="index.php" class="btn">Go to Portal Login &rarr;</a>
    </div>
</div>
</body>
</html>
