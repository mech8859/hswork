<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

$db = Database::getInstance();

echo "<h2>案件 status 欄位診斷</h2>";

// 1. 欄位類型
echo "<h3>1. status 欄位類型</h3>";
$stmt = $db->query("SHOW COLUMNS FROM cases WHERE Field = 'status'");
$col = $stmt->fetch(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($col);
echo "</pre>";

// 2. 所有 status 值分布
echo "<h3>2. status 值分布</h3>";
$stmt = $db->query("SELECT status, COUNT(*) as cnt FROM cases GROUP BY status ORDER BY cnt DESC");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<table border='1' cellpadding='5'><tr><th>status 值</th><th>HEX</th><th>筆數</th><th>是否有效</th></tr>";
$validKeys = array('tracking','incomplete','completed','unpaid','lost','maint_case','breach','awaiting_dispatch','customer_cancel');
foreach ($rows as $r) {
    $s = $r['status'];
    $valid = in_array($s, $validKeys) ? '<span style="color:green">✓</span>' : '<span style="color:red">✗ 無效</span>';
    echo "<tr><td>" . htmlspecialchars($s ?: '(NULL)') . "</td><td>" . bin2hex($s) . "</td><td>" . $r['cnt'] . "</td><td>" . $valid . "</td></tr>";
}
echo "</table>";

// 3. 有效的 progressOptions
echo "<h3>3. 有效的 progressOptions 對照</h3>";
require_once __DIR__ . '/../modules/cases/CaseModel.php';
$opts = CaseModel::progressOptions();
echo "<table border='1' cellpadding='5'><tr><th>key (DB值)</th><th>顯示名稱</th></tr>";
foreach ($opts as $k => $v) {
    echo "<tr><td>" . htmlspecialchars($k) . "</td><td>" . htmlspecialchars($v) . "</td></tr>";
}
echo "</table>";

// 4. 列出無效 status 的案件
echo "<h3>4. 無效 status 的案件（前50筆）</h3>";
$placeholders = implode(',', array_fill(0, count($validKeys), '?'));
$stmt = $db->prepare("SELECT id, case_number, title, status, stage FROM cases WHERE status NOT IN ({$placeholders}) ORDER BY id DESC LIMIT 50");
$stmt->execute($validKeys);
$invalid = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<p>共 " . count($invalid) . " 筆無效</p>";
if ($invalid) {
    echo "<table border='1' cellpadding='5'><tr><th>ID</th><th>編號</th><th>名稱</th><th>status</th><th>stage</th></tr>";
    foreach ($invalid as $r) {
        echo "<tr><td>{$r['id']}</td><td>" . htmlspecialchars($r['case_number']) . "</td><td>" . htmlspecialchars($r['title']) . "</td><td>" . htmlspecialchars($r['status']) . "</td><td>{$r['stage']}</td></tr>";
    }
    echo "</table>";
}

echo "<hr><p><a href='/cases.php'>返回案件管理</a></p>";
