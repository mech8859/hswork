<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('admin')) { die('需要管理員權限'); }
header('Content-Type: text/plain; charset=utf-8');
$db = Database::getInstance();

echo "=== 產品分類現況 ===\n\n";

// 1. 主分類（parent_id IS NULL 或 0）
$top = $db->query("
    SELECT pc.id, pc.name, pc.parent_id,
           (SELECT COUNT(*) FROM product_categories WHERE parent_id = pc.id) AS sub_count,
           (SELECT COUNT(*) FROM products WHERE category_id = pc.id) AS direct_products
    FROM product_categories pc
    WHERE pc.parent_id IS NULL OR pc.parent_id = 0
    ORDER BY pc.name
")->fetchAll(PDO::FETCH_ASSOC);

echo "主分類: " . count($top) . " 個\n";
echo str_repeat('-', 80) . "\n";

foreach ($top as $t) {
    echo sprintf("ID:%-4d %-30s 子分類:%d  直屬產品:%d\n", $t['id'], $t['name'], $t['sub_count'], $t['direct_products']);

    // 子分類
    $subs = $db->prepare("
        SELECT pc.id, pc.name,
               (SELECT COUNT(*) FROM product_categories WHERE parent_id = pc.id) AS sub_count,
               (SELECT COUNT(*) FROM products WHERE category_id = pc.id) AS direct_products
        FROM product_categories pc
        WHERE pc.parent_id = ?
        ORDER BY pc.name
    ");
    $subs->execute(array($t['id']));
    foreach ($subs->fetchAll(PDO::FETCH_ASSOC) as $s) {
        echo sprintf("  ├─ ID:%-4d %-28s 細分類:%d  產品:%d\n", $s['id'], $s['name'], $s['sub_count'], $s['direct_products']);

        // 細分類（第三層）
        if ($s['sub_count'] > 0) {
            $detail = $db->prepare("
                SELECT pc.id, pc.name,
                       (SELECT COUNT(*) FROM products WHERE category_id = pc.id) AS direct_products
                FROM product_categories pc
                WHERE pc.parent_id = ?
                ORDER BY pc.name
            ");
            $detail->execute(array($s['id']));
            foreach ($detail->fetchAll(PDO::FETCH_ASSOC) as $d) {
                echo sprintf("  │  └─ ID:%-4d %-25s 產品:%d\n", $d['id'], $d['name'], $d['direct_products']);
            }
        }
    }
    echo "\n";
}

// 統計
$totalCat = $db->query("SELECT COUNT(*) FROM product_categories")->fetchColumn();
$totalProd = $db->query("SELECT COUNT(*) FROM products")->fetchColumn();
$uncat = $db->query("SELECT COUNT(*) FROM products WHERE category_id IS NULL OR category_id = 0")->fetchColumn();
$orphan = $db->query("SELECT COUNT(*) FROM products p LEFT JOIN product_categories pc ON p.category_id = pc.id WHERE pc.id IS NULL AND p.category_id > 0")->fetchColumn();

echo "\n=== 統計 ===\n";
echo "總分類數: {$totalCat}\n";
echo "總產品數: {$totalProd}\n";
echo "未分類產品: {$uncat}\n";
echo "分類已刪除的產品（孤兒）: {$orphan}\n";
