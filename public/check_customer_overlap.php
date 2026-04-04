<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
header('Content-Type: application/json; charset=utf-8');

$db = Database::getInstance();

// 案件管理中 2026 年的案件，有 customer_no 的
$stmt = $db->query("
    SELECT c.id, c.case_number, c.customer_name, c.customer_no, c.customer_id,
           cu.customer_no as cu_customer_no, cu.name as cu_name, cu.completion_date
    FROM cases c
    LEFT JOIN customers cu ON c.customer_id = cu.id
    WHERE c.case_number LIKE '2026-%'
    AND c.customer_no IS NOT NULL AND c.customer_no != ''
    ORDER BY c.case_number
");
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(array('total' => count($results), 'data' => $results), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
