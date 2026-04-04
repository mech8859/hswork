<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();

echo '<h2>案件-客戶關聯檢查</h2>';

// 檢查 cases 表有沒有 customer_id 欄位
$cols = $db->query("SHOW COLUMNS FROM cases LIKE 'customer_id'")->fetch();
echo '<p>customer_id 欄位: ' . ($cols ? '存在' : '不存在') . '</p>';

// 有多少案件有 customer_id
$total = $db->query("SELECT COUNT(*) FROM cases")->fetchColumn();
$linked = $db->query("SELECT COUNT(*) FROM cases WHERE customer_id IS NOT NULL AND customer_id > 0")->fetchColumn();
echo "<p>案件總數: {$total} | 有 customer_id: {$linked}</p>";

// 案件用什麼方式關聯客戶？查看 customer_name 欄位
$cols2 = $db->query("SHOW COLUMNS FROM cases LIKE 'customer_name'")->fetch();
echo '<p>customer_name 欄位: ' . ($cols2 ? '存在' : '不存在') . '</p>';

if ($cols2) {
    $withName = $db->query("SELECT COUNT(*) FROM cases WHERE customer_name IS NOT NULL AND customer_name != ''")->fetchColumn();
    echo "<p>有 customer_name: {$withName}</p>";
    
    // 找「陳東茂」相關案件
    $stmt = $db->prepare("SELECT id, case_number, title, customer_name, customer_id FROM cases WHERE customer_name LIKE '%陳東茂%' OR title LIKE '%陳東茂%' LIMIT 10");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo '<h3>陳東茂相關案件</h3>';
    foreach ($rows as $r) {
        echo "<p>ID={$r['id']} | {$r['case_number']} | title={$r['title']} | customer_name={$r['customer_name']} | customer_id={$r['customer_id']}</p>";
    }
}

// 查客戶181的ID
$stmt = $db->prepare("SELECT id, name FROM customers WHERE legacy_customer_no = '客戶181'");
$stmt->execute();
$c = $stmt->fetch(PDO::FETCH_ASSOC);
if ($c) {
    echo "<h3>客戶181 = ID {$c['id']} ({$c['name']})</h3>";
    $stmt2 = $db->prepare("SELECT id, case_number, title FROM cases WHERE customer_id = ?");
    $stmt2->execute(array($c['id']));
    $cases = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    echo "<p>此 customer_id 的案件: " . count($cases) . "</p>";
}

echo '<br><a href="/customers.php">返回</a>';
