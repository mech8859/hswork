<?php
/**
 * 新增工程項次到產品分類
 * 從 engineering_items 表讀取現有分類，建立到 product_categories
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }

header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();

echo "=== 新增工程項次到產品分類 ===\n\n";

// 1. 檢查是否已存在
$exists = $db->query("SELECT id FROM product_categories WHERE name = '工程項次'")->fetchColumn();
if ($exists) {
    echo "「工程項次」主分類已存在 (ID: {$exists})，跳過\n";
} else {
    // 建立主分類
    $db->prepare("INSERT INTO product_categories (name, parent_id, sort) VALUES (?, NULL, 999)")->execute(array('工程項次'));
    $exists = $db->lastInsertId();
    echo "[+] 建立主分類「工程項次」ID: {$exists}\n";
}
$mainCatId = $exists;

// 2. 從 engineering_items 取得所有分類
$categories = $db->query("SELECT DISTINCT category FROM engineering_items WHERE category IS NOT NULL AND category != '' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

echo "\n工程項次分類: " . count($categories) . " 個\n\n";

foreach ($categories as $catName) {
    // 檢查子分類是否已存在
    $stmt = $db->prepare("SELECT id FROM product_categories WHERE name = ? AND parent_id = ?");
    $stmt->execute(array($catName, $mainCatId));
    $subCatId = $stmt->fetchColumn();

    if ($subCatId) {
        echo "[SKIP] 「{$catName}」已存在 (ID: {$subCatId})\n";
    } else {
        $db->prepare("INSERT INTO product_categories (name, parent_id, sort) VALUES (?, ?, 0)")->execute(array($catName, $mainCatId));
        $subCatId = $db->lastInsertId();
        echo "[+] 「{$catName}」 ID: {$subCatId}\n";
    }

    // 列出該分類下的項目
    $items = $db->prepare("SELECT name, unit, default_price FROM engineering_items WHERE category = ? ORDER BY sort_order, name");
    $items->execute(array($catName));
    $itemList = $items->fetchAll(PDO::FETCH_ASSOC);
    foreach ($itemList as $item) {
        echo "     - {$item['name']} ({$item['unit']}) \${$item['default_price']}\n";
    }
}

echo "\n=== 完成 ===\n";
