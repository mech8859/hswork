<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();

echo "=== 已完工結案但資料不完整的案件 ===\n\n";

$sql = "SELECT case_number, status, is_completed, completion_date, customer_name, branch_id
        FROM cases
        WHERE status = 'closed'
        AND (is_completed = 0 OR is_completed IS NULL OR completion_date IS NULL OR completion_date = '')
        ORDER BY case_number DESC";
$rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$noCompleted = 0;
$noDate = 0;
$both = 0;

foreach ($rows as $r) {
    $isComp = $r['is_completed'] ? '已完工' : '未完工';
    $date = $r['completion_date'] ?: '(無)';
    $issues = array();
    $missingComp = empty($r['is_completed']);
    $missingDate = empty($r['completion_date']);
    if ($missingComp) $issues[] = '未標記完工';
    if ($missingDate) $issues[] = '無完工日期';
    if ($missingComp && $missingDate) $both++;
    elseif ($missingComp) $noCompleted++;
    elseif ($missingDate) $noDate++;

    echo "{$r['case_number']} | {$r['customer_name']} | 是否完工:{$isComp} | 完工日期:{$date} | " . implode('、', $issues) . "\n";
}

$total = count($rows);
echo "\n=== 摘要 ===\n";
echo "總計: {$total} 筆\n";
echo "只缺「是否已完工」: {$noCompleted} 筆\n";
echo "只缺「完工日期」: {$noDate} 筆\n";
echo "兩個都缺: {$both} 筆\n";
