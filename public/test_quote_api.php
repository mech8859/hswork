<?php
require_once __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();

echo "=== 報價單狀態統計 ===\n";
$stmt = $db->query("SELECT status, COUNT(*) as cnt FROM quotations GROUP BY status ORDER BY cnt DESC");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo "  {$row['status']}: {$row['cnt']}\n";
}

echo "\n=== customer_accepted 或 accepted 的報價單 ===\n";
$stmt = $db->query("SELECT id, quotation_number, customer_name, total_amount, status FROM quotations WHERE status IN ('customer_accepted','accepted') ORDER BY created_at DESC LIMIT 10");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "筆數: " . count($rows) . "\n";
foreach ($rows as $r) {
    echo "  [{$r['id']}] {$r['quotation_number']} | {$r['customer_name']} | \${$r['total_amount']} | {$r['status']}\n";
}

echo "\n=== API 模擬 (q='') ===\n";
$stmt = $db->query("SELECT quotation_number, customer_name, total_amount FROM quotations WHERE status IN ('customer_accepted','accepted') ORDER BY created_at DESC LIMIT 30");
$apiResult = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "回傳筆數: " . count($apiResult) . "\n";
echo json_encode($apiResult, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
