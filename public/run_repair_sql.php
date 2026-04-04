<?php
if (!isset($_GET['token']) || $_GET['token'] !== 'hswork2026fix') { die('token required'); }
header('Content-Type: text/plain; charset=utf-8');
try {
    $db = new PDO('mysql:host=localhost;dbname=vhost158992;charset=utf8mb4', 'vhost158992', 'Kss9227456');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $sql = file_get_contents(__DIR__ . '/../database/repair_scan_import.sql');
    $statements = array_filter(array_map('trim', explode(";\n", $sql)));
    $ok = 0;
    foreach ($statements as $stmt) {
        $stmt = rtrim(trim($stmt), ';');
        if (empty($stmt) || strpos($stmt, '--') === 0 || strpos($stmt, 'SET') === 0) continue;
        $db->exec($stmt);
        $ok++;
    }
    $count = $db->query("SELECT COUNT(*) FROM customer_files")->fetchColumn();
    echo "INSERT: " . $ok . " batches\n";
    echo "customer_files total: " . $count . "\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
