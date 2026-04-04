<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();
$stmt = $db->query("SELECT id, payable_number, create_date, vendor_name, ragic_id FROM payables ORDER BY id DESC LIMIT 5");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo $r['id'] . ' | ' . $r['payable_number'] . ' | date=' . $r['create_date'] . ' | ' . $r['vendor_name'] . ' | ragic=' . $r['ragic_id'] . "\n";
}

echo "\n--- P1-20260223-051 ---\n";
$stmt2 = $db->prepare("SELECT * FROM payables WHERE payable_number = ?");
$stmt2->execute(array('P1-20260223-051'));
$r = $stmt2->fetch(PDO::FETCH_ASSOC);
if ($r) {
    foreach ($r as $k => $v) echo "$k: $v\n";
}
