<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();
$stmt = $db->query("SELECT ri.*, r.requisition_number FROM requisition_items ri JOIN requisitions r ON ri.requisition_id = r.id ORDER BY ri.requisition_id DESC, ri.id LIMIT 30");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo $r['requisition_number'] . ' | ' . $r['product_name'] . ' | qty=' . $r['quantity'] . ' | price=' . $r['unit_price'] . ' | approved=' . $r['approved_qty'] . "\n";
}
