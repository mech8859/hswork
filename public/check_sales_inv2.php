<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();
$stmt = $db->query("SELECT si.id, si.invoice_number, si.invoice_date, si.status FROM sales_invoices si ORDER BY si.invoice_date DESC, si.id DESC LIMIT 10");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Direct query top 10:\n";
foreach ($rows as $r) {
    echo $r['id'] . ' | ' . $r['invoice_number'] . ' | ' . $r['invoice_date'] . ' | ' . $r['status'] . "\n";
}

echo "\n--- Using model ---\n";
require_once __DIR__ . '/../modules/accounting/InvoiceModel.php';
$model = new InvoiceModel();
$result = $model->getSalesInvoices(array(), 1);
echo "Total: " . $result['total'] . "\n";
echo "Data count: " . count($result['data']) . "\n";
echo "Page: " . $result['page'] . "\n";
echo "LastPage: " . $result['lastPage'] . "\n";
if (count($result['data']) > 0) {
    echo "First: " . $result['data'][0]['invoice_number'] . "\n";
    echo "Last: " . $result['data'][count($result['data'])-1]['invoice_number'] . "\n";
}
