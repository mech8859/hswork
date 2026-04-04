<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();
$sqls = array(
    "CREATE TABLE IF NOT EXISTS dispatch_engineer_pairs (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        dispatch_worker_id INT UNSIGNED NOT NULL COMMENT '點工人員ID',
        user_id INT UNSIGNED NOT NULL COMMENT '工程師ID',
        compatibility TINYINT UNSIGNED NOT NULL DEFAULT 3 COMMENT '配對評分 1-5',
        note TEXT DEFAULT NULL COMMENT '備註',
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_pair (dispatch_worker_id, user_id),
        INDEX idx_user (user_id),
        CONSTRAINT chk_compat CHECK (compatibility BETWEEN 1 AND 5)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='點工人員與工程師配對表'",
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
