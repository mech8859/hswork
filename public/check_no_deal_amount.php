<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();

$statusMap = array(
    'incomplete' => '未完工',
    'unpaid' => '完工未收款',
    'pending_approval' => '已完工待簽核',
    'closed' => '已完工結案'
);

echo "=== 已成交案件但無成交金額 ===\n\n";

$statuses = array_keys($statusMap);
$placeholders = implode(',', array_fill(0, count($statuses), '?'));

$sql = "SELECT case_number, status, sub_status, customer_name, deal_amount, deal_date,
               quote_amount, title, branch_id, b.name AS branch_name
        FROM cases c
        LEFT JOIN branches b ON c.branch_id = b.id
        WHERE c.status IN ({$placeholders})
        AND (c.deal_amount IS NULL OR c.deal_amount = 0 OR c.deal_amount = '')
        ORDER BY c.status, c.case_number DESC";

$rows = $db->prepare($sql);
$rows->execute($statuses);
$rows = $rows->fetchAll(PDO::FETCH_ASSOC);

$byStatus = array();
foreach ($rows as $r) {
    $key = $r['status'];
    if (!isset($byStatus[$key])) $byStatus[$key] = array();
    $byStatus[$key][] = $r;
}

$total = 0;
foreach ($statusMap as $key => $label) {
    $items = isset($byStatus[$key]) ? $byStatus[$key] : array();
    $cnt = count($items);
    $total += $cnt;
    echo "--- {$label} ({$cnt} 筆) ---\n";
    foreach ($items as $r) {
        $quote = $r['quote_amount'] ? number_format($r['quote_amount']) : '無';
        $branch = $r['branch_name'] ?: '';
        echo "  {$r['case_number']} | {$r['customer_name']} | {$branch} | 報價:{$quote} | 狀態:{$r['sub_status']}\n";
    }
    echo "\n";
}

echo "=== 摘要 ===\n";
foreach ($statusMap as $key => $label) {
    $cnt = isset($byStatus[$key]) ? count($byStatus[$key]) : 0;
    echo "{$label}: {$cnt} 筆\n";
}
echo "總計: {$total} 筆\n";
