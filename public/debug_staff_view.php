<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';

$db = Database::getInstance();

// 檢查 vendor_trainings 表是否存在
$tables = $db->query("SHOW TABLES LIKE 'vendor_trainings'")->fetchAll();
echo "vendor_trainings table exists: " . (count($tables) > 0 ? 'YES' : 'NO') . "<br>";

// 測試 getVendorTrainings
if (count($tables) > 0) {
    echo "Table exists, querying...<br>";
    $stmt = $db->prepare('SELECT * FROM vendor_trainings WHERE user_id = ?');
    $stmt->execute(array(4));
    echo "Query OK, rows: " . count($stmt->fetchAll()) . "<br>";
} else {
    echo "<br><strong>Please run migration_006_vendor_training.sql in phpMyAdmin first!</strong><br>";
}
