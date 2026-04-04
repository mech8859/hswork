<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$db = Database::getInstance();

// 沒有分類的產品
$stmt = $db->query("SELECT id, name, model, category_id FROM products WHERE (category_id IS NULL OR category_id = 0) AND is_active = 1 ORDER BY name LIMIT 100");
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "未分類產品: " . count($items) . " 筆\n\n";
foreach ($items as $i => $item) {
    echo ($i+1) . ". [{$item['id']}] {$item['name']}";
    if ($item['model']) echo " ({$item['model']})";
    echo "\n";
}

// 總數
$total = $db->query("SELECT COUNT(*) FROM products WHERE (category_id IS NULL OR category_id = 0) AND is_active = 1")->fetchColumn();
echo "\n總計未分類: {$total} 筆\n";

// 現有分類
echo "\n=== 現有分類 ===\n";
$cats = $db->query("SELECT c.id, c.name, c.parent_id, p.name as parent_name, (SELECT COUNT(*) FROM products WHERE category_id = c.id) as cnt FROM product_categories c LEFT JOIN product_categories p ON c.parent_id = p.id ORDER BY COALESCE(p.name, c.name), c.name")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cats as $c) {
    $prefix = $c['parent_name'] ? "  {$c['parent_name']} > " : "";
    echo "{$prefix}{$c['name']} (id={$c['id']}, {$c['cnt']}筆)\n";
}
