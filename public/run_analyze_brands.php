<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('admin')) { die('需要管理員權限'); }
header('Content-Type: text/html; charset=utf-8');
echo '<pre style="font-family:monospace;font-size:13px;line-height:1.6">';
$db = Database::getInstance();

// 查看各主分類的產品品牌/供應商分布
$tops = $db->query("SELECT id, name FROM product_categories WHERE (parent_id IS NULL OR parent_id = 0) AND name LIKE '0%' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

foreach ($tops as $top) {
    // 取得此分類及子分類的所有產品
    $ids = array($top['id']);
    $ch = $db->prepare("SELECT id FROM product_categories WHERE parent_id = ?");
    $ch->execute(array($top['id']));
    foreach ($ch->fetchAll(PDO::FETCH_COLUMN) as $cid) $ids[] = $cid;

    $ph = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("SELECT name, model, brand, supplier, category_id FROM products WHERE category_id IN ({$ph}) ORDER BY name");
    $stmt->execute($ids);
    $prods = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($prods)) continue;

    echo "\n=== {$top['name']} ({$top['id']}) — " . count($prods) . " 個產品 ===\n";

    // 按 supplier 分組
    $bySupplier = array();
    foreach ($prods as $p) {
        $sup = trim($p['supplier'] ?: ($p['brand'] ?: ''));
        if (!$sup) $sup = '(無供應商)';
        $bySupplier[$sup][] = $p['name'];
    }
    arsort($bySupplier);

    foreach ($bySupplier as $sup => $names) {
        echo "  [{$sup}] " . count($names) . " 個: ";
        echo implode(', ', array_slice(array_map(function($n) { return mb_substr($n, 0, 20); }, $names), 0, 3));
        if (count($names) > 3) echo '...';
        echo "\n";
    }
}
echo '</pre>';
