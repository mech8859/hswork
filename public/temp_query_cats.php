<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain; charset=utf-8');
echo "OK - PHP works\n";

try {
    $dsn = 'mysql:host=localhost;port=3306;dbname=vhost158992;charset=utf8mb4';
    $db = new PDO($dsn, 'vhost158992', 'Kss9227456', array(
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ));
    echo "DB connected\n";

    $sql = "SELECT c.id, c.name, c.parent_id, (SELECT COUNT(*) FROM products WHERE category_id = c.id) as product_count FROM product_categories c ORDER BY c.parent_id, c.name";
    $stmt = $db->query($sql);
    $cats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $children = array();
    $roots = array();
    foreach ($cats as $cat) {
        $pid = $cat['parent_id'];
        if (empty($pid) || $pid == 0) {
            $roots[] = $cat;
        } else {
            if (!isset($children[$pid])) {
                $children[$pid] = array();
            }
            $children[$pid][] = $cat;
        }
    }

    function printTree($cat, $children, $indent = 0) {
        $prefix = str_repeat('  ', $indent);
        $marker = $indent > 0 ? '|- ' : '';
        echo $prefix . $marker . '[' . $cat['id'] . '] ' . $cat['name'] . ' (' . $cat['product_count'] . " products)\n";
        if (isset($children[$cat['id']])) {
            foreach ($children[$cat['id']] as $child) {
                printTree($child, $children, $indent + 1);
            }
        }
    }

    echo "=== Product Category Tree ===\n";
    echo "Total categories: " . count($cats) . "\n\n";

    foreach ($roots as $root) {
        printTree($root, $children);
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
