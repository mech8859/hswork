<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Session::getUser()['role'] !== 'boss') { die('需要管理員權限'); }
header('Content-Type: text/plain; charset=utf-8');
$db = Database::getInstance();

$affected = $db->exec("UPDATE customers SET case_number = CONCAT(case_number, '-C') WHERE case_number LIKE '2026-%' AND case_number NOT LIKE '%-C'");
echo "修改 2026-XXXX → 2026-XXXX-C: {$affected} 筆\n";

// 驗證
$stmt = $db->query("SELECT case_number, name FROM customers WHERE case_number LIKE '2026%' LIMIT 5");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "  {$r['case_number']} | {$r['name']}\n";
}
$total = $db->query("SELECT COUNT(*) FROM customers WHERE case_number LIKE '2026%-C'")->fetchColumn();
echo "\n2026-C 總筆數: {$total}\n";
