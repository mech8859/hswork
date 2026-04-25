<?php
/**
 * Migration 135: 備用金→零用金 人工核對配對表
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();

try {
    $db->exec("CREATE TABLE IF NOT EXISTS rf_pc_match_confirmed (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        rf_id INT UNSIGNED NOT NULL,
        pc_id INT UNSIGNED NOT NULL,
        confirmed_by INT UNSIGNED DEFAULT NULL,
        confirmed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_rf (rf_id),
        UNIQUE KEY uk_pc (pc_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='備用金→零用金 人工核對配對'");
    echo "OK: rf_pc_match_confirmed\n";
    echo "Migration 135 done.\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
