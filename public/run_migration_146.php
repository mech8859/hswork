<?php
/**
 * Migration 146：attendance_employees 加 work_start_time / work_end_time（每員工上下班時間）
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();
try {
    $col1 = $db->query("SHOW COLUMNS FROM attendance_employees LIKE 'work_start_time'")->fetch();
    if ($col1) {
        echo "[skip] work_start_time 已存在\n";
    } else {
        $db->exec("ALTER TABLE attendance_employees ADD COLUMN work_start_time TIME DEFAULT NULL COMMENT '上班時間（NULL=預設 08:00）'");
        echo "OK: work_start_time 已新增\n";
    }
    $col2 = $db->query("SHOW COLUMNS FROM attendance_employees LIKE 'work_end_time'")->fetch();
    if ($col2) {
        echo "[skip] work_end_time 已存在\n";
    } else {
        $db->exec("ALTER TABLE attendance_employees ADD COLUMN work_end_time TIME DEFAULT NULL COMMENT '下班時間（NULL=預設 17:30）'");
        echo "OK: work_end_time 已新增\n";
    }
    AuditLog::log('attendance', 'migration', 0, 'Migration 146: per-employee work hours');
    echo "Migration 146 done.\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
