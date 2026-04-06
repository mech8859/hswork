<?php
/**
 * Migration 100: inventory 加 prepared_qty（預扣與已備貨分離）
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');
$db = Database::getInstance();
header('Content-Type: text/plain; charset=utf-8');

$sqls = array(
    "ALTER TABLE inventory ADD COLUMN prepared_qty INT DEFAULT 0 COMMENT '已備貨數量' AFTER reserved_qty",
);
foreach ($sqls as $sql) {
    try { $db->exec($sql); echo "OK: $sql\n"; }
    catch (PDOException $e) {
        echo (strpos($e->getMessage(), 'Duplicate') !== false ? "SKIP (exists)\n" : "ERROR: " . $e->getMessage() . "\n");
    }
}
echo "\nDone.\n";
