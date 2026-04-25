<?php
/**
 * Migration 136: rf_pc_match_confirmed 允許單邊 NULL（單獨核對未對應列用）
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();

try {
    $db->exec("ALTER TABLE rf_pc_match_confirmed
        MODIFY COLUMN rf_id INT UNSIGNED DEFAULT NULL,
        MODIFY COLUMN pc_id INT UNSIGNED DEFAULT NULL");
    echo "OK: rf_pc_match_confirmed 允許 rf_id/pc_id 為 NULL\n";
    echo "Migration 136 done.\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
