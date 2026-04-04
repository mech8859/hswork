<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();
$sqls = array(
    // 確保 dispatch_attendance 有 schedule_id 欄位
    "ALTER TABLE dispatch_attendance ADD COLUMN schedule_id INT UNSIGNED NULL COMMENT '關聯排工ID' AFTER dispatch_worker_id",
    "ALTER TABLE dispatch_attendance ADD INDEX idx_schedule (schedule_id)",
    // 確保 status ENUM 包含 scheduled
    "ALTER TABLE dispatch_attendance MODIFY COLUMN status ENUM('present','absent','cancelled','scheduled') NOT NULL DEFAULT 'present' COMMENT '出勤狀態'",
);
foreach ($sqls as $sql) {
    try {
        $db->exec($sql);
        echo "[OK] " . substr($sql, 0, 80) . "...\n";
    } catch (Exception $e) {
        echo "[SKIP] " . $e->getMessage() . "\n";
    }
}
echo "\nDone!\n";
