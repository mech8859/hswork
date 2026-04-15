<?php
/**
 * Migration 127: 應付帳款 ↔ 付款單 1:1 連結 + 進貨單回寫付款單號
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('all')) die('admin only');
$db = Database::getInstance();

echo "<!DOCTYPE html><meta charset=utf-8><pre>";

try {
    // 1. goods_receipts 加 payment_number
    $exists = $db->query("SHOW COLUMNS FROM goods_receipts LIKE 'payment_number'")->fetch();
    if ($exists) {
        echo "✓ goods_receipts.payment_number 已存在\n";
    } else {
        $db->exec("ALTER TABLE goods_receipts ADD COLUMN payment_number VARCHAR(30) NULL COMMENT '付款單號（由付款單儲存時回寫）' AFTER paid_date");
        echo "✓ goods_receipts 加上 payment_number 欄位\n";
    }
    $idx = $db->query("SHOW INDEX FROM goods_receipts WHERE Key_name = 'idx_payment_number'")->fetch();
    if (!$idx) {
        $db->exec("ALTER TABLE goods_receipts ADD INDEX idx_payment_number (payment_number)");
        echo "  ✓ 加 idx_payment_number 索引\n";
    }

    // 2. payables 加 payment_out_id
    $exists2 = $db->query("SHOW COLUMNS FROM payables LIKE 'payment_out_id'")->fetch();
    if ($exists2) {
        echo "✓ payables.payment_out_id 已存在\n";
    } else {
        $db->exec("ALTER TABLE payables ADD COLUMN payment_out_id INT UNSIGNED NULL COMMENT '生成的付款單 id（1:1 連結 + 鎖定判斷）' AFTER status");
        echo "✓ payables 加上 payment_out_id 欄位\n";
    }
    $idx2 = $db->query("SHOW INDEX FROM payables WHERE Key_name = 'idx_payment_out_id'")->fetch();
    if (!$idx2) {
        $db->exec("ALTER TABLE payables ADD INDEX idx_payment_out_id (payment_out_id)");
        echo "  ✓ 加 idx_payment_out_id 索引\n";
    }

    echo "\n完成\n";
} catch (Exception $e) {
    echo "✗ 失敗：" . $e->getMessage() . "\n";
}
