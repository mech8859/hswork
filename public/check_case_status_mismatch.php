<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();

$statusMap = array(
    'tracking' => '待追蹤',
    'incomplete' => '未完工',
    'closed' => '已完工結案',
    'unpaid' => '完工未收款',
    'awaiting_dispatch' => '待安排派工查修',
    'customer_cancel' => '客戶取消',
    'lost' => '未成交',
    'maint_case' => '保養案件',
    'breach' => '毀約'
);

echo "=== 案件進度與完工狀態不一致檢查 ===\n\n";

// 1. 案件進度=未完工 或 待追蹤，但有完工日期
$sql1 = "SELECT case_number, status, completion_date, is_completed, customer_name
         FROM cases
         WHERE status IN ('incomplete','tracking')
         AND completion_date IS NOT NULL AND completion_date != ''
         ORDER BY case_number DESC";
$rows1 = $db->query($sql1)->fetchAll(PDO::FETCH_ASSOC);

$count1 = count($rows1);
echo "--- 1. 進度=未完工/待追蹤，但有完工日期 ({$count1} 筆) ---\n";
foreach ($rows1 as $r) {
    $completed = $r['is_completed'] ? '已完工' : '未完工';
    $statusCn = isset($statusMap[$r['status']]) ? $statusMap[$r['status']] : $r['status'];
    echo "  {$r['case_number']} | 進度:{$statusCn} | 完工日期:{$r['completion_date']} | 是否完工:{$completed} | {$r['customer_name']}\n";
}

// 2. 案件進度=未完工 或 待追蹤，但是否已完工=1
$sql2 = "SELECT case_number, status, completion_date, is_completed, customer_name
         FROM cases
         WHERE status IN ('incomplete','tracking')
         AND is_completed = 1
         ORDER BY case_number DESC";
$rows2 = $db->query($sql2)->fetchAll(PDO::FETCH_ASSOC);

$count2 = count($rows2);
echo "\n--- 2. 進度=未完工/待追蹤，但是否已完工=已完工 ({$count2} 筆) ---\n";
foreach ($rows2 as $r) {
    $statusCn = isset($statusMap[$r['status']]) ? $statusMap[$r['status']] : $r['status'];
    echo "  {$r['case_number']} | 進度:{$statusCn} | 完工日期:{$r['completion_date']} | {$r['customer_name']}\n";
}

// 3. 已完工結案但沒有完工日期
$sql3 = "SELECT case_number, status, completion_date, is_completed, customer_name
         FROM cases
         WHERE status = 'closed'
         AND (completion_date IS NULL OR completion_date = '')
         ORDER BY case_number DESC";
$rows3 = $db->query($sql3)->fetchAll(PDO::FETCH_ASSOC);

$count3 = count($rows3);
echo "\n--- 3. 進度=已完工結案，但沒有完工日期 ({$count3} 筆) ---\n";
foreach ($rows3 as $r) {
    $completed = $r['is_completed'] ? '已完工' : '未完工';
    echo "  {$r['case_number']} | 是否完工:{$completed} | {$r['customer_name']}\n";
}

// 4. 有完工日期但是否已完工=0
$sql4 = "SELECT case_number, status, completion_date, is_completed, customer_name
         FROM cases
         WHERE completion_date IS NOT NULL AND completion_date != ''
         AND is_completed = 0
         ORDER BY case_number DESC";
$rows4 = $db->query($sql4)->fetchAll(PDO::FETCH_ASSOC);

$count4 = count($rows4);
echo "\n--- 4. 有完工日期但是否已完工=未完工 ({$count4} 筆) ---\n";
foreach ($rows4 as $r) {
    $statusCn = isset($statusMap[$r['status']]) ? $statusMap[$r['status']] : $r['status'];
    echo "  {$r['case_number']} | 進度:{$statusCn} | 完工日期:{$r['completion_date']} | {$r['customer_name']}\n";
}

echo "\n=== 摘要 ===\n";
echo "1. 未完工/待追蹤 但有完工日期: {$count1} 筆\n";
echo "2. 未完工/待追蹤 但標記已完工: {$count2} 筆\n";
echo "3. 已完工結案 但沒完工日期: {$count3} 筆\n";
echo "4. 有完工日期 但標記未完工: {$count4} 筆\n";
