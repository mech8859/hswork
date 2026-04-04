<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();
$results = array();

$categories = array(
    'payment_method' => array(
        array('現金', '現金', 1),
        array('匯款-禾順中信', '匯款-禾順中信', 2),
        array('匯款-彰銀', '匯款-彰銀', 3),
        array('支票', '支票', 4),
        array('匯款-政達', '匯款-政達', 5),
        array('代收付', '代收付', 6),
        array('進銷對沖', '進銷對沖', 7),
    ),
    'invoice_category' => array(
        array('訂金', '訂金', 1),
        array('尾款', '尾款', 2),
        array('全款', '全款', 3),
        array('工程款', '工程款', 4),
        array('維修款', '維修款', 5),
        array('保養款', '保養款', 6),
        array('設備款', '設備款', 7),
        array('其他', '其他', 99),
    ),
    'receipt_status' => array(
        array('已收款', '已收款', 1),
        array('已收待查資料', '已收待查資料', 2),
        array('預收待查', '預收待查', 3),
        array('保留款', '保留款', 4),
        array('待收款', '待收款', 5),
        array('已入帳', '已入帳', 6),
        array('退款', '退款', 7),
        array('取消', '取消', 99),
    ),
);

$insertStmt = $db->prepare("INSERT IGNORE INTO dropdown_options (category, option_key, label, sort_order, is_active, is_system) VALUES (?, ?, ?, ?, 1, 1)");

foreach ($categories as $cat => $rows) {
    $chk = $db->prepare("SELECT COUNT(*) FROM dropdown_options WHERE category = ?");
    $chk->execute(array($cat));
    $existing = (int)$chk->fetchColumn();
    if ($existing > 0) {
        $results[] = "{$cat}: 已有 {$existing} 筆，跳過";
        continue;
    }
    $count = 0;
    foreach ($rows as $row) {
        $insertStmt->execute(array($cat, $row[0], $row[1], $row[2]));
        $count++;
    }
    $results[] = "{$cat}: 已新增 {$count} 筆";
}

echo "<h2>Migration 062 - 財務下拉選項 Seed</h2><ul>";
foreach ($results as $r) echo "<li style='color:green'>" . htmlspecialchars($r) . "</li>";
echo "</ul><p><a href='/dropdown_options.php'>選單管理</a> | <a href='/receipts.php'>收款單</a></p>";
