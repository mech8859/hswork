<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();
$results = array();

// 1. payments_out 加欄位
$cols = array(
    'vendor_code'    => "ADD COLUMN `vendor_code` VARCHAR(20) DEFAULT NULL COMMENT '廠商編號' AFTER `vendor_name`",
    'voucher_number' => "ADD COLUMN `voucher_number` VARCHAR(50) DEFAULT NULL COMMENT '傳票號碼' AFTER `payment_number`",
);
foreach ($cols as $name => $sql) {
    try {
        $db->exec("ALTER TABLE `payments_out` {$sql}");
        $results[] = "payments_out.{$name} 已新增";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false) $results[] = "payments_out.{$name} 已存在";
        else $results[] = "錯誤: " . $e->getMessage();
    }
}

// 2. Seed 付款單下拉選項
$categories = array(
    'payment_out_method' => array(
        array('零用金', '零用金', 1),
        array('現金', '現金', 2),
        array('銀行支出-禾順', '銀行支出-禾順', 3),
        array('銀行支出-政達', '銀行支出-政達', 4),
        array('銀行支出-富邦', '銀行支出-富邦', 5),
        array('支票', '支票', 6),
        array('進銷對沖', '進銷對沖', 7),
        array('銀行支出', '銀行支出', 8),
    ),
    'payment_out_category' => array(
        array('訂金', '訂金', 1),
        array('保留款', '保留款', 2),
        array('廠商-發票已申報', '廠商-發票已申報', 3),
        array('廠商', '廠商', 4),
        array('其他', '其他', 99),
    ),
    'payment_out_status' => array(
        array('草稿', '草稿', 1),
        array('已付款', '已付款', 2),
        array('預付待查', '預付待查', 3),
        array('已付待查', '已付待查', 4),
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

echo "<h2>Migration 064 - 付款單欄位+選項</h2><ul>";
foreach ($results as $r) echo "<li style='color:green'>" . htmlspecialchars($r) . "</li>";
echo "</ul>";
