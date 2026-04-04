<?php
/**
 * 修復收款單業務欄位
 * 從 note 裡的 [業務:xxx] 解析業務名稱，對應到 users.real_name 填入 sales_id
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

$db = Database::getInstance();

// 取得所有 users 做名稱對照
$stmt = $db->query("SELECT id, real_name FROM users WHERE real_name IS NOT NULL AND real_name != ''");
$userMap = array();
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $u) {
    $userMap[$u['real_name']] = $u['id'];
}

echo "<h2>修復收款單業務欄位</h2>";
echo "<p>系統使用者數: " . count($userMap) . "</p>";

// 取得所有 sales_id 為空的收款單
$stmt = $db->query("SELECT id, receipt_number, note FROM receipts WHERE (sales_id IS NULL OR sales_id = 0) AND note LIKE '%業務:%'");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<p>待修復筆數: " . count($rows) . "</p>";

$updated = 0;
$notFound = array();
$updateStmt = $db->prepare("UPDATE receipts SET sales_id = ? WHERE id = ?");

foreach ($rows as $row) {
    // 解析 [業務:xxx]
    if (preg_match('/\[業務[:：]([^\]]+)\]/', $row['note'], $m)) {
        $salesName = trim($m[1]);
        if (isset($userMap[$salesName])) {
            $updateStmt->execute(array($userMap[$salesName], $row['id']));
            $updated++;
        } else {
            $notFound[$salesName] = isset($notFound[$salesName]) ? $notFound[$salesName] + 1 : 1;
        }
    }
}

echo "<p style='color:green'>已更新: {$updated} 筆</p>";

if (!empty($notFound)) {
    echo "<h3>找不到對應使用者的業務名稱:</h3><ul>";
    foreach ($notFound as $name => $cnt) {
        echo "<li>{$name} ({$cnt} 筆)</li>";
    }
    echo "</ul>";
}

// 同時修復應收帳款
$stmt2 = $db->query("SELECT id, invoice_number, note FROM receivables WHERE (sales_id IS NULL OR sales_id = 0) AND note LIKE '%業務:%'");
$rows2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
$updated2 = 0;
$notFound2 = array();
$updateStmt2 = $db->prepare("UPDATE receivables SET sales_id = ? WHERE id = ?");

foreach ($rows2 as $row) {
    if (preg_match('/\[業務[:：]([^\]]+)\]/', $row['note'], $m)) {
        $salesName = trim($m[1]);
        if (isset($userMap[$salesName])) {
            $updateStmt2->execute(array($userMap[$salesName], $row['id']));
            $updated2++;
        } else {
            $notFound2[$salesName] = isset($notFound2[$salesName]) ? $notFound2[$salesName] + 1 : 1;
        }
    }
}

echo "<hr><h2>修復應收帳款業務欄位</h2>";
echo "<p>待修復筆數: " . count($rows2) . "</p>";
echo "<p style='color:green'>已更新: {$updated2} 筆</p>";

if (!empty($notFound2)) {
    echo "<h3>找不到對應使用者的業務名稱:</h3><ul>";
    foreach ($notFound2 as $name => $cnt) {
        echo "<li>{$name} ({$cnt} 筆)</li>";
    }
    echo "</ul>";
}

echo "<hr><p><a href='/receipts.php'>返回收款單</a> | <a href='/receivables.php'>返回應收帳款</a></p>";
