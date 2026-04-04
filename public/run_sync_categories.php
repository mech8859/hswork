<?php
/**
 * 同步禾順系統分類到 hswork
 * 先清除所有分類，再從禾順 JSON 重建完整三級結構
 * 產品的 category_id 會對應更新
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }

header('Content-Type: text/plain; charset=utf-8');

$jsonFile = __DIR__ . '/../data/hershun_categories.json';
if (!file_exists($jsonFile)) {
    die('找不到 hershun_categories.json');
}

$data = json_decode(file_get_contents($jsonFile), true);
if (!isset($data['data'])) {
    die('JSON 格式錯誤');
}

$categories = $data['data'];
$db = Database::getInstance();

echo "=== 同步禾順分類結構 ===\n\n";

// 1. 先建立 舊ID → 名稱 的對照表（用於更新產品）
$oldCats = $db->query("SELECT id, name, parent_id FROM product_categories ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$oldCatMap = array();
foreach ($oldCats as $c) {
    $oldCatMap[$c['id']] = $c;
}
echo "現有分類: " . count($oldCats) . " 筆\n";

// 2. 建立禾順 ID → hswork 舊 ID 的映射（透過名稱比對）
// 先收集所有禾順分類（扁平化）
$hershunFlat = array();
function flattenCats($cats, &$result) {
    foreach ($cats as $c) {
        $result[$c['id']] = array('name' => $c['name'], 'parentId' => $c['parentId']);
        if (!empty($c['children'])) {
            flattenCats($c['children'], $result);
        }
    }
}
flattenCats($categories, $hershunFlat);
echo "禾順分類: " . count($hershunFlat) . " 筆\n\n";

// 3. 清除所有分類，重建
echo "清除現有分類...\n";
$db->exec("SET FOREIGN_KEY_CHECKS = 0");

// 先記錄產品對應的分類名稱（用於之後重新對應）
$products = $db->query("SELECT id, category_id FROM products WHERE category_id IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
$productCatNames = array();
foreach ($products as $p) {
    if (isset($oldCatMap[$p['category_id']])) {
        $productCatNames[$p['id']] = $oldCatMap[$p['category_id']]['name'];
    }
}
echo "需要重新對應的產品: " . count($productCatNames) . " 筆\n";

$db->exec("DELETE FROM product_categories");
$db->exec("ALTER TABLE product_categories AUTO_INCREMENT = 1");
echo "[OK] 分類已清除\n\n";

// 4. 重建分類（遞迴插入）
$newCatMap = array(); // 禾順ID → 新hswork ID
$insertCount = 0;

function insertCategory($db, $cat, $parentHsworkId, &$newCatMap, &$insertCount) {
    $stmt = $db->prepare("INSERT INTO product_categories (name, parent_id, sort, source_id) VALUES (?, ?, ?, ?)");
    $stmt->execute(array($cat['name'], $parentHsworkId, $cat['sort'] ?? 0, $cat['id']));
    $newId = $db->lastInsertId();
    $newCatMap[$cat['id']] = $newId;
    $insertCount++;

    echo "  [+] " . $cat['name'] . " (禾順#{$cat['id']} → hswork#{$newId})" .
         ($parentHsworkId ? " parent=#{$parentHsworkId}" : "") . "\n";

    if (!empty($cat['children'])) {
        foreach ($cat['children'] as $child) {
            insertCategory($db, $child, $newId, $newCatMap, $insertCount);
        }
    }
}

echo "建立新分類結構...\n";
foreach ($categories as $cat) {
    insertCategory($db, $cat, null, $newCatMap, $insertCount);
}
echo "\n[OK] 新增 {$insertCount} 筆分類\n\n";

// 5. 重新對應產品的 category_id
echo "重新對應產品分類...\n";
// 建立 新分類名稱 → 新ID 的映射
$newNameMap = array();
$newCats = $db->query("SELECT id, name FROM product_categories")->fetchAll(PDO::FETCH_ASSOC);
foreach ($newCats as $c) {
    $newNameMap[$c['name']] = $c['id'];
}

$matched = 0;
$unmatched = 0;
$updateStmt = $db->prepare("UPDATE products SET category_id = ? WHERE id = ?");

foreach ($productCatNames as $productId => $catName) {
    if (isset($newNameMap[$catName])) {
        $updateStmt->execute(array($newNameMap[$catName], $productId));
        $matched++;
    } else {
        echo "  [MISS] 產品#{$productId} 找不到分類: {$catName}\n";
        $unmatched++;
    }
}

$db->exec("SET FOREIGN_KEY_CHECKS = 1");

echo "\n[OK] 對應成功: {$matched} 筆, 未對應: {$unmatched} 筆\n";
echo "\n=== 同步完成！ ===\n";
