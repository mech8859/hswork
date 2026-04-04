<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
header('Content-Type: text/plain; charset=utf-8');
$db = Database::getInstance();

// cases 表的 2026 最大編號
$max = $db->query("SELECT MAX(case_number) FROM cases WHERE case_number LIKE '2026-%'")->fetchColumn();
echo "cases 表 2026 最大: {$max}\n";

$total2026 = $db->query("SELECT COUNT(*) FROM cases WHERE case_number LIKE '2026-%'")->fetchColumn();
echo "cases 表 2026 筆數: {$total2026}\n";

// number_sequences 的 cases 設定
$stmt = $db->query("SELECT * FROM number_sequences WHERE module = 'cases'");
$seq = $stmt->fetch(PDO::FETCH_ASSOC);
echo "\nnumber_sequences:\n";
print_r($seq);

// 測試 peek
echo "\npeek_next: " . peek_next_doc_number('cases') . "\n";
