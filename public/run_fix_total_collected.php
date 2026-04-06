<?php
/**
 * 一次性：批次回算所有案件的 total_collected + 訂金金額/方式/日期
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();

// 總收款
$stmt = $db->query("
    SELECT cp.case_id, SUM(cp.amount) AS total
    FROM case_payments cp
    GROUP BY cp.case_id
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$updated = 0;
foreach ($rows as $row) {
    $caseId = (int)$row['case_id'];
    $total = (int)$row['total'];

    // 訂金
    $depStmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM case_payments WHERE case_id = ? AND payment_type = '訂金'");
    $depStmt->execute(array($caseId));
    $depAmount = (int)$depStmt->fetchColumn();

    $depInfoStmt = $db->prepare("SELECT transaction_type, payment_date FROM case_payments WHERE case_id = ? AND payment_type = '訂金' ORDER BY payment_date DESC, id DESC LIMIT 1");
    $depInfoStmt->execute(array($caseId));
    $depInfo = $depInfoStmt->fetch(PDO::FETCH_ASSOC);

    $db->prepare("UPDATE cases SET total_collected = ?, deposit_amount = ?, deposit_method = ?, deposit_payment_date = ? WHERE id = ?")
        ->execute(array($total, $depAmount ?: null, $depInfo ? $depInfo['transaction_type'] : null, $depInfo ? $depInfo['payment_date'] : null, $caseId));
    $updated++;
}

// 沒有交易紀錄的案件
$db->exec("UPDATE cases SET total_collected = 0 WHERE id NOT IN (SELECT DISTINCT case_id FROM case_payments) AND total_collected IS NOT NULL AND total_collected != 0");

echo "Updated {$updated} cases with total_collected + deposit info.\n";
echo "Done.\n";
