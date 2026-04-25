<?php
/**
 * Migration 134: 案件狀態變更歷史 + 案件更新進度報表隱藏表
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();

try {
    $db->exec("CREATE TABLE IF NOT EXISTS case_status_history (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        case_id INT UNSIGNED NOT NULL,
        old_status VARCHAR(50) DEFAULT NULL,
        new_status VARCHAR(50) DEFAULT NULL,
        old_sub_status VARCHAR(50) DEFAULT NULL,
        new_sub_status VARCHAR(50) DEFAULT NULL,
        changed_by INT UNSIGNED DEFAULT NULL,
        changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_case (case_id, changed_at),
        KEY idx_changed_at (changed_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='案件狀態變更歷史'");
    echo "OK: case_status_history\n";

    $db->exec("CREATE TABLE IF NOT EXISTS case_progress_hidden (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        case_id INT UNSIGNED NOT NULL,
        hidden_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_user_case (user_id, case_id),
        KEY idx_case (case_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='案件更新進度報表 - 使用者隱藏紀錄'");
    echo "OK: case_progress_hidden\n";

    echo "Migration 134 done.\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
