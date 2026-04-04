<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();

// 找中區專案部的 branch_id
$stmt = $db->prepare("SELECT id, name FROM branches WHERE name LIKE '%中區專案%'");
$stmt->execute();
$projectBranch = $stmt->fetch(PDO::FETCH_ASSOC);

// 找中區技術組的 branch_id
$stmt2 = $db->prepare("SELECT id, name FROM branches WHERE name LIKE '%中區技術%'");
$stmt2->execute();
$techBranch = $stmt2->fetch(PDO::FETCH_ASSOC);

echo "<h2>修正中區專案部案件分公司</h2>";

if (!$projectBranch) {
    echo "<p style='color:red'>找不到「中區專案部」分公司！</p>";
    exit;
}

echo "<p>中區專案部 ID: {$projectBranch['id']} ({$projectBranch['name']})</p>";
if ($techBranch) echo "<p>中區技術組 ID: {$techBranch['id']} ({$techBranch['name']})</p>";

// 128 筆案件編號（from Ragic 所屬分公司=中區專案部）
$caseNumbers = array('2026-1542','2026-1541','2026-1531','2026-1501','2026-1484','2026-1395','2026-1353','2026-1347','2026-1343','2026-1336','2026-1329','2026-1304','2026-1262','2026-1158','2026-1154','2026-1149','2026-1145','2026-1143','2026-1096','2026-1092','2026-1091','2026-1090','2026-1087','2026-1040','2026-1038','2026-1035','2026-1034','2026-1033','2026-1031','2026-1025','2026-1008','2026-0905','2026-0871','2026-0845','2026-0843','2026-0817','2026-0811','2026-0730','2026-0725','2026-0676','202601-073','202601-109','202601-117','202601-118','202601-121','2026-0011','2026-0084','2026-0089','2026-0043','202512-281','202512-282','202512-283','202512-284','202512-285','202512-286','202512-287','202512-288','202512-289','202512-291','202512-292','202512-293','202512-294','202512-296','2026-0643','2026-0642','2026-0641','2026-0640','2026-0639','2026-0638','2026-0637','2026-0636','2026-0634','2026-0633','2026-0632','2026-0631','2026-0630','2026-0629','2026-0628','2026-0627','2026-0626','2026-0625','2026-0624','2026-0623','202512-5-4','2026-0622','2026-0621','2026-0620','2026-0618','2026-0617','2026-0616','2026-0615','2026-0614','2026-0613','2026-0612','2026-0611','2026-0610','2026-0609','2026-0608','2026-0607','2026-0606','2026-0605','2026-0604','2026-0603','202512-5-2','202512-5-1','2026-0602','2026-0601','2026-0123','2026-0155','2026-0176','2026-0177','2026-0209','2026-0222','2026-0257','2026-0262','2026-0305','2026-0598','2026-0597','2026-0596','2026-0595','202601-2-002','202601-2-001','2026-0376','2026-0381','2026-0400','2026-0401','2026-0454','2026-0481');

$placeholders = implode(',', array_fill(0, count($caseNumbers), '?'));

// 先查目前這些案件的分公司分布
$checkStmt = $db->prepare("SELECT b.name, COUNT(*) as cnt FROM cases c LEFT JOIN branches b ON c.branch_id = b.id WHERE c.case_number IN ($placeholders) GROUP BY b.name");
$checkStmt->execute($caseNumbers);
echo "<h3>修正前分布</h3><ul>";
while ($row = $checkStmt->fetch(PDO::FETCH_ASSOC)) {
    echo "<li>{$row['name']}: {$row['cnt']} 筆</li>";
}
echo "</ul>";

// 執行更新
$updateStmt = $db->prepare("UPDATE cases SET branch_id = ? WHERE case_number IN ($placeholders)");
$params = array_merge(array($projectBranch['id']), $caseNumbers);
$updateStmt->execute($params);
$affected = $updateStmt->rowCount();

echo "<p style='color:green;font-size:1.2em'>✅ 已更新 {$affected} 筆案件至「{$projectBranch['name']}」</p>";
echo "<p><a href='/cases.php'>← 案件管理</a></p>";
