<?php
require_once __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: text/plain; charset=utf-8');
$db = Database::getInstance();

$sqls = array(
    "ALTER TABLE receivable_items ADD COLUMN item_name VARCHAR(200) DEFAULT NULL AFTER merge_case_number",
    "ALTER TABLE receivable_items ADD COLUMN unit_price DECIMAL(12,0) DEFAULT 0 AFTER item_name",
    "ALTER TABLE receivable_items ADD COLUMN quantity DECIMAL(10,2) DEFAULT 1 AFTER unit_price",
    "ALTER TABLE receivable_items ADD COLUMN sort_order INT DEFAULT 0 AFTER note",
);
foreach ($sqls as $sql) {
    try { $db->exec($sql); echo "OK: $sql\n"; }
    catch (PDOException $e) { echo (strpos($e->getMessage(),'Duplicate')!==false ? "SKIP" : "ERR: ".$e->getMessage()) . "\n"; }
}
echo "Done.\n";
