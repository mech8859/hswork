<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('admin')) { die('需要管理員權限'); }
header('Content-Type: text/plain; charset=utf-8');
$db = Database::getInstance();

echo "=== 庫存 vs 產品目錄比對 ===\n\n";

// 1. 庫存有但產品目錄沒有的
$stmt1 = $db->query("
    SELECT i.product_id, i.stock_qty, p.id AS pid, p.name, p.is_active
    FROM inventory i
    LEFT JOIN products p ON i.product_id = p.id
    WHERE p.id IS NULL
");
$orphans = $stmt1->fetchAll(PDO::FETCH_ASSOC);
echo "1. 庫存有但產品目錄找不到的: " . count($orphans) . " 筆\n";
foreach ($orphans as $r) {
    echo "   product_id={$r['product_id']} | 庫存={$r['stock_qty']}\n";
}

// 2. 庫存有但產品已停用的
$stmt2 = $db->query("
    SELECT i.product_id, SUM(i.stock_qty) AS total_stock, p.name, p.is_active
    FROM inventory i
    JOIN products p ON i.product_id = p.id
    WHERE p.is_active = 0 AND i.stock_qty > 0
    GROUP BY i.product_id
    ORDER BY total_stock DESC
");
$inactive = $stmt2->fetchAll(PDO::FETCH_ASSOC);
echo "\n2. 庫存有但產品已停用的: " . count($inactive) . " 筆\n";
foreach ($inactive as $r) {
    echo "   ID:{$r['product_id']} | {$r['name']} | 庫存={$r['total_stock']}\n";
}

// 3. 統計
$stmt3 = $db->query("SELECT COUNT(DISTINCT product_id) FROM inventory WHERE stock_qty > 0");
$invCount = $stmt3->fetchColumn();

$stmt4 = $db->query("SELECT COUNT(*) FROM products WHERE is_active = 1");
$prodCount = $stmt4->fetchColumn();

$stmt5 = $db->query("
    SELECT COUNT(DISTINCT i.product_id)
    FROM inventory i
    JOIN products p ON i.product_id = p.id
    WHERE i.stock_qty > 0 AND p.is_active = 1
");
$matchCount = $stmt5->fetchColumn();

echo "\n3. 統計\n";
echo "   有庫存的產品數: {$invCount}\n";
echo "   啟用中產品數: {$prodCount}\n";
echo "   有庫存且啟用中: {$matchCount}\n";
echo "   有庫存但停用/不存在: " . ($invCount - $matchCount) . "\n";

// 4. 產品目錄有但沒庫存記錄的（僅列前10筆示意）
$stmt6 = $db->query("
    SELECT COUNT(*) FROM products p
    WHERE p.is_active = 1
    AND NOT EXISTS (SELECT 1 FROM inventory i WHERE i.product_id = p.id)
");
$noInv = $stmt6->fetchColumn();
echo "\n4. 啟用中產品但無庫存記錄: {$noInv} 筆\n";

// 5. 有庫存的產品明細（含庫存數與可用數）
echo "\n5. 有庫存產品明細 (庫存 > 0):\n";
echo str_pad('ID', 6) . str_pad('產品名稱', 50) . str_pad('庫存', 10) . str_pad('可用', 10) . str_pad('預留', 10) . str_pad('倉庫', 15) . "\n";
echo str_repeat('-', 101) . "\n";

$stmt7 = $db->query("
    SELECT i.product_id, p.name, p.model,
           i.stock_qty, i.available_qty, i.reserved_qty,
           w.name AS warehouse_name, b.name AS branch_name
    FROM inventory i
    JOIN products p ON i.product_id = p.id
    LEFT JOIN warehouses w ON i.warehouse_id = w.id
    LEFT JOIN branches b ON i.branch_id = b.id
    WHERE i.stock_qty > 0
    ORDER BY p.name, b.name
");
$items = $stmt7->fetchAll(PDO::FETCH_ASSOC);
$totalStock = 0; $totalAvail = 0; $totalReserved = 0;
foreach ($items as $r) {
    $stock = (float)$r['stock_qty'];
    $avail = (float)$r['available_qty'];
    $reserved = (float)$r['reserved_qty'];
    $totalStock += $stock;
    $totalAvail += $avail;
    $totalReserved += $reserved;
    $name = mb_substr($r['name'], 0, 22, 'UTF-8');
    echo str_pad($r['product_id'], 6)
       . str_pad($name, 24)
       . str_pad(number_format($stock), 10, ' ', STR_PAD_LEFT)
       . str_pad(number_format($avail), 10, ' ', STR_PAD_LEFT)
       . str_pad(number_format($reserved), 10, ' ', STR_PAD_LEFT)
       . '  ' . ($r['branch_name'] ?: '') . '/' . ($r['warehouse_name'] ?: '')
       . "\n";
}
echo str_repeat('-', 101) . "\n";
echo str_pad('合計 ' . count($items) . ' 筆', 30)
   . str_pad(number_format($totalStock), 10, ' ', STR_PAD_LEFT)
   . str_pad(number_format($totalAvail), 10, ' ', STR_PAD_LEFT)
   . str_pad(number_format($totalReserved), 10, ' ', STR_PAD_LEFT)
   . "\n";

echo "\n完成\n";
