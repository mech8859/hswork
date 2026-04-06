<?php
/**
 * 一次性：批次回算所有案件的 total_collected
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();

$stmt = $db->query("
    SELECT cp.case_id, SUM(cp.amount) AS total
    FROM case_payments cp
    GROUP BY cp.case_id
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$updated = 0;
foreach ($rows as $row) {
    $total = (int)$row['total'];
    $db->prepare("UPDATE cases SET total_collected = ? WHERE id = ?")->execute(array($total, $row['case_id']));
    $updated++;
}

// 沒有交易紀錄的案件設為 0
$db->exec("UPDATE cases SET total_collected = 0 WHERE id NOT IN (SELECT DISTINCT case_id FROM case_payments) AND total_collected IS NOT NULL AND total_collected != 0");

echo "Updated {$updated} cases with total_collected.\n";
echo "Done.\n";
