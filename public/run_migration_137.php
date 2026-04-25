<?php
/**
 * Migration 137: 現金→零用金 人工核對配對表（schema 同 rf_pc_match_confirmed migration 136 後版）
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();

try {
    $db->exec("CREATE TABLE IF NOT EXISTS cash_pc_match_confirmed (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        cash_id INT UNSIGNED DEFAULT NULL,
        pc_id INT UNSIGNED DEFAULT NULL,
        confirmed_by INT UNSIGNED DEFAULT NULL,
        confirmed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_cash (cash_id),
        UNIQUE KEY uk_pc (pc_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='現金→零用金 人工核對配對'");
    echo "OK: cash_pc_match_confirmed\n";
    echo "Migration 137 done.\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
