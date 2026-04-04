<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('admin')) { die('需要管理員權限'); }
header('Content-Type: text/plain; charset=utf-8');
$db = Database::getInstance();

// 1. 產品表自帶 stock 欄位 vs inventory 表庫存
echo "=== 產品庫存比對 ===\n\n";

// 檢查 inventory 表是否存在
$tables = $db->query("SHOW TABLES LIKE 'inventory'")->fetchAll();
if (empty($tables)) {
    echo "inventory 表不存在\n";
} else {
    // 有 inventory 表的產品庫存
    $stmt = $db->query("
        SELECT p.id, p.name, p.stock AS product_stock,
               COALESCE(SUM(i.stock_qty), 0) AS inv_stock
        FROM products p
        LEFT JOIN inventory i ON i.product_id = p.id
        GROUP BY p.id
        HAVING product_stock != inv_stock OR (product_stock != 0 AND inv_stock = 0) OR (product_stock = 0 AND inv_stock != 0)
        ORDER BY p.id
        LIMIT 50
    ");
    $mismatches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "products.stock 與 inventory 不一致的產品: " . count($mismatches) . " 筆\n";
    foreach ($mismatches as $r) {
        echo "  ID:{$r['id']} | {$r['name']} | products.stock={$r['product_stock']} | inventory合計={$r['inv_stock']}\n";
    }
}

echo "\n";

// 2. 有庫存的產品數量
$stmt2 = $db->query("SELECT COUNT(*) FROM products WHERE stock > 0");
echo "products.stock > 0 的產品: " . $stmt2->fetchColumn() . " 筆\n";

$stmt3 = $db->query("SELECT COUNT(DISTINCT product_id) FROM inventory WHERE stock_qty > 0");
echo "inventory.stock_qty > 0 的產品: " . $stmt3->fetchColumn() . " 筆\n";

// 3. 總庫存量
$stmt4 = $db->query("SELECT SUM(stock) FROM products");
echo "products.stock 總計: " . ($stmt4->fetchColumn() ?: 0) . "\n";

$stmt5 = $db->query("SELECT SUM(stock_qty) FROM inventory");
echo "inventory.stock_qty 總計: " . ($stmt5->fetchColumn() ?: 0) . "\n";

echo "\n";

// 4. 最近匯入的產品庫存狀況
echo "=== 最近匯入產品(ID > 2000)庫存狀況 ===\n";
$stmt6 = $db->query("
    SELECT p.id, p.name, p.stock, p.is_active,
           COALESCE(SUM(i.stock_qty), 0) AS inv_stock
    FROM products p
    LEFT JOIN inventory i ON i.product_id = p.id
    WHERE p.id > 2000
    GROUP BY p.id
    HAVING p.stock != 0 OR inv_stock != 0
    ORDER BY p.id
    LIMIT 30
");
$recent = $stmt6->fetchAll(PDO::FETCH_ASSOC);
echo "有庫存的: " . count($recent) . " 筆\n";
foreach ($recent as $r) {
    echo "  ID:{$r['id']} | {$r['name']} | stock={$r['stock']} | inv={$r['inv_stock']} | active={$r['is_active']}\n";
}

// 5. inventory 表結構
echo "\n=== inventory 表結構 ===\n";
$cols = $db->query("DESCRIBE inventory")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $col) {
    echo "  {$col['Field']} ({$col['Type']})\n";
}

// 6. inventory 筆數
$stmt7 = $db->query("SELECT COUNT(*) FROM inventory");
echo "\ninventory 總筆數: " . $stmt7->fetchColumn() . "\n";

echo "\n完成\n";
