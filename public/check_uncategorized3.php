<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$db = Database::getInstance();
header('Content-Type: text/plain; charset=utf-8');

// 找出所有不存在的 category_id 及其產品數量和產品名稱樣本
$stmt = $db->query("
    SELECT p.category_id, COUNT(*) as cnt,
           GROUP_CONCAT(DISTINCT SUBSTRING(p.name, 1, 30) ORDER BY p.name SEPARATOR ' | ') as samples
    FROM products p
    LEFT JOIN product_categories pc ON p.category_id = pc.id
    WHERE p.category_id IS NOT NULL AND p.category_id > 0 AND pc.id IS NULL AND p.is_active = 1
    GROUP BY p.category_id
    ORDER BY cnt DESC
");

echo "=== 不存在的 category_id 統計 ===\n\n";
$total = 0;
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $total += $row['cnt'];
    $samples = mb_substr($row['samples'], 0, 200);
    echo "category_id={$row['category_id']} ({$row['cnt']}筆)\n";
    echo "  樣本: {$samples}\n\n";
}
echo "總計: {$total} 筆\n";
