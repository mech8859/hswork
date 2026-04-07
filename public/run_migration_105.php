<?php
/**
 * Migration 105: case_payments 加未稅金額/稅額欄位
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');
$db = Database::getInstance();
header('Content-Type: text/plain; charset=utf-8');

$sqls = array(
    "ALTER TABLE case_payments ADD COLUMN untaxed_amount DECIMAL(12,0) DEFAULT 0 COMMENT '未稅金額' AFTER amount",
    "ALTER TABLE case_payments ADD COLUMN tax_amount DECIMAL(12,0) DEFAULT 0 COMMENT '稅額' AFTER untaxed_amount",
);
foreach ($sqls as $sql) {
    try { $db->exec($sql); echo "OK: $sql\n"; }
    catch (PDOException $e) {
        echo (strpos($e->getMessage(), 'Duplicate') !== false ? "SKIP\n" : "ERROR: " . $e->getMessage() . "\n");
    }
}
echo "Done.\n";
