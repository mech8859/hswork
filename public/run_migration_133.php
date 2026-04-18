<?php
/**
 * Migration 133: report_period 欄位擴為 VARCHAR(10) 以支援 401 兩月一期格式 YYYY-MM-MM
 *  - purchase_invoices
 *  - sales_invoices
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');
$db = Database::getInstance();
header('Content-Type: text/plain; charset=utf-8');

foreach (array('purchase_invoices', 'sales_invoices') as $tbl) {
    $cur = $db->query("SHOW COLUMNS FROM {$tbl} LIKE 'report_period'")->fetch(PDO::FETCH_ASSOC);
    if (!$cur) {
        echo "SKIP: {$tbl} 無 report_period 欄位\n";
        continue;
    }
    $type = strtolower($cur['Type']);
    if (strpos($type, 'varchar(10)') !== false || strpos($type, 'char(10)') !== false
        || strpos($type, 'varchar(20)') !== false) {
        echo "SKIP: {$tbl}.report_period 已是 {$type}\n";
        continue;
    }
    $db->exec("ALTER TABLE {$tbl} MODIFY COLUMN report_period VARCHAR(10) DEFAULT NULL COMMENT '申報期間 YYYY-MM-MM'");
    echo "OK: {$tbl}.report_period 擴為 VARCHAR(10)（原 {$type}）\n";
}
echo "Migration 133 done.\n";
