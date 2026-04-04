<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();
$sqls = array(
    "ALTER TABLE dispatch_attendance MODIFY COLUMN status ENUM('present','absent','cancelled','scheduled') NOT NULL DEFAULT 'present' COMMENT '出勤狀態: present=出勤, absent=未到, cancelled=取消, scheduled=預排'",
    "ALTER TABLE schedules ADD COLUMN ragic_calendar_id VARCHAR(50) DEFAULT NULL COMMENT 'Ragic 行事曆來源ID'",
    "ALTER TABLE schedules ADD COLUMN ragic_calendar_branch VARCHAR(20) DEFAULT NULL COMMENT 'Ragic 行事曆分公司代碼'",
);
foreach ($sqls as $sql) {
    try {
        $db->exec($sql);
        echo "[OK] $sql\n";
    } catch (Exception $e) {
        echo "[SKIP] " . $e->getMessage() . "\n";
    }
}
echo "\nDone!\n";
