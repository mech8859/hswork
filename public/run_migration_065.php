<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();
$results = array();

// receivables 缺少的欄位
$cols = array(
    'voucher_number'   => "ADD COLUMN `voucher_number` VARCHAR(50) DEFAULT NULL COMMENT '傳票號碼' AFTER `invoice_number`",
    'receivable_number'=> "ADD COLUMN `receivable_number` VARCHAR(50) DEFAULT NULL COMMENT '請款單號(S1)' AFTER `id`",
    'voucher_type'     => "ADD COLUMN `voucher_type` VARCHAR(30) DEFAULT NULL COMMENT '憑證類別'",
    'tax_rate'         => "ADD COLUMN `tax_rate` VARCHAR(10) DEFAULT NULL COMMENT '稅率'",
    'deposit'          => "ADD COLUMN `deposit` DECIMAL(12,0) DEFAULT 0 COMMENT '訂金'",
    'discount'         => "ADD COLUMN `discount` DECIMAL(12,0) DEFAULT 0 COMMENT '折讓金額'",
    'tax'              => "ADD COLUMN `tax` DECIMAL(12,0) DEFAULT 0 COMMENT '稅額'",
    'shipping'         => "ADD COLUMN `shipping` DECIMAL(12,0) DEFAULT 0 COMMENT '運費'",
    'total_amount'     => "ADD COLUMN `total_amount` DECIMAL(12,0) DEFAULT 0 COMMENT '總計'",
    'real_invoice_number' => "ADD COLUMN `real_invoice_number` VARCHAR(30) DEFAULT NULL COMMENT '發票號碼'",
);

foreach ($cols as $name => $sql) {
    try {
        $db->exec("ALTER TABLE `receivables` {$sql}");
        $results[] = "receivables.{$name} 已新增";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false) $results[] = "receivables.{$name} 已存在";
        else $results[] = "錯誤: " . $e->getMessage();
    }
}

// Seed 應收帳款下拉選項
$categories = array(
    'receivable_status' => array(
        array('已請款', '已請款', 1),
        array('待請款', '待請款', 2),
        array('部分收款', '部分收款', 3),
        array('已收款', '已收款', 4),
        array('逾期', '逾期', 5),
        array('取消', '取消', 99),
    ),
    'receivable_category' => array(
        array('訂金', '訂金', 1),
        array('尾款', '尾款', 2),
        array('全款', '全款', 3),
        array('工程款', '工程款', 4),
        array('維修款', '維修款', 5),
        array('保養款', '保養款', 6),
        array('設備款', '設備款', 7),
        array('其他', '其他', 99),
    ),
    'voucher_type' => array(
        array('三聯發票', '三聯發票', 1),
        array('二聯發票', '二聯發票', 2),
        array('免稅發票', '免稅發票', 3),
        array('收據', '收據', 4),
        array('免開', '免開', 5),
    ),
);

$insertStmt = $db->prepare("INSERT IGNORE INTO dropdown_options (category, option_key, label, sort_order, is_active, is_system) VALUES (?, ?, ?, ?, 1, 1)");
foreach ($categories as $cat => $rows) {
    $chk = $db->prepare("SELECT COUNT(*) FROM dropdown_options WHERE category = ?");
    $chk->execute(array($cat));
    if ($chk->fetchColumn() > 0) { $results[] = "{$cat}: 已存在，跳過"; continue; }
    $count = 0;
    foreach ($rows as $row) { $insertStmt->execute(array($cat, $row[0], $row[1], $row[2])); $count++; }
    $results[] = "{$cat}: 新增 {$count} 筆";
}

echo "<h2>Migration 065 - 應收帳款欄位+選項</h2><ul>";
foreach ($results as $r) echo "<li style='color:green'>" . htmlspecialchars($r) . "</li>";
echo "</ul>";
