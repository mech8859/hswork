<?php
/**
 * Migration 102: receipts 表加 case_number 和 customer_no 欄位
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');
$db = Database::getInstance();
header('Content-Type: text/plain; charset=utf-8');

$sqls = array(
    "ALTER TABLE receipts ADD COLUMN case_number VARCHAR(30) DEFAULT NULL COMMENT '進件編號' AFTER case_id",
    "ALTER TABLE receipts ADD COLUMN customer_no VARCHAR(20) DEFAULT NULL COMMENT '客戶編號' AFTER case_number",
);
foreach ($sqls as $sql) {
    try { $db->exec($sql); echo "OK: $sql\n"; }
    catch (PDOException $e) {
        echo (strpos($e->getMessage(), 'Duplicate') !== false ? "SKIP\n" : "ERROR: " . $e->getMessage() . "\n");
    }
}
echo "Done.\n";
