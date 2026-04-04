<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();
$sqls = array(
    "ALTER TABLE cases ADD COLUMN completion_date DATE DEFAULT NULL COMMENT '完工日期' AFTER completion_amount"
);
foreach ($sqls as $sql) {
    try {
        $db->exec($sql);
        echo "[OK] $sql\n";
    } catch (Exception $e) {
        echo "[SKIP] " . $e->getMessage() . "\n";
    }
}

// 從已關聯的客戶同步現有完工日期到案件
try {
    $count = $db->exec("UPDATE cases c INNER JOIN customers cu ON c.customer_id = cu.id SET c.completion_date = cu.completion_date WHERE cu.completion_date IS NOT NULL AND cu.completion_date != '0000-00-00' AND c.completion_date IS NULL");
    echo "[OK] 同步客戶完工日期到案件: {$count} 筆\n";
} catch (Exception $e) {
    echo "[SKIP] sync: " . $e->getMessage() . "\n";
}

echo "\nDone!\n";
