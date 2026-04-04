<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../includes/Database.php';

$db = Database::getInstance();

// Check if inventory table exists
try {
    $db->query("SELECT 1 FROM inventory LIMIT 1");
    echo "inventory table exists\n";
} catch (\Throwable $e) {
    echo "inventory table ERROR: " . $e->getMessage() . "\n";
}

// Test simple query without inventory
try {
    $stmt = $db->prepare("SELECT id, name, model, CAST(COALESCE(NULLIF(cost,0), price, 0) AS SIGNED) as price, unit FROM products WHERE is_active = 1 AND name LIKE ? LIMIT 3");
    $stmt->execute(array('%監視%'));
    $r = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Simple query OK: " . count($r) . " results\n";
} catch (\Throwable $e) {
    echo "Simple query ERROR: " . $e->getMessage() . "\n";
}

// Test with inventory join
try {
    $stmt = $db->prepare("SELECT p.id, p.name, CAST(COALESCE(inv.total_stock, 0) AS SIGNED) as stock FROM products p LEFT JOIN (SELECT product_id, SUM(stock_qty) as total_stock FROM inventory GROUP BY product_id) inv ON p.id = inv.product_id WHERE p.is_active = 1 AND p.name LIKE ? LIMIT 3");
    $stmt->execute(array('%監視%'));
    $r = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Join query OK: " . count($r) . " results\n";
} catch (\Throwable $e) {
    echo "Join query ERROR: " . $e->getMessage() . "\n";
}
