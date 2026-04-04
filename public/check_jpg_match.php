<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

$db = Database::getInstance();

// 取所有有 legacy_customer_no 的客戶
$stmt = $db->query("SELECT id, customer_no, legacy_customer_no, name FROM customers WHERE legacy_customer_no IS NOT NULL AND legacy_customer_no != '' ORDER BY id");
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 掃描 JPG 目錄（用 POST 傳入路徑，或先用固定範例）
echo '<h2>客戶 JPG 比對</h2>';
echo '<p>系統客戶數（有原編號）: ' . count($customers) . '</p>';

// 提取編號
$custByNum = array();
foreach ($customers as $c) {
    if (preg_match('/(\d+)/', $c['legacy_customer_no'], $m)) {
        $num = ltrim($m[1], '0') ?: '0';
        if (!isset($custByNum[$num])) $custByNum[$num] = array();
        $custByNum[$num][] = $c;
    }
}

echo '<p>可用編號的客戶: ' . count($custByNum) . '</p>';
echo '<h3>前20個範例</h3>';
echo '<table border="1" cellpadding="4"><tr><th>提取編號</th><th>原資料編號</th><th>客戶名稱</th><th>系統ID</th></tr>';
$i = 0;
foreach ($custByNum as $num => $custs) {
    foreach ($custs as $c) {
        if ($i++ >= 20) break 2;
        echo '<tr><td>' . $num . '</td><td>' . htmlspecialchars($c['legacy_customer_no']) . '</td><td>' . htmlspecialchars($c['name']) . '</td><td>' . $c['id'] . '</td></tr>';
    }
}
echo '</table>';

echo '<br><a href="/customers.php">返回客戶管理</a>';
