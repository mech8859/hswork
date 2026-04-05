<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();

$statusMap = array(
    'tracking' => '待追蹤', 'incomplete' => '未完工', 'closed' => '已完工結案',
    'unpaid' => '完工未收款', 'awaiting_dispatch' => '待安排派工查修',
    'customer_cancel' => '客戶取消', 'lost' => '未成交',
    'maint_case' => '保養案件', 'breach' => '毀約'
);

echo "=== 待排工/施工中 但已完工的案件 ===\n\n";

// 待排工 = stage 4, 施工中 = stage 5 (推測)
// 先查 stage 欄位的分佈
$stages = $db->query("SELECT stage, COUNT(*) as cnt FROM cases WHERE stage IN (4,5) GROUP BY stage")->fetchAll(PDO::FETCH_ASSOC);
echo "--- Stage 分佈 ---\n";
foreach ($stages as $s) {
    $label = $s['stage'] == 4 ? '待排工(stage=4)' : '施工中(stage=5)';
    echo "  {$label}: {$s['cnt']} 筆\n";
}

// 待排工中已完工
$sql1 = "SELECT case_number, status, is_completed, completion_date, customer_name
         FROM cases WHERE stage = 4
         AND (status = 'closed' OR is_completed = 1 OR (completion_date IS NOT NULL AND completion_date != ''))
         ORDER BY case_number DESC";
$rows1 = $db->query($sql1)->fetchAll(PDO::FETCH_ASSOC);
$count1 = count($rows1);

echo "\n--- 待排工(stage=4) 但已完工/有完工日期/進度結案 ({$count1} 筆) ---\n";
foreach ($rows1 as $r) {
    $statusCn = isset($statusMap[$r['status']]) ? $statusMap[$r['status']] : $r['status'];
    $comp = $r['is_completed'] ? '已完工' : '未完工';
    $date = $r['completion_date'] ?: '(無)';
    echo "  {$r['case_number']} | 進度:{$statusCn} | 是否完工:{$comp} | 完工日期:{$date} | {$r['customer_name']}\n";
}

// 施工中已完工
$sql2 = "SELECT case_number, status, is_completed, completion_date, customer_name
         FROM cases WHERE stage = 5
         AND (status = 'closed' OR is_completed = 1 OR (completion_date IS NOT NULL AND completion_date != ''))
         ORDER BY case_number DESC";
$rows2 = $db->query($sql2)->fetchAll(PDO::FETCH_ASSOC);
$count2 = count($rows2);

echo "\n--- 施工中(stage=5) 但已完工/有完工日期/進度結案 ({$count2} 筆) ---\n";
foreach ($rows2 as $r) {
    $statusCn = isset($statusMap[$r['status']]) ? $statusMap[$r['status']] : $r['status'];
    $comp = $r['is_completed'] ? '已完工' : '未完工';
    $date = $r['completion_date'] ?: '(無)';
    echo "  {$r['case_number']} | 進度:{$statusCn} | 是否完工:{$comp} | 完工日期:{$date} | {$r['customer_name']}\n";
}

echo "\n=== 摘要 ===\n";
echo "待排工 343 筆中，已完工相關: {$count1} 筆\n";
echo "施工中 299 筆中，已完工相關: {$count2} 筆\n";
