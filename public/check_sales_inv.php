<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();

$total = $db->query("SELECT COUNT(*) FROM sales_invoices")->fetchColumn();
echo "Total records: $total\n";

$stmt = $db->query("SELECT id, invoice_number, invoice_date, status, customer_name FROM sales_invoices ORDER BY id DESC LIMIT 5");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo $r['id'] . ' | ' . $r['invoice_number'] . ' | ' . $r['invoice_date'] . ' | ' . $r['status'] . ' | ' . $r['customer_name'] . "\n";
}

echo "\nStatus distribution:\n";
$stmt2 = $db->query("SELECT status, COUNT(*) as cnt FROM sales_invoices GROUP BY status");
foreach ($stmt2->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo $r['status'] . ': ' . $r['cnt'] . "\n";
}

echo "\nPeriod distribution (top 5):\n";
$stmt3 = $db->query("SELECT period, COUNT(*) as cnt FROM sales_invoices GROUP BY period ORDER BY period DESC LIMIT 5");
foreach ($stmt3->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo ($r['period'] ?: 'NULL') . ': ' . $r['cnt'] . "\n";
}
