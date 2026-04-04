<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }
header('Content-Type: text/html; charset=utf-8');
echo '<pre>';

$execute = isset($_GET['execute']) && $_GET['execute'] == '1';
echo $execute ? "=== 執行模式 ===" : "=== 預覽模式 ===";
echo "\n\n";

$db = Database::getInstance();

// 1. 在五金配件下建立「配電箱/弱電箱」子分類
$parentStmt = $db->prepare("SELECT id FROM product_categories WHERE name = '五金配件' LIMIT 1");
$parentStmt->execute();
$parentId = $parentStmt->fetchColumn();
if (!$parentId) { die("找不到「五金配件」分類\n"); }
echo "五金配件 id={$parentId}\n";

$chk = $db->prepare("SELECT id FROM product_categories WHERE name = '配電箱/弱電箱' AND parent_id = ?");
$chk->execute(array($parentId));
$newCatId = $chk->fetchColumn();

if (!$newCatId) {
    if ($execute) {
        $db->prepare("INSERT INTO product_categories (name, parent_id) VALUES ('配電箱/弱電箱', ?)")->execute(array($parentId));
        $newCatId = (int)$db->lastInsertId();
        echo "[NEW] 五金配件 > 配電箱/弱電箱 id={$newCatId}\n";
    } else {
        echo "[PREVIEW] 將建立 五金配件 > 配電箱/弱電箱\n";
        $newCatId = 0;
    }
} else {
    echo "[EXISTS] 五金配件 > 配電箱/弱電箱 id={$newCatId}\n";
}

// 2. 找出所有烤漆/白鐵/防水箱/動力箱/開關箱/弱電箱 相關產品（目前在防水盒&接線盒 id=134）
$keywords = array('烤漆', '白鐵 防水', '白鐵防水', '防水箱', '防水動力箱', '開關箱', '弱電箱', '配電箱', '明箱');
$where = array();
$params = array();
foreach ($keywords as $kw) {
    $where[] = "p.name LIKE ?";
    $params[] = '%' . $kw . '%';
}
$whereStr = implode(' OR ', $where);

$stmt = $db->prepare("
    SELECT p.id, p.name, p.category_id, pc.name as cat_name
    FROM products p
    LEFT JOIN product_categories pc ON p.category_id = pc.id
    WHERE ({$whereStr})
      AND p.is_active = 1
    ORDER BY p.name
");
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "\n找到 " . count($products) . " 筆相關產品\n\n";

$moved = 0;
foreach ($products as $p) {
    // 只移動目前在「防水盒&接線盒」(134) 或「螺絲/管件/五金」(351) 的
    if (!in_array($p['category_id'], array(134, 351))) {
        echo "[SKIP] {$p['name']} (目前分類: {$p['cat_name']} id={$p['category_id']})\n";
        continue;
    }
    echo "[MOVE] {$p['name']} ({$p['cat_name']}) → 配電箱/弱電箱\n";
    if ($execute && $newCatId) {
        $db->prepare("UPDATE products SET category_id = ? WHERE id = ?")->execute(array($newCatId, $p['id']));
    }
    $moved++;
}

echo "\n=== 完成 ===\n";
echo "移動: {$moved} 筆\n";
if (!$execute) echo "\n→ 確認後加 ?execute=1 執行\n";
echo '</pre>';
