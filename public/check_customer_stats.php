<?php
header('Content-Type: text/html; charset=utf-8');
try {
    $db = new PDO('mysql:host=localhost;dbname=vhost158992;charset=utf8mb4', 'vhost158992', 'Kss9227456');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die('DB連線失敗');
}

echo '<h2>客戶資料統計</h2>';

// 總數
$total = $db->query("SELECT COUNT(*) FROM customers")->fetchColumn();
$active = $db->query("SELECT COUNT(*) FROM customers WHERE is_active = 1")->fetchColumn();
echo "<p>客戶總數: {$total}（啟用: {$active}）</p>";

// 依來源
echo '<h3>依匯入來源</h3>';
$stmt = $db->query("SELECT COALESCE(import_source, '(無)') as src, COUNT(*) as cnt FROM customers GROUP BY import_source ORDER BY cnt DESC");
echo '<table border="1" cellpadding="6"><tr><th>來源</th><th>筆數</th></tr>';
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo '<tr><td>' . htmlspecialchars($r['src']) . '</td><td>' . number_format($r['cnt']) . '</td></tr>';
}
echo '</table>';

// 依客戶編號格式
echo '<h3>依客戶編號格式</h3>';
$stmt = $db->query("
    SELECT
        CASE
            WHEN customer_no LIKE '客戶%' THEN '客戶XXXX (ERP匯入)'
            WHEN customer_no LIKE 'CU-%' THEN 'CU-XXXXXX (系統產生)'
            ELSE '其他格式'
        END as fmt,
        COUNT(*) as cnt,
        MIN(customer_no) as sample_min,
        MAX(customer_no) as sample_max
    FROM customers GROUP BY fmt ORDER BY cnt DESC
");
echo '<table border="1" cellpadding="6"><tr><th>格式</th><th>筆數</th><th>範例</th></tr>';
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo '<tr><td>' . $r['fmt'] . '</td><td>' . number_format($r['cnt']) . '</td><td>' . htmlspecialchars($r['sample_min'] . ' ~ ' . $r['sample_max']) . '</td></tr>';
}
echo '</table>';

// ERP 來源的最大編號
echo '<h3>ERP 客戶編號範圍</h3>';
$stmt = $db->query("SELECT import_source, MIN(CAST(REPLACE(customer_no,'客戶','') AS UNSIGNED)) as min_no, MAX(CAST(REPLACE(customer_no,'客戶','') AS UNSIGNED)) as max_no, COUNT(*) as cnt FROM customers WHERE customer_no LIKE '客戶%' GROUP BY import_source");
echo '<table border="1" cellpadding="6"><tr><th>來源</th><th>最小編號</th><th>最大編號</th><th>筆數</th><th>應有筆數(max-min+1)</th><th>缺少</th></tr>';
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $expected = $r['max_no'] - $r['min_no'] + 1;
    $missing = $expected - $r['cnt'];
    echo '<tr><td>' . htmlspecialchars($r['import_source']) . '</td><td>' . $r['min_no'] . '</td><td>' . $r['max_no'] . '</td><td>' . $r['cnt'] . '</td><td>' . $expected . '</td><td style="color:' . ($missing > 0 ? 'red' : 'green') . '">' . $missing . '</td></tr>';
}
echo '</table>';

echo '<p><a href="customers.php">返回客戶管理</a></p>';
