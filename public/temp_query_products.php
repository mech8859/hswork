<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain; charset=utf-8');

try {
    $dsn = 'mysql:host=localhost;port=3306;dbname=vhost158992;charset=utf8mb4';
    $db = new PDO($dsn, 'vhost158992', 'Kss9227456', array(
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ));

    $sql = "SELECT p.id, p.name, p.model, p.category_id, c.name as category_name FROM products p LEFT JOIN product_categories c ON p.category_id = c.id WHERE p.name LIKE '%聯順%' OR p.model LIKE '%LN%' OR c.name LIKE '%聯順%' ORDER BY p.name LIMIT 200";
    $stmt = $db->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "=== Products matching LianShun or model LN ===\n";
    echo "Total found: " . count($rows) . "\n\n";

    echo str_pad('ID', 8) . str_pad('Name', 50) . str_pad('Model', 30) . str_pad('CatID', 8) . "Category\n";
    echo str_repeat('-', 140) . "\n";

    foreach ($rows as $row) {
        $name = $row['name'] ? $row['name'] : '-';
        if (mb_strlen($name) > 48) {
            $name = mb_substr($name, 0, 46) . '..';
        }
        echo str_pad($row['id'], 8);
        echo str_pad($name, 50);
        echo str_pad($row['model'] ? $row['model'] : '-', 30);
        echo str_pad($row['category_id'] ? $row['category_id'] : '-', 8);
        echo ($row['category_name'] ? $row['category_name'] : '-') . "\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
