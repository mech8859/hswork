<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
header('Content-Type: application/json; charset=utf-8');
$db = Database::getInstance();
$stmt = $db->query("SELECT id, customer_no FROM customers");
$map = array();
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $map[$r['customer_no']] = (int)$r['id'];
}
echo json_encode($map);
