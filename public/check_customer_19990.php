<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();
// 查 customers 表
$stmt = $db->prepare("SELECT id, name FROM customers WHERE id = 19990 OR name LIKE '%19990%'");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>customers 表:\n";
print_r($rows);
// 查 cases 表
$stmt = $db->prepare("SELECT id, case_number, customer_name, customer_id FROM cases WHERE customer_name LIKE '%19990%' OR customer_id = 19990 LIMIT 5");
$stmt->execute();
$rows2 = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "\ncases 表:\n";
print_r($rows2);
echo "</pre>";
