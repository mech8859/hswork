<?php
date_default_timezone_set('Asia/Taipei');
ini_set('display_errors', '1');
error_reporting(E_ALL);
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../includes/Database.php';

$db = Database::getInstance();
$mode = isset($_GET['run']) ? 'execute' : 'preview';
$target = isset($_GET['all']) ? 'all' : 'single';

echo "=== fix case ===\nmode: $mode | target: $target\n\n";

// 找 case_number 含 1915 的
echo "--- search 1915 ---\n";
$chk = $db->query("SELECT id, case_number, sub_status, status, deal_amount, total_amount, total_collected, balance_amount FROM cases WHERE case_number LIKE '%1915%'");
foreach ($chk->fetchAll(PDO::FETCH_ASSOC) as $c) {
    echo 'id=' . $c['id'] . ' ' . $c['case_number'] . ' sub_status=' . $c['sub_status'] . ' status=' . $c['status'] . ' deal=' . $c['deal_amount'] . ' total_amt=' . $c['total_amount'] . ' collected=' . $c['total_collected'] . ' balance=' . $c['balance_amount'] . "\n";

    // 簽核
    $af = $db->prepare("SELECT level_order, status, payload FROM approval_flows WHERE module='case_completion' AND target_id=? ORDER BY level_order");
    $af->execute(array($c['id']));
    foreach ($af->fetchAll(PDO::FETCH_ASSOC) as $f) {
        echo '  L' . $f['level_order'] . ' ' . $f['status'] . ' payload:' . $f['payload'] . "\n";
    }
}

echo "\n--- all L3 approved but not closed ---\n";
$sql = "SELECT c.id, c.case_number, c.sub_status, c.status, c.deal_amount, c.total_amount, c.total_collected,
    GREATEST(COALESCE(CASE WHEN c.total_amount > 0 THEN c.total_amount ELSE c.deal_amount END, 0) - COALESCE(c.total_collected, 0), 0) AS real_balance
FROM cases c
WHERE c.status NOT IN ('closed')
AND EXISTS (SELECT 1 FROM approval_flows af WHERE af.module='case_completion' AND af.target_id=c.id AND af.level_order=3 AND af.status='approved')";

if ($target === 'single') {
    $sql .= " AND c.case_number LIKE '%1915%'";
}

$stmt = $db->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$fixed = 0;

foreach ($rows as $row) {
    $rb = (int)$row['real_balance'];
    $action = $rb > 0 ? 'SKIP(balance=$' . number_format($rb) . ')' : 'CLOSE';
    echo $row['case_number'] . ' sub_status=' . $row['sub_status'] . ' deal=' . number_format((int)$row['deal_amount']) . ' collected=' . number_format((int)$row['total_collected']) . ' real_balance=' . number_format($rb) . ' -> ' . $action . "\n";

    if ($rb <= 0 && $mode === 'execute') {
        $db->prepare("UPDATE cases SET status='closed', sub_status='已完工結案' WHERE id=?")->execute(array($row['id']));
        echo "  DONE!\n";
        $fixed++;
    }
}

echo "\ntotal: " . count($rows) . " | fixed: $fixed\n";
