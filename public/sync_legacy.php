<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Session::getUser()['role'] !== 'boss') { die('需要管理員權限'); }
header('Content-Type: text/plain; charset=utf-8');
$db = Database::getInstance();

$affected = $db->exec("UPDATE customers SET legacy_customer_no = original_customer_no WHERE original_customer_no IS NOT NULL AND original_customer_no != '' AND (legacy_customer_no IS NULL OR legacy_customer_no = '')");
echo "同步 original_customer_no → legacy_customer_no: {$affected} 筆\n";

// 驗證
$stmt = $db->query("SELECT customer_no, original_customer_no, legacy_customer_no FROM customers WHERE customer_no IN ('A-000512','A-009984','A-000001') ORDER BY customer_no");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "{$r['customer_no']} | orig={$r['original_customer_no']} | legacy={$r['legacy_customer_no']}\n";
}
