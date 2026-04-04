<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
header('Content-Type: text/plain; charset=utf-8');
$db = Database::getInstance();

// 改成半形 A，同時更新 last_sequence 為目前最大值
$maxSeq = $db->query("SELECT MAX(CAST(SUBSTRING_INDEX(customer_no, '-', -1) AS UNSIGNED)) FROM customers WHERE customer_no LIKE 'A-%'")->fetchColumn();
echo "目前最大客戶編號序號: {$maxSeq}\n";

$db->prepare("UPDATE number_sequences SET prefix = 'A', last_sequence = ?, last_reset_key = 'ALL' WHERE module = 'customers'")->execute(array($maxSeq));
echo "已更新: prefix=A, last_sequence={$maxSeq}\n";

echo "下一個客戶編號: " . peek_next_doc_number('customers') . "\n";
