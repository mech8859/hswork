<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();

echo "=== 員工編號檢查 ===\n\n";

// 有員工編號的
$has = $db->query("SELECT COUNT(*) FROM users WHERE is_active = 1 AND employee_no IS NOT NULL AND employee_no != ''")->fetchColumn();
echo "有員工編號: {$has} 筆\n";

// 沒有員工編號的
$no = $db->query("SELECT COUNT(*) FROM users WHERE is_active = 1 AND (employee_no IS NULL OR employee_no = '')")->fetchColumn();
echo "無員工編號: {$no} 筆\n\n";

// 列出前10筆有/無的看看
echo "--- 有員工編號（前10筆）---\n";
$rows = $db->query("SELECT id, username, real_name, employee_no, employment_status FROM users WHERE is_active = 1 AND employee_no IS NOT NULL AND employee_no != '' LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo "  ID:{$r['id']} | {$r['username']} | {$r['real_name']} | 編號:{$r['employee_no']} | {$r['employment_status']}\n";
}

echo "\n--- 無員工編號（前10筆）---\n";
$rows2 = $db->query("SELECT id, username, real_name, employee_no, employment_status FROM users WHERE is_active = 1 AND (employee_no IS NULL OR employee_no = '') LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows2 as $r) {
    $eno = $r['employee_no'] === null ? 'NULL' : "'{$r['employee_no']}'";
    echo "  ID:{$r['id']} | {$r['username']} | {$r['real_name']} | 編號:{$eno} | {$r['employment_status']}\n";
}

// 看看有沒有其他欄位可能存編號
echo "\n--- SHOW COLUMNS LIKE '%no%' or '%emp%' ---\n";
$cols = $db->query("SHOW COLUMNS FROM users WHERE Field LIKE '%no%' OR Field LIKE '%emp%' OR Field LIKE '%number%'")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) {
    echo "  {$c['Field']} ({$c['Type']})\n";
}
