<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Session::getUser()['role'] !== 'boss') { die('需要管理員權限'); }
header('Content-Type: text/html; charset=utf-8');
echo '<pre>';
$db = Database::getInstance();

$sqlFile = __DIR__ . '/../database/migration_050_warranty_date.sql';
if (!file_exists($sqlFile)) { die("SQL file not found\n"); }
$sql = file_get_contents($sqlFile);
$statements = array_filter(array_map('trim', explode(';', $sql)));

foreach ($statements as $stmt) {
    $stmt = trim($stmt);
    if (empty($stmt) || strpos($stmt, '--') === 0) continue;
    try {
        $db->exec($stmt);
        echo "[OK] " . mb_substr($stmt, 0, 80) . "\n";
    } catch (PDOException $e) {
        echo "[ERR] " . mb_substr($stmt, 0, 80) . "\n  " . $e->getMessage() . "\n";
    }
}

echo "\nMigration 050 done\n";
$cols = $db->query("SHOW COLUMNS FROM customers LIKE 'warranty_date'")->fetchAll();
echo "warranty_date: " . (count($cols) > 0 ? 'OK' : 'MISSING') . "\n";
$cols2 = $db->query("SHOW COLUMNS FROM customers LIKE 'payment_terms'")->fetchAll();
echo "payment_terms: " . (count($cols2) > 0 ? 'OK' : 'MISSING') . "\n";
echo '</pre>';
