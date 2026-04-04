<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();
$results = array();

// 4 個財務表都加 customer_no 和 case_number
$tables = array(
    'receipts'     => '收款單',
    'payments_out' => '付款單',
    'receivables'  => '應收帳款',
    'payables'     => '應付帳款',
);

foreach ($tables as $table => $label) {
    $cols = array(
        'customer_no' => "ADD COLUMN `customer_no` VARCHAR(20) DEFAULT NULL COMMENT '客戶編號'",
        'case_number' => "ADD COLUMN `case_number` VARCHAR(30) DEFAULT NULL COMMENT '進件編號'",
    );
    foreach ($cols as $col => $sql) {
        try {
            $db->exec("ALTER TABLE `{$table}` {$sql}");
            $results[] = "{$label}: {$col} 已新增";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate') !== false) $results[] = "{$label}: {$col} 已存在";
        }
    }
}

// payables 額外缺的欄位
$payableCols = array(
    'vendor_code'    => "ADD COLUMN `vendor_code` VARCHAR(20) DEFAULT NULL COMMENT '廠商編號' AFTER `vendor_name`",
    'voucher_number' => "ADD COLUMN `voucher_number` VARCHAR(50) DEFAULT NULL COMMENT '傳票號碼' AFTER `payable_number`",
    'status'         => "ADD COLUMN `status` VARCHAR(20) DEFAULT '待付款' COMMENT '狀態'",
);
foreach ($payableCols as $col => $sql) {
    try {
        $db->exec("ALTER TABLE `payables` {$sql}");
        $results[] = "應付帳款: {$col} 已新增";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false) $results[] = "應付帳款: {$col} 已存在";
    }
}

// 回填 case_number（從 case_id 查 cases.case_number）
$backfilled = 0;
foreach ($tables as $table => $label) {
    try {
        $affected = $db->exec("UPDATE `{$table}` t JOIN cases c ON t.case_id = c.id SET t.case_number = c.case_number WHERE t.case_number IS NULL AND t.case_id IS NOT NULL");
        $backfilled += $affected;
    } catch (PDOException $e) {}
}
$results[] = "已回填 {$backfilled} 筆 case_number";

echo "<h2>Migration 066 - 財務表加客戶編號+進件編號</h2><ul>";
foreach ($results as $r) echo "<li style='color:green'>" . htmlspecialchars($r) . "</li>";
echo "</ul>";
