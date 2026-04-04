<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$db = Database::getInstance();
header('Content-Type: text/html; charset=utf-8');
echo '<pre>';

// 1. 完全沒分類的產品
$stmt = $db->query("SELECT COUNT(*) FROM products WHERE (category_id IS NULL OR category_id = 0) AND is_active = 1");
echo "完全沒 category_id 的產品: " . $stmt->fetchColumn() . " 筆\n\n";

// 2. 分類是頂層（沒有父分類）的產品 — 這些只有主分類沒有子分類
echo "=== 產品掛在頂層分類（沒有子分類歸屬）===\n";
$stmt = $db->query("
    SELECT pc.id as cat_id, pc.name as cat_name, COUNT(p.id) as cnt,
           GROUP_CONCAT(p.name ORDER BY p.name SEPARATOR ', ') as product_names
    FROM products p
    JOIN product_categories pc ON p.category_id = pc.id
    WHERE (pc.parent_id IS NULL OR pc.parent_id = 0)
      AND p.is_active = 1
    GROUP BY pc.id
    ORDER BY pc.name
");
$totalOrphan = 0;
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
    $totalOrphan += $c['cnt'];
    $names = mb_substr($c['product_names'], 0, 150);
    echo "\n[分類 #{$c['cat_id']}] {$c['cat_name']} ({$c['cnt']}筆)\n";
    echo "  產品: {$names}...\n";
}
echo "\n總計: {$totalOrphan} 筆產品只有主分類\n";

// 3. 完全孤兒 — category_id 指向不存在的分類
echo "\n=== category_id 指向不存在分類的產品 ===\n";
$stmt = $db->query("
    SELECT p.id, p.name, p.category_id
    FROM products p
    LEFT JOIN product_categories pc ON p.category_id = pc.id
    WHERE p.category_id IS NOT NULL AND p.category_id > 0 AND pc.id IS NULL AND p.is_active = 1
    ORDER BY p.name
");
$orphans = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo count($orphans) . " 筆\n";
foreach ($orphans as $o) {
    echo "  [{$o['id']}] {$o['name']} (category_id={$o['category_id']} 不存在)\n";
}

echo '</pre>';
