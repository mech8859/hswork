<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

header('Content-Type: text/plain; charset=utf-8');
$db = Database::getInstance();

// Add req_vendor_name column to purchase_orders
try {
    $db->exec("ALTER TABLE purchase_orders ADD COLUMN req_vendor_name VARCHAR(200) NULL AFTER urgency");
    echo "Added req_vendor_name column.\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate') !== false) {
        echo "Column already exists.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

// Backfill: for existing POs from requisitions, copy vendor_name to req_vendor_name
$db->exec("UPDATE purchase_orders SET req_vendor_name = vendor_name WHERE requisition_id IS NOT NULL AND req_vendor_name IS NULL");
echo "Backfilled existing records.\n";
echo "Done.\n";
