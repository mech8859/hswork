<?php
/**
 * Migration 138：銷項發票
 *  1) 移除 invoice_number UNIQUE INDEX（改由 PHP 端依聯式判斷；33/23 退折允許重複）
 *  2) 新增 bill_date（發票日期）— 原 invoice_date 改為「開立日期」
 *  3) 新增 allowance_number（折讓證明單號碼，僅聯式 33 可填）
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();

try {
    $idxStmt = $db->query("SHOW INDEX FROM sales_invoices WHERE Key_name = 'uniq_sales_invoice_number'");
    if ($idxStmt->fetch(PDO::FETCH_ASSOC)) {
        $db->exec("ALTER TABLE sales_invoices DROP INDEX uniq_sales_invoice_number");
        echo "OK: dropped uniq_sales_invoice_number\n";
    } else {
        echo "SKIP: uniq_sales_invoice_number not found\n";
    }

    $colStmt = $db->query("SHOW COLUMNS FROM sales_invoices LIKE 'bill_date'");
    if (!$colStmt->fetch(PDO::FETCH_ASSOC)) {
        $db->exec("ALTER TABLE sales_invoices ADD COLUMN bill_date DATE NULL COMMENT '發票日期（買賣雙方記載日期，可與開立日期不同）' AFTER invoice_date");
        echo "OK: added bill_date\n";
    } else {
        echo "SKIP: bill_date already exists\n";
    }

    $colStmt = $db->query("SHOW COLUMNS FROM sales_invoices LIKE 'allowance_number'");
    if (!$colStmt->fetch(PDO::FETCH_ASSOC)) {
        $db->exec("ALTER TABLE sales_invoices ADD COLUMN allowance_number VARCHAR(50) NULL COMMENT '折讓證明單號碼（聯式33專用）' AFTER invoice_number");
        echo "OK: added allowance_number\n";
    } else {
        echo "SKIP: allowance_number already exists\n";
    }

    echo "Migration 138 done.\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
