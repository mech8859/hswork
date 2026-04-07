<?php
/**
 * Migration 101: petty_cash 加 updated_by 欄位
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');
$db = Database::getInstance();
header('Content-Type: text/plain; charset=utf-8');

$sqls = array(
    "ALTER TABLE petty_cash ADD COLUMN updated_by INT UNSIGNED DEFAULT NULL COMMENT '最後修改人' AFTER updated_at",
);
foreach ($sqls as $sql) {
    try { $db->exec($sql); echo "OK: $sql\n"; }
    catch (PDOException $e) {
        echo (strpos($e->getMessage(), 'Duplicate') !== false ? "SKIP\n" : "ERROR: " . $e->getMessage() . "\n");
    }
}
echo "Done.\n";
