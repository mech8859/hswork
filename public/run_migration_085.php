<?php
/**
 * Migration 085: stock_ins 加 branch_id, branch_name
 */
require_once __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();

$sqls = array(
    "ALTER TABLE stock_ins ADD COLUMN branch_id INT UNSIGNED DEFAULT NULL AFTER warehouse_id",
    "ALTER TABLE stock_ins ADD COLUMN branch_name VARCHAR(100) DEFAULT NULL AFTER branch_id",
);

foreach ($sqls as $sql) {
    try {
        $db->exec($sql);
        echo "OK: $sql\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "SKIP (exists)\n";
        } else {
            echo "ERROR: " . $e->getMessage() . "\n";
        }
    }
}
echo "\nDone.\n";
