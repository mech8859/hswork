<?php
/**
 * 一次性重算所有案件的 balance_amount（尾款）
 * 公式：balance = (total_amount > 0 ? total_amount : deal_amount) - total_collected
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');
header('Content-Type: text/html; charset=utf-8');

echo "<meta charset='utf-8'><h2>重算所有案件尾款</h2>";

$db = Database::getInstance();

// 重新計算每個案件的 total_collected 與 balance_amount
$sql = "SELECT c.id, c.deal_amount, c.total_amount,
        (SELECT COALESCE(SUM(amount), 0) FROM case_payments WHERE case_id = c.id) AS total_collected
        FROM cases c
        WHERE c.deal_amount > 0 OR c.total_amount > 0";
$stmt = $db->query($sql);
$updated = 0;
$skipped = 0;
$updateStmt = $db->prepare("UPDATE cases SET total_collected = ?, balance_amount = ? WHERE id = ?");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $base = ((int)$row['total_amount']) > 0 ? (int)$row['total_amount'] : (int)$row['deal_amount'];
    if ($base <= 0) { $skipped++; continue; }
    $balance = max(0, $base - (int)$row['total_collected']);
    $updateStmt->execute(array((int)$row['total_collected'], $balance, (int)$row['id']));
    $updated++;
}

echo "<p style='color:green'>OK 已重算 $updated 筆案件的尾款（跳過 $skipped 筆無金額案件）</p>";
echo "<p><a href='/cases.php'>返回案件管理</a></p>";
