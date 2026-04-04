<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();
header('Content-Type: text/plain; charset=utf-8');

echo "=== 頂層分類 ===\n";
$stmt = $db->query("SELECT c.id, c.name, c.parent_id, COUNT(p.id) as product_count,
    (SELECT COUNT(*) FROM product_categories cc WHERE cc.parent_id = c.id) as child_count
    FROM product_categories c
    LEFT JOIN products p ON p.category_id = c.id
    WHERE c.parent_id IS NULL OR c.parent_id = 0
    GROUP BY c.id ORDER BY c.sort, c.name");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo $r['id'] . " | " . $r['name'] . " | 子分類:" . $r['child_count'] . " | 產品:" . $r['product_count'] . "\n";
}

echo "\n=== 全部分類樹 ===\n";
$allCats = $db->query("SELECT c.id, c.name, c.parent_id, c.sort, COUNT(p.id) as pcnt
    FROM product_categories c LEFT JOIN products p ON p.category_id = c.id
    GROUP BY c.id ORDER BY c.parent_id, c.sort, c.name")->fetchAll(PDO::FETCH_ASSOC);
$byParent = array();
foreach ($allCats as $c) {
    $pid = $c['parent_id'] ? (int)$c['parent_id'] : 0;
    $byParent[$pid][] = $c;
}
function printTree($byParent, $parentId, $depth) {
    if (!isset($byParent[$parentId])) return;
    foreach ($byParent[$parentId] as $c) {
        echo str_repeat('  ', $depth) . $c['id'] . ' | ' . $c['name'] . ' (' . $c['pcnt'] . ")\n";
        printTree($byParent, (int)$c['id'], $depth + 1);
    }
}
printTree($byParent, 0, 0);

echo "\n=== 統計 ===\n";
echo "總分類: " . count($allCats) . "\n";
$stmt = $db->query("SELECT COUNT(*) FROM products"); echo "總產品: " . $stmt->fetchColumn() . "\n";

echo "\n=== 工程項次 ===\n";
try {
    $stmt = $db->query("SELECT id, name, category, unit, default_price, default_cost, is_active FROM engineering_items ORDER BY category, sort_order");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        echo $r['category'] . " | " . $r['name'] . " | " . $r['unit'] . " | 價:" . (int)$r['default_price'] . " | 成本:" . (int)$r['default_cost'] . "\n";
    }
} catch (Exception $e) {
    echo "工程項次表不存在\n";
}
