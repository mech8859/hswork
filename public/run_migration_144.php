<?php
/**
 * Migration 144：attendance_settings 加 last_notified_at（防止失敗通知洗版）
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();
try {
    $col = $db->query("SHOW COLUMNS FROM attendance_settings LIKE 'last_notified_at'")->fetch();
    if ($col) {
        echo "[skip] last_notified_at 已存在\n";
    } else {
        $db->exec("ALTER TABLE attendance_settings ADD COLUMN last_notified_at DATETIME DEFAULT NULL COMMENT '上次發失敗通知時間'");
        echo "OK: last_notified_at 已新增\n";
    }
    AuditLog::log('attendance', 'migration', 0, 'Migration 144: last_notified_at');
    echo "Migration 144 done.\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
