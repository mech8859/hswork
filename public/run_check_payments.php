<?php
header('Content-Type: application/json; charset=utf-8');
try {
    $db = new PDO('mysql:host=localhost;port=3306;dbname=vhost158992;charset=utf8mb4', 'vhost158992', 'Kas199306', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    $stmt = $db->prepare('SELECT cp.id, cp.case_id, cp.payment_date, cp.payment_type, cp.transaction_type, cp.amount, cp.note, cp.ragic_id
        FROM case_payments cp
        JOIN cases c ON cp.case_id = c.id
        WHERE c.case_number = ?');
    $stmt->execute(['2026-1643']);
    $target = $stmt->fetchAll();

    $txTypes = $db->query('SELECT transaction_type, COUNT(*) as cnt FROM case_payments GROUP BY transaction_type ORDER BY cnt DESC')->fetchAll();
    $payTypes = $db->query('SELECT payment_type, COUNT(*) as cnt FROM case_payments GROUP BY payment_type ORDER BY cnt DESC')->fetchAll();
    $total = $db->query('SELECT COUNT(*) FROM case_payments')->fetchColumn();

    echo json_encode([
        'case_2026_1643' => $target,
        'transaction_types' => $txTypes,
        'payment_types' => $payTypes,
        'total_records' => $total
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
