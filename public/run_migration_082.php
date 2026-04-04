<?php
/**
 * Migration 082: stock_outs 加 customer/branch 欄位
 */
require_once __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();

$alterSqls = array(
    "ALTER TABLE stock_outs ADD COLUMN customer_id INT UNSIGNED DEFAULT NULL AFTER warehouse_id",
    "ALTER TABLE stock_outs ADD COLUMN customer_name VARCHAR(200) DEFAULT NULL AFTER customer_id",
    "ALTER TABLE stock_outs ADD COLUMN branch_id INT UNSIGNED DEFAULT NULL AFTER customer_name",
    "ALTER TABLE stock_outs ADD COLUMN branch_name VARCHAR(100) DEFAULT NULL AFTER branch_id",
);

foreach ($alterSqls as $sql) {
    try {
        $db->exec($sql);
        echo "OK: $sql\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "SKIP (exists): $sql\n";
        } else {
            echo "ERROR: $sql => " . $e->getMessage() . "\n";
        }
    }
}

echo "\nDone.\n";
