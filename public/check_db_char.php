<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Session::getUser()['role'] !== 'boss') { die('需要管理員權限'); }
header('Content-Type: text/plain; charset=utf-8');
$db = Database::getInstance();
$db->exec("SET NAMES utf8mb4");
$stmt = $db->query("SELECT customer_no, name, HEX(name) as hex_name FROM customers WHERE customer_no = 'A-001832'");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo "customer_no: {$row['customer_no']}\n";
echo "name: {$row['name']}\n";
echo "hex: {$row['hex_name']}\n";
echo "contains ?: " . (strpos($row['name'], '?') !== false ? 'YES' : 'NO') . "\n";
echo "contains 𪝞: " . (strpos($row['name'], '𪝞') !== false ? 'YES' : 'NO') . "\n";
