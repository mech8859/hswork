<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();
header('Content-Type: text/plain; charset=utf-8');

// 分類最大深度
echo "=== 分類層級深度 ===\n";
function getDepth($db, $catId, $depth = 0) {
    $stmt = $db->prepare("SELECT parent_id FROM product_categories WHERE id = ?");
    $stmt->execute(array($catId));
    $r = $stmt->fetch();
    if ($r && $r['parent_id']) return getDepth($db, $r['parent_id'], $depth + 1);
    return $depth;
}

$allCats = $db->query("SELECT id, name, parent_id FROM product_categories ORDER BY id")->fetchAll();
$depthMap = array();
foreach ($allCats as $c) {
    $d = getDepth($db, $c['id']);
    if (!isset($depthMap[$d])) $depthMap[$d] = 0;
    $depthMap[$d]++;
}
foreach ($depthMap as $d => $cnt) {
    echo "  深度{$d}: {$cnt} 個分類\n";
}

// 產品指向哪一層
echo "\n=== 產品 category_id 指向深度 ===\n";
$prods = $db->query("SELECT category_id FROM products WHERE is_active = 1 AND category_id IS NOT NULL AND category_id > 0")->fetchAll(PDO::FETCH_COLUMN);
$prodDepth = array();
foreach ($prods as $cid) {
    $d = getDepth($db, $cid);
    if (!isset($prodDepth[$d])) $prodDepth[$d] = 0;
    $prodDepth[$d]++;
}
foreach ($prodDepth as $d => $cnt) {
    echo "  深度{$d}(最底層=0): {$cnt} 個產品\n";
}

// 顯示三層範例
echo "\n=== 三層分類範例 ===\n";
$tops = $db->query("SELECT id, name FROM product_categories WHERE (parent_id IS NULL OR parent_id = 0) ORDER BY name LIMIT 3")->fetchAll();
foreach ($tops as $t) {
    echo "{$t['name']} (ID={$t['id']})\n";
    $subs = $db->prepare("SELECT id, name FROM product_categories WHERE parent_id = ? ORDER BY name LIMIT 3");
    $subs->execute(array($t['id']));
    foreach ($subs->fetchAll() as $s) {
        $prodCnt = $db->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
        $prodCnt->execute(array($s['id']));
        $pc = $prodCnt->fetchColumn();
        
        $subsubs = $db->prepare("SELECT id, name FROM product_categories WHERE parent_id = ? ORDER BY name LIMIT 3");
        $subsubs->execute(array($s['id']));
        $ss = $subsubs->fetchAll();
        
        echo "  └ {$s['name']} (ID={$s['id']}, 直屬產品:{$pc}, 子分類:" . count($ss) . ")\n";
        foreach ($ss as $ss2) {
            $pc2 = $db->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
            $pc2->execute(array($ss2['id']));
            echo "      └ {$ss2['name']} (ID={$ss2['id']}, 產品:{$pc2->fetchColumn()})\n";
        }
    }
}

echo "\n=== 總計 ===\n";
echo "總分類數: " . count($allCats) . "\n";
echo "總產品數: " . $db->query("SELECT COUNT(*) FROM products WHERE is_active = 1")->fetchColumn() . "\n";
echo "有分類的產品: " . count($prods) . "\n";
$nocat = $db->query("SELECT COUNT(*) FROM products WHERE is_active = 1 AND (category_id IS NULL OR category_id = 0)")->fetchColumn();
echo "無分類的產品: {$nocat}\n";
