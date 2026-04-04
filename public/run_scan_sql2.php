<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
header('Content-Type: text/plain; charset=utf-8');
$db = Database::getInstance();
$sql = file_get_contents(__DIR__ . '/../database/customer_scan_import.sql');
$statements = array_filter(array_map('trim', explode(";\n", $sql)));
foreach ($statements as $stmt) {
    $stmt = rtrim(trim($stmt), ';');
    if (empty($stmt) || strpos($stmt, '--') === 0 || strpos($stmt, 'SET') === 0) continue;
    try { $db->exec($stmt); } catch (PDOException $e) { echo "ERR: " . $e->getMessage() . "\n"; }
}
echo "OK: customer_files = " . $db->query("SELECT COUNT(*) FROM customer_files")->fetchColumn() . "\n";
