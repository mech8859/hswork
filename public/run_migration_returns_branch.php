<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();

try {
    $db->exec("ALTER TABLE returns ADD COLUMN branch_id INT DEFAULT NULL AFTER return_type");
    echo "Added branch_id column to returns table.\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate') !== false) {
        echo "Column branch_id already exists.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

echo "Done.\n";
