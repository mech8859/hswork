<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
header('Content-Type: text/plain; charset=utf-8');
$db = Database::getInstance();

// 直接測 SQL
$stmt = $db->query("SELECT MAX(CAST(SUBSTRING_INDEX(case_number, '-', -1) AS UNSIGNED)) FROM cases WHERE case_number LIKE '2026-%'");
echo "MAX seq from cases: " . $stmt->fetchColumn() . "\n";

// 看幾筆範例
$stmt = $db->query("SELECT case_number, SUBSTRING_INDEX(case_number, '-', -1) as seq FROM cases WHERE case_number LIKE '2026-%' ORDER BY case_number DESC LIMIT 5");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "  {$r['case_number']} → seq={$r['seq']}\n";
}

// 重新測 peek
echo "\npeek_next: " . peek_next_doc_number('cases') . "\n";
