<?php
header('Content-Type: text/html; charset=utf-8');
set_time_limit(120);

try {
    $db = new PDO('mysql:host=localhost;dbname=vhost158992;charset=utf8mb4', 'vhost158992', 'Kss9227456');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die('DB連線失敗: ' . $e->getMessage());
}

echo '<h2>從 Ragic 導入請款資料</h2>';

$paths = array(
    __DIR__ . '/../ragic_cases.json',
    __DIR__ . '/ragic_cases.json',
    '/home/vhost158992/ragic_cases.json',
);
$jsonFile = null;
foreach ($paths as $p) {
    if (file_exists($p)) {
        $jsonFile = $p;
        break;
    }
}
if (!$jsonFile) {
    die('<p style="color:red">找不到 ragic_cases.json，請確認檔案已上傳</p>');
}

$jsonStr = @file_get_contents($jsonFile);
if (!$jsonStr) {
    die('<p style="color:red">無法讀取檔案</p>');
}

$data = @json_decode($jsonStr, true);
unset($jsonStr);
if (!$data) {
    die('<p style="color:red">JSON 解析失敗</p>');
}

echo '<p>JSON 筆數: ' . count($data) . '</p>';

$stmt = $db->prepare("
    UPDATE cases SET
        billing_title = ?,
        billing_tax_id = ?,
        billing_address = ?,
        billing_email = ?
    WHERE case_number = ? AND (billing_title IS NULL OR billing_title = '')
");

$updated = 0;
$skipped = 0;

foreach ($data as $row) {
    $cn = isset($row['進件編號']) ? trim($row['進件編號']) : '';
    if (!$cn) continue;

    $bt = isset($row['發票抬頭']) ? trim($row['發票抬頭']) : '';
    $ti = isset($row['統一編號']) ? trim($row['統一編號']) : '';
    $ba = isset($row['發票寄送地址']) ? trim($row['發票寄送地址']) : '';
    $be = isset($row['發票寄送mail']) ? trim($row['發票寄送mail']) : '';

    if (!$bt && !$ti && !$ba && !$be) {
        $skipped++;
        continue;
    }

    $stmt->execute(array(
        $bt ?: null,
        $ti ?: null,
        $ba ?: null,
        $be ?: null,
        $cn,
    ));

    if ($stmt->rowCount() > 0) {
        $updated++;
    }
}

unset($data);

echo "<p style='color:green'>更新: {$updated} 筆</p>";
echo "<p>跳過（無請款資料）: {$skipped} 筆</p>";

$r = $db->query("SELECT
    SUM(CASE WHEN billing_title != '' AND billing_title IS NOT NULL THEN 1 ELSE 0 END) as t,
    SUM(CASE WHEN billing_tax_id != '' AND billing_tax_id IS NOT NULL THEN 1 ELSE 0 END) as x,
    SUM(CASE WHEN billing_address != '' AND billing_address IS NOT NULL THEN 1 ELSE 0 END) as a,
    SUM(CASE WHEN billing_email != '' AND billing_email IS NOT NULL THEN 1 ELSE 0 END) as e
    FROM cases")->fetch(PDO::FETCH_ASSOC);

echo '<h3>統計</h3>';
echo '<table border="1" cellpadding="6">';
echo '<tr><td>有發票抬頭</td><td>' . $r['t'] . '</td></tr>';
echo '<tr><td>有統一編號</td><td>' . $r['x'] . '</td></tr>';
echo '<tr><td>有寄送地址</td><td>' . $r['a'] . '</td></tr>';
echo '<tr><td>有 Email</td><td>' . $r['e'] . '</td></tr>';
echo '</table>';
echo '<p><a href="cases.php">返回案件管理</a></p>';
