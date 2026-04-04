<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();

echo "<h2>JSON vs DB case_number 比對</h2>";
echo "<style>body{font-family:sans-serif;padding:20px;} table{border-collapse:collapse;margin:10px 0;} td,th{border:1px solid #ccc;padding:6px 12px;text-align:left;}</style>";

$json = json_decode(file_get_contents(__DIR__ . '/cases_import.json'), true);

// 取得 DB 所有 case_number
$stmt = $db->query("SELECT case_number FROM cases");
$dbNumbers = array();
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $dbNumbers[$row['case_number']] = true;
}
echo "<p>DB 案件總數: " . count($dbNumbers) . "</p>";
echo "<p>JSON 案件總數: " . count($json) . "</p>";

// JSON 有但 DB 沒有
$jsonOnly = array();
foreach ($json as $r) {
    if (!isset($dbNumbers[$r['case_number']])) {
        $jsonOnly[] = $r['case_number'];
    }
}
echo "<p>JSON 有但 DB 沒有: " . count($jsonOnly) . " 筆</p>";
if (!empty($jsonOnly)) {
    echo "<p>前20筆: " . implode(', ', array_slice($jsonOnly, 0, 20)) . "</p>";
}

// DB case_number 格式分布
echo "<h3>DB case_number 格式樣本</h3>";
$stmt = $db->query("SELECT case_number FROM cases ORDER BY id LIMIT 10");
echo "<p>前10筆: ";
$nums = array();
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $nums[] = $row['case_number'];
}
echo implode(', ', $nums) . "</p>";

$stmt = $db->query("SELECT case_number FROM cases ORDER BY id DESC LIMIT 10");
echo "<p>後10筆: ";
$nums = array();
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $nums[] = $row['case_number'];
}
echo implode(', ', $nums) . "</p>";

// JSON case_number 格式樣本
echo "<h3>JSON 未匹配的 case_number 樣本</h3>";
echo "<p>" . implode(', ', array_slice($jsonOnly, 0, 30)) . "</p>";

echo "<p><a href='/cases.php'>返回</a></p>";
