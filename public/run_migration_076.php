<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();
$sqls = array(
    "CREATE TABLE IF NOT EXISTS dispatch_worker_skills (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        dispatch_worker_id INT UNSIGNED NOT NULL,
        skill_id INT UNSIGNED NOT NULL,
        proficiency TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '熟練度 1-5, 0=不適用',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_worker_skill (dispatch_worker_id, skill_id),
        INDEX idx_skill (skill_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='點工人員技能表'",
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
