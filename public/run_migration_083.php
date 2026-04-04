<?php
/**
 * Migration 083: stock_outs 加 has_return_material 欄位
 */
require_once __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();

$sqls = array(
    "ALTER TABLE stock_outs ADD COLUMN has_return_material TINYINT(1) NOT NULL DEFAULT 0 COMMENT '有餘料需入庫' AFTER updated_at",
);

foreach ($sqls as $sql) {
    try {
        $db->exec($sql);
        echo "OK: $sql\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "SKIP (exists): $sql\n";
        } else {
            echo "ERROR: " . $e->getMessage() . "\n";
        }
    }
}
echo "\nDone.\n";
