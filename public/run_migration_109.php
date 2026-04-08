<?php
/**
 * Migration 109: cash_details 加 is_starred 星號記號欄位
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');
$db = Database::getInstance();
header('Content-Type: text/plain; charset=utf-8');

$sqls = array(
    "ALTER TABLE cash_details ADD COLUMN is_starred TINYINT(1) NOT NULL DEFAULT 0 COMMENT '星號記號（使用者自行標記）'",
    "ALTER TABLE cash_details ADD INDEX idx_is_starred (is_starred)",
);
foreach ($sqls as $sql) {
    try {
        $db->exec($sql);
        echo "OK: $sql\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false) {
            echo "SKIP: $sql\n";
        } else {
            echo "ERROR: " . $e->getMessage() . "\n";
        }
    }
}

echo "\nDone.\n";
