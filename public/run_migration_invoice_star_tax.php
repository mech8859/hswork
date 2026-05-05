<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();

foreach (array('purchase_invoices', 'sales_invoices') as $tbl) {
    try {
        $db->exec("ALTER TABLE {$tbl} ADD COLUMN is_starred_tax TINYINT(1) NOT NULL DEFAULT 0 AFTER is_starred");
        echo "Added is_starred_tax to {$tbl}.\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false) {
            echo "Column is_starred_tax already exists in {$tbl}.\n";
        } else {
            echo "Error on {$tbl}: " . $e->getMessage() . "\n";
        }
    }
}

echo "Done.\n";
