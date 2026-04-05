<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();

echo "=== 產品目錄有數量但庫存管理無紀錄 ===\n\n";

// 產品目錄有 stock/quantity 但 inventory 表沒有對應紀錄
$sql = "SELECT p.id, p.name, p.model, p.unit, p.stock_qty, p.category_id, pc.name AS category_name
        FROM products p
        LEFT JOIN product_categories pc ON p.category_id = pc.id
        LEFT JOIN inventory i ON i.product_id = p.id
        WHERE p.is_active = 1
        AND p.stock_qty > 0
        AND i.id IS NULL
        ORDER BY pc.name, p.name";

$rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
$count = count($rows);

echo "共 {$count} 筆產品有庫存數量但庫存管理無紀錄:\n\n";

$currentCat = '';
foreach ($rows as $r) {
    $cat = $r['category_name'] ?: '未分類';
    if ($cat !== $currentCat) {
        $currentCat = $cat;
        echo "\n[{$cat}]\n";
    }
    echo "  ID:{$r['id']} | {$r['name']} | 型號:{$r['model']} | 單位:{$r['unit']} | 數量:{$r['stock_qty']}\n";
}

// 反向：庫存管理有但產品目錄數量為0
echo "\n\n=== 反向：庫存管理有紀錄但產品目錄數量為0 ===\n";
$sql2 = "SELECT p.id, p.name, p.model, p.stock_qty, i.quantity AS inv_qty, w.name AS warehouse_name
         FROM inventory i
         JOIN products p ON i.product_id = p.id
         LEFT JOIN warehouses w ON i.warehouse_id = w.id
         WHERE p.is_active = 1 AND i.quantity > 0 AND (p.stock_qty = 0 OR p.stock_qty IS NULL)
         ORDER BY p.name";
$rows2 = $db->query($sql2)->fetchAll(PDO::FETCH_ASSOC);
$count2 = count($rows2);
echo "共 {$count2} 筆\n";
foreach ($rows2 as $r) {
    echo "  ID:{$r['id']} | {$r['name']} | 產品數量:{$r['stock_qty']} | 庫存數量:{$r['inv_qty']} | 倉庫:{$r['warehouse_name']}\n";
}

echo "\n=== 摘要 ===\n";
$totalProducts = $db->query("SELECT COUNT(*) FROM products WHERE is_active = 1")->fetchColumn();
$totalWithStock = $db->query("SELECT COUNT(*) FROM products WHERE is_active = 1 AND stock_qty > 0")->fetchColumn();
$totalInventory = $db->query("SELECT COUNT(DISTINCT product_id) FROM inventory WHERE quantity > 0")->fetchColumn();
echo "有效產品: {$totalProducts} 筆\n";
echo "產品目錄有數量: {$totalWithStock} 筆\n";
echo "庫存管理有紀錄: {$totalInventory} 筆\n";
echo "產品有數量但無庫存紀錄: {$count} 筆\n";
echo "庫存有紀錄但產品數量為0: {$count2} 筆\n";
