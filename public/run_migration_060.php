<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();
$results = array();

// Seed case_attach_type
$caseAttachTypes = array(
    array('drawing',    '施工圖',       1),
    array('quotation',  '報價單',       2),
    array('warranty',   '保固書',       3),
    array('wire_plan',  '預計使用線材', 4),
    array('site_photo', '現場照片',     5),
    array('other',      '其他',         99),
);

// Seed customer_file_type
$customerFileTypes = array(
    array('quotation', '報價單', 1),
    array('contract',  '合約書', 2),
    array('photo',     '照片',   3),
    array('invoice',   '發票',   4),
    array('other',     '其他',   99),
);

$insertStmt = $db->prepare(
    'INSERT INTO dropdown_options (category, option_key, label, sort_order, is_active, is_system) VALUES (?, ?, ?, ?, 1, 1)'
);

$seedSets = array(
    'case_attach_type'   => $caseAttachTypes,
    'customer_file_type' => $customerFileTypes,
);

foreach ($seedSets as $cat => $rows) {
    $check = $db->prepare('SELECT COUNT(*) FROM dropdown_options WHERE category = ?');
    $check->execute(array($cat));
    $existing = (int)$check->fetchColumn();

    if ($existing > 0) {
        $results[] = "分類 {$cat} 已有 {$existing} 筆資料，跳過";
        continue;
    }

    $count = 0;
    foreach ($rows as $row) {
        $insertStmt->execute(array($cat, $row[0], $row[1], $row[2]));
        $count++;
    }
    $results[] = "分類 {$cat} 已新增 {$count} 筆選項";
}

echo "<h2>Migration 060 - 附件/文件類型選項 Seed</h2><ul>";
foreach ($results as $r) {
    echo "<li style='color:green'>" . htmlspecialchars($r) . "</li>";
}
echo "</ul>";

$total = $db->query("SELECT COUNT(*) FROM dropdown_options")->fetchColumn();
echo "<p><b>dropdown_options 共 {$total} 筆</b></p>";
echo "<p><a href='/customers.php'>客戶管理</a> | <a href='/cases.php'>案件管理</a></p>";
