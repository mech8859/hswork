<?php
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../includes/bootstrap.php';
$db = Database::getInstance();

// 查系統裡所有 TP-LINK / VIGI / Omada 相關產品
echo "=== 系統內 TP-LINK 相關產品 ===\n";
echo sprintf("%-6s %-25s %-40s %-8s %-8s %-6s %s\n", 'ID', '型號', '品名', '成本', '售價', '分類ID', '分類名');
echo str_repeat('-', 140) . "\n";

$products = $db->query("SELECT p.id, p.model, SUBSTRING(p.name,1,38) as name, p.cost, p.price, p.category_id, c.name as cat_name
    FROM products p LEFT JOIN product_categories c ON p.category_id = c.id
    WHERE p.category_id IN (152,153,154,155,156,157,158,159,160,161,109,110,111,371,372,373)
    OR p.model LIKE 'EAP%' OR p.model LIKE 'ER%' OR p.model LIKE 'SG%' OR p.model LIKE 'SX%'
    OR p.model LIKE 'TL-%' OR p.model LIKE 'ES%' OR p.model LIKE 'DS%'
    OR p.model LIKE 'MC%' OR p.model LIKE 'SM%'
    OR p.model LIKE 'VIGI%' OR p.model LIKE 'C%' OR p.model LIKE 'InSight%' OR p.model LIKE 'Insight%'
    OR p.model LIKE 'NVR%'
    ORDER BY p.model")->fetchAll(PDO::FETCH_ASSOC);

foreach ($products as $p) {
    echo sprintf("%-6s %-25s %-40s %-8s %-8s %-6s %s\n",
        $p['id'], $p['model'], $p['name'], $p['cost'], $p['price'], $p['category_id'], $p['cat_name']);
}
echo "\n共 " . count($products) . " 筆\n";

// TP-LINK 分類結構
echo "\n=== TP-LINK 分類結構 ===\n";
$tpCats = $db->query("SELECT c.id, c.name, c.parent_id, COUNT(p.id) as cnt
    FROM product_categories c LEFT JOIN products p ON p.category_id = c.id
    WHERE c.id IN (152,153,154,155,156,157,158,159,160,161,371) OR c.parent_id IN (152,109)
    GROUP BY c.id ORDER BY c.parent_id, c.name")->fetchAll(PDO::FETCH_ASSOC);
foreach ($tpCats as $c) {
    echo sprintf("  id:%s parent:%s %s (%s筆)\n", $c['id'], $c['parent_id'], $c['name'], $c['cnt']);
}

// VIGI 分類
echo "\n=== VIGI 分類結構 ===\n";
$vigiCats = $db->query("SELECT c.id, c.name, c.parent_id, COUNT(p.id) as cnt
    FROM product_categories c LEFT JOIN products p ON p.category_id = c.id
    WHERE c.id IN (109,110,111,372,373) OR c.parent_id = 109
    GROUP BY c.id ORDER BY c.parent_id, c.name")->fetchAll(PDO::FETCH_ASSOC);
foreach ($vigiCats as $c) {
    echo sprintf("  id:%s parent:%s %s (%s筆)\n", $c['id'], $c['parent_id'], $c['name'], $c['cnt']);
}
