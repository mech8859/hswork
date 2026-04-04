<?php
header('Access-Control-Allow-Origin: https://ap15.ragic.com');
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/bootstrap.php';
$db = Database::getInstance();

// 找所有 image_path 為空的帳款交易
$stmt = $db->query("
    SELECT cp.id, cp.case_id, c.case_number, cp.ragic_id, cp.payment_date, cp.amount
    FROM case_payments cp
    JOIN cases c ON c.id = cp.case_id
    WHERE (cp.image_path IS NULL OR cp.image_path = '')
    AND cp.ragic_id IS NOT NULL
    ORDER BY c.case_number
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(array('total' => count($rows), 'payments' => $rows), JSON_UNESCAPED_UNICODE);
