<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('admin')) { die('需要管理員權限'); }
header('Content-Type: text/plain; charset=utf-8');
$db = Database::getInstance();

// 找大華品牌的產品
$stmt = $db->query("SELECT id, name, brand, category_id FROM products WHERE brand LIKE '%大華%' OR brand LIKE '%Dahua%' OR brand LIKE '%dahua%'");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "=== 大華/Dahua 品牌產品: " . count($rows) . " 筆 ===\n";
foreach ($rows as $r) {
    echo "ID:{$r['id']} | {$r['name']} | 品牌:{$r['brand']} | 分類ID:{$r['category_id']}\n";
}

echo "\n";

// 找聯順品牌的產品
$stmt2 = $db->query("SELECT id, name, brand, category_id FROM products WHERE brand LIKE '%聯順%'");
$rows2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
echo "=== 聯順品牌產品: " . count($rows2) . " 筆 ===\n";
foreach ($rows2 as $r) {
    echo "ID:{$r['id']} | {$r['name']} | 品牌:{$r['brand']} | 分類ID:{$r['category_id']}\n";
}

// 檢查所有不重複的品牌
$stmt3 = $db->query("SELECT DISTINCT brand FROM products WHERE brand IS NOT NULL AND brand != '' ORDER BY brand");
$brands = $stmt3->fetchAll(PDO::FETCH_COLUMN);
echo "\n=== 所有品牌列表 (" . count($brands) . " 個) ===\n";
foreach ($brands as $b) {
    echo "- {$b}\n";
}
