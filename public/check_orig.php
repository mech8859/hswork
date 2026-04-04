<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Session::getUser()['role'] !== 'boss') { die('需要管理員權限'); }
header('Content-Type: text/plain; charset=utf-8');
$db = Database::getInstance();

// 查前20筆的 original_customer_no
$stmt = $db->query("SELECT customer_no, original_customer_no, legacy_customer_no FROM customers LIMIT 20");
echo "=== 前20筆 ===\n";
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "{$r['customer_no']} | orig={$r['original_customer_no']} | legacy={$r['legacy_customer_no']}\n";
}

// 統計
$total = $db->query("SELECT COUNT(*) FROM customers")->fetchColumn();
$has_orig = $db->query("SELECT COUNT(*) FROM customers WHERE original_customer_no IS NOT NULL AND original_customer_no != '' AND original_customer_no != '匯入用'")->fetchColumn();
$is_import = $db->query("SELECT COUNT(*) FROM customers WHERE original_customer_no = '匯入用'")->fetchColumn();
$is_null = $db->query("SELECT COUNT(*) FROM customers WHERE original_customer_no IS NULL OR original_customer_no = ''")->fetchColumn();

echo "\n=== 統計 ===\n";
echo "總筆數: {$total}\n";
echo "有原始編號: {$has_orig}\n";
echo "顯示匯入用: {$is_import}\n";
echo "空值: {$is_null}\n";

// 看前端顯示的是哪個欄位
echo "\n=== A-000512 ===\n";
$stmt = $db->prepare("SELECT customer_no, original_customer_no, legacy_customer_no FROM customers WHERE customer_no = ?");
$stmt->execute(array('A-000512'));
$r = $stmt->fetch(PDO::FETCH_ASSOC);
print_r($r);

echo "\n=== A-009984 ===\n";
$stmt->execute(array('A-009984'));
$r = $stmt->fetch(PDO::FETCH_ASSOC);
print_r($r);
