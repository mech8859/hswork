<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
header('Content-Type: text/plain; charset=utf-8');
$db = Database::getInstance();
$stmt = $db->prepare("SELECT * FROM customer_files WHERE customer_id = ?");
$stmt->execute(array(16564));
$files = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($files as $f) {
    echo "id: {$f['id']}\n";
    echo "file_name: {$f['file_name']}\n";
    echo "file_path: {$f['file_path']}\n";
    echo "file_type: {$f['file_type']}\n";
    echo "---\n";
}
