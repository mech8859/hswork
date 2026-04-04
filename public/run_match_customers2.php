<?php
header('Content-Type: text/html; charset=utf-8');
set_time_limit(120);

try {
    $db = new PDO('mysql:host=localhost;dbname=vhost158992;charset=utf8mb4', 'vhost158992', 'Kss9227456');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die('DB連線失敗: ' . $e->getMessage());
}

echo '<h2>第二輪：模糊配對（去掉 T/C 編號）</h2>';

// 取得客戶 name → id
$custStmt = $db->query("SELECT id, name FROM customers WHERE is_active = 1");
$custMap = array();
$custMapClean = array(); // 去掉T編號的版本
while ($row = $custStmt->fetch(PDO::FETCH_ASSOC)) {
    $name = trim($row['name']);
    if ($name !== '') {
        if (!isset($custMap[$name])) {
            $custMap[$name] = (int)$row['id'];
        }
        // 也建立去掉 T/C 編號的映射
        $clean = preg_replace('/\s+[TC]\d+(-\d+)?$/u', '', $name);
        $clean = trim($clean);
        if ($clean !== '' && !isset($custMapClean[$clean])) {
            $custMapClean[$clean] = (int)$row['id'];
        }
    }
}

// 取得仍未配對的案件
$casesStmt = $db->query("SELECT id, customer_name FROM cases WHERE customer_id IS NULL AND customer_name IS NOT NULL AND customer_name != ''");
$updateStmt = $db->prepare("UPDATE cases SET customer_id = ? WHERE id = ?");

$matched = 0;
$unmatched = 0;
$unmatchedNames = array();
$matchExamples = array();

while ($case = $casesStmt->fetch(PDO::FETCH_ASSOC)) {
    $cname = trim($case['customer_name']);

    // 去掉 T/C 編號後綴
    $cleanName = preg_replace('/\s+[TC]\d+(-\d+)?$/u', '', $cname);
    $cleanName = trim($cleanName);

    $foundId = null;

    // 方法1: 清理後完全匹配
    if (isset($custMap[$cleanName])) {
        $foundId = $custMap[$cleanName];
    }
    // 方法2: 清理後匹配客戶的清理版
    if (!$foundId && isset($custMapClean[$cleanName])) {
        $foundId = $custMapClean[$cleanName];
    }
    // 方法3: 案件名包含客戶名 (用 LIKE 查詢)
    if (!$foundId && mb_strlen($cleanName) >= 3) {
        $likeStmt = $db->prepare("SELECT id FROM customers WHERE is_active = 1 AND name LIKE ? LIMIT 1");
        $likeStmt->execute(array($cleanName . '%'));
        $found = $likeStmt->fetch(PDO::FETCH_ASSOC);
        if ($found) {
            $foundId = (int)$found['id'];
        }
    }

    if ($foundId) {
        $updateStmt->execute(array($foundId, $case['id']));
        $matched++;
        if (count($matchExamples) < 10) {
            $matchExamples[] = $cname;
        }
    } else {
        $unmatched++;
        if (count($unmatchedNames) < 20 && !in_array($cname, $unmatchedNames)) {
            $unmatchedNames[] = $cname;
        }
    }
}

echo "<p style='color:green'>第二輪配對成功: {$matched} 筆</p>";
echo "<p>仍未配對: {$unmatched} 筆</p>";

// 總統計
$after = $db->query("SELECT
    COUNT(*) as total,
    SUM(CASE WHEN customer_id IS NOT NULL THEN 1 ELSE 0 END) as has_id,
    COUNT(DISTINCT customer_id) as unique_cust
    FROM cases")->fetch(PDO::FETCH_ASSOC);
echo "<h3>總計</h3>";
echo "<p style='font-weight:bold'>{$after['total']} 筆案件中，{$after['has_id']} 筆已配對 customer_id（{$after['unique_cust']} 個客戶）</p>";

if (!empty($matchExamples)) {
    echo '<h3>本次配對樣本</h3><ul>';
    foreach ($matchExamples as $n) {
        echo '<li>' . htmlspecialchars($n) . '</li>';
    }
    echo '</ul>';
}

if (!empty($unmatchedNames)) {
    echo '<h3>仍未配對樣本（前20個）</h3><ul>';
    foreach ($unmatchedNames as $n) {
        echo '<li>' . htmlspecialchars($n) . '</li>';
    }
    echo '</ul>';
}

echo '<p><a href="customers.php">返回客戶管理</a></p>';
