<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('admin')) { die('需要管理員權限'); }
header('Content-Type: text/html; charset=utf-8');
echo '<pre style="font-family:monospace;font-size:13px;line-height:1.6">';
$db = Database::getInstance();

echo "=== 清除舊分類 ===\n\n";

// 新分類：以數字開頭 + 「未分類」
// 舊分類：不以數字開頭且不是「未分類」的頂層分類及其所有子分類

$oldTopCats = $db->query("
    SELECT id, name FROM product_categories
    WHERE (parent_id IS NULL OR parent_id = 0)
    AND name NOT REGEXP '^[0-9]'
    AND name != '未分類'
    ORDER BY name
")->fetchAll(PDO::FETCH_ASSOC);

echo "找到 " . count($oldTopCats) . " 個舊頂層分類\n\n";

// 遞迴取所有子孫
function getAllDescendants($db, $id) {
    $all = array($id);
    $ch = $db->prepare("SELECT id FROM product_categories WHERE parent_id = ?");
    $ch->execute(array($id));
    foreach ($ch->fetchAll(PDO::FETCH_COLUMN) as $cid) {
        $all = array_merge($all, getAllDescendants($db, $cid));
    }
    return $all;
}

$totalDeleted = 0;
$totalProductsMoved = 0;

// 找一個 fallback 分類（未分類）
$fallback = $db->query("SELECT id FROM product_categories WHERE name = '未分類' AND (parent_id IS NULL OR parent_id = 0)")->fetchColumn();

foreach ($oldTopCats as $oc) {
    $allIds = getAllDescendants($db, $oc['id']);
    $ph = implode(',', array_fill(0, count($allIds), '?'));

    // 檢查殘留產品
    $pCnt = $db->prepare("SELECT COUNT(*) FROM products WHERE category_id IN ({$ph})");
    $pCnt->execute($allIds);
    $productCount = (int)$pCnt->fetchColumn();

    if ($productCount > 0 && $fallback) {
        // 移到未分類
        $db->prepare("UPDATE products SET category_id = ? WHERE category_id IN ({$ph})")
           ->execute(array_merge(array($fallback), $allIds));
        echo "[移動] {$oc['name']} 殘留 {$productCount} 個產品 → 未分類\n";
        $totalProductsMoved += $productCount;
    }

    // 從最深的子分類開始刪（避免外鍵問題）
    $reversed = array_reverse($allIds);
    foreach ($reversed as $delId) {
        $db->prepare("DELETE FROM product_categories WHERE id = ?")->execute(array($delId));
    }
    echo "[刪除] {$oc['name']} (含 " . (count($allIds) - 1) . " 個子分類)\n";
    $totalDeleted += count($allIds);
}

echo "\n--- 結果 ---\n";
echo "刪除分類: {$totalDeleted}\n";
echo "移動產品: {$totalProductsMoved}\n";

$remaining = $db->query("SELECT COUNT(*) FROM product_categories")->fetchColumn();
$topCats = $db->query("SELECT name FROM product_categories WHERE (parent_id IS NULL OR parent_id = 0) ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
echo "剩餘分類: {$remaining}\n";
echo "頂層分類: " . implode('、', $topCats) . "\n";

echo '</pre>';
