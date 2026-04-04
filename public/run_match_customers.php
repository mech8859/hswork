<?php
header('Content-Type: text/html; charset=utf-8');
set_time_limit(120);

try {
    $db = new PDO('mysql:host=localhost;dbname=vhost158992;charset=utf8mb4', 'vhost158992', 'Kss9227456');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die('DB連線失敗: ' . $e->getMessage());
}

echo '<h2>案件×客戶配對（customer_id）</h2>';

// 統計修復前
$before = $db->query("SELECT
    COUNT(*) as total,
    SUM(CASE WHEN customer_id IS NOT NULL THEN 1 ELSE 0 END) as has_id
    FROM cases")->fetch(PDO::FETCH_ASSOC);
echo "<p>修復前：{$before['total']} 筆案件，{$before['has_id']} 筆有 customer_id</p>";

// 取得所有客戶 name → id 映射
$custStmt = $db->query("SELECT id, name FROM customers WHERE is_active = 1");
$custMap = array();
while ($row = $custStmt->fetch(PDO::FETCH_ASSOC)) {
    $name = trim($row['name']);
    if ($name !== '') {
        // 如果同名只保留第一個
        if (!isset($custMap[$name])) {
            $custMap[$name] = (int)$row['id'];
        }
    }
}
echo '<p>客戶表共 ' . count($custMap) . ' 個不重複名稱</p>';

// 取得所有 customer_id 為 NULL 的案件
$casesStmt = $db->query("SELECT id, customer_name FROM cases WHERE customer_id IS NULL AND customer_name IS NOT NULL AND customer_name != ''");
$updateStmt = $db->prepare("UPDATE cases SET customer_id = ? WHERE id = ?");

$matched = 0;
$unmatched = 0;
$unmatchedNames = array();

while ($case = $casesStmt->fetch(PDO::FETCH_ASSOC)) {
    $cname = trim($case['customer_name']);
    if (isset($custMap[$cname])) {
        $updateStmt->execute(array($custMap[$cname], $case['id']));
        $matched++;
    } else {
        $unmatched++;
        // 收集前20個未匹配名稱
        if (count($unmatchedNames) < 20 && !in_array($cname, $unmatchedNames)) {
            $unmatchedNames[] = $cname;
        }
    }
}

echo "<p style='color:green'>配對成功: {$matched} 筆</p>";
echo "<p>未配對: {$unmatched} 筆</p>";

// 統計修復後
$after = $db->query("SELECT
    SUM(CASE WHEN customer_id IS NOT NULL THEN 1 ELSE 0 END) as has_id,
    COUNT(DISTINCT customer_id) as unique_customers
    FROM cases WHERE customer_id IS NOT NULL")->fetch(PDO::FETCH_ASSOC);
echo "<p style='color:green;font-weight:bold'>修復後：{$after['has_id']} 筆有 customer_id，涉及 {$after['unique_customers']} 個客戶</p>";

if (!empty($unmatchedNames)) {
    echo '<h3>未配對的客戶名稱樣本（前20個）</h3><ul>';
    foreach ($unmatchedNames as $n) {
        echo '<li>' . htmlspecialchars($n) . '</li>';
    }
    echo '</ul>';
}

echo '<p><a href="customers.php">返回客戶管理</a></p>';
