<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();
header('Content-Type: text/plain; charset=utf-8');

$cols = $db->query("SHOW COLUMNS FROM products")->fetchAll(PDO::FETCH_COLUMN);
echo "products 欄位: " . implode(', ', $cols) . "\n";
