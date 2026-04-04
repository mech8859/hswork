<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();
header('Content-Type: text/plain; charset=utf-8');

// 測試分類5（對講機系統）的遞迴
echo "=== 分類5 對講機系統 遞迴測試 ===\n";
$catIds = array(5);
$queue = array(5);
$depth = 0;
while (!empty($queue)) {
    $depth++;
    $nextQueue = array();
    foreach ($queue as $pid) {
        $s = $db->prepare('SELECT id, name FROM product_categories WHERE parent_id = ?');
        $s->execute(array($pid));
        $subs = $s->fetchAll();
        foreach ($subs as $sub) {
            echo "  深度{$depth}: ID={$sub['id']} {$sub['name']} (parent={$pid})\n";
            $catIds[] = (int)$sub['id'];
            $nextQueue[] = (int)$sub['id'];
        }
    }
    $queue = $nextQueue;
    if ($depth > 5) break;
}
echo "所有分類IDs: " . implode(',', $catIds) . "\n";
echo "共 " . count($catIds) . " 個分類\n";

// 查產品
$ph = implode(',', array_fill(0, count($catIds), '?'));
$stmt = $db->prepare("SELECT COUNT(*) FROM products WHERE category_id IN ($ph) AND is_active = 1");
$stmt->execute($catIds);
echo "產品數: " . $stmt->fetchColumn() . "\n";

// 顯示前5筆
$stmt2 = $db->prepare("SELECT p.name, p.model_number, p.price FROM products p WHERE p.category_id IN ($ph) AND p.is_active = 1 LIMIT 5");
$stmt2->execute($catIds);
foreach ($stmt2->fetchAll() as $p) {
    echo "  {$p['name']} | {$p['model_number']} | \${$p['price']}\n";
}

// 測試直接呼叫 ajax API
echo "\n=== 模擬 ajax_products?category_id=5 ===\n";
$catId = 5;
$catIds2 = array($catId);
$queue2 = array($catId);
while (!empty($queue2)) {
    $parentId = array_shift($queue2);
    $subStmt = $db->prepare('SELECT id FROM product_categories WHERE parent_id = ?');
    $subStmt->execute(array($parentId));
    foreach ($subStmt->fetchAll() as $sub) {
        $catIds2[] = (int)$sub['id'];
        $queue2[] = (int)$sub['id'];
    }
}
$ph2 = implode(',', array_fill(0, count($catIds2), '?'));
$where = "p.is_active = 1 AND p.category_id IN ($ph2)";
$stmt3 = $db->prepare("SELECT p.id, p.name, p.model_number, p.price, pc.name AS category_name FROM products p LEFT JOIN product_categories pc ON p.category_id = pc.id WHERE $where ORDER BY p.name LIMIT 50");
$stmt3->execute($catIds2);
$results = $stmt3->fetchAll();
echo "API回傳: " . count($results) . " 筆\n";
foreach (array_slice($results, 0, 5) as $r) {
    echo "  {$r['name']} | {$r['model_number']} | \${$r['price']} | [{$r['category_name']}]\n";
}
