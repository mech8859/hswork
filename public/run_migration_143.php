<?php
/**
 * Migration 143：attendance_settings 加 cron_token 欄位
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();
try {
    $col = $db->query("SHOW COLUMNS FROM attendance_settings LIKE 'cron_token'")->fetch();
    if ($col) {
        echo "[skip] cron_token 已存在\n";
    } else {
        $db->exec("ALTER TABLE attendance_settings ADD COLUMN cron_token VARCHAR(64) DEFAULT NULL COMMENT '排程同步用 token'");
        echo "OK: cron_token 已新增\n";
    }
    AuditLog::log('attendance', 'migration', 0, 'Migration 143: cron_token');
    echo "Migration 143 done.\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
