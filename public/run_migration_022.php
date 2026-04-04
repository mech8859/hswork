<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();

echo "<h3>Migration 022 – Import Source Column</h3>";

// Add import_source column
try {
    $db->exec("ALTER TABLE customers ADD COLUMN import_source VARCHAR(50) DEFAULT NULL");
    echo "OK: import_source column added<br>";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "SKIP: import_source exists<br>";
    } else {
        echo "ERR: " . $e->getMessage() . "<br>";
    }
}

echo "<br>DONE<br>";
echo "<a href='/customers.php'>客戶管理</a>";
