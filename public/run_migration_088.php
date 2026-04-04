<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();
$sqls = array(
    "CREATE TABLE IF NOT EXISTS dispatch_worker_availability (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        dispatch_worker_id INT UNSIGNED NOT NULL COMMENT '點工人員ID',
        available_date DATE NOT NULL COMMENT '可上工日期',
        registered_by INT UNSIGNED NOT NULL COMMENT '登錄人',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_worker_date (dispatch_worker_id, available_date),
        INDEX idx_date (available_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='點工人員可上工日期登錄'",
);
foreach ($sqls as $sql) {
    try {
        $db->exec($sql);
        echo "[OK] " . substr($sql, 0, 60) . "...\n";
    } catch (Exception $e) {
        echo "[SKIP] " . $e->getMessage() . "\n";
    }
}
echo "\nDone!\n";
