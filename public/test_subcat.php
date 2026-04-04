<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();
header('Content-Type: text/plain; charset=utf-8');

// 監控系統 ID=13
$catId = 13;
echo "分類ID={$catId} 監控系統\n\n";

// 直接查子分類
$stmt = $db->prepare("SELECT id, name, parent_id FROM product_categories WHERE parent_id = ? ORDER BY name");
$stmt->execute(array($catId));
$subs = $stmt->fetchAll();
echo "子分類數: " . count($subs) . "\n";
foreach ($subs as $s) {
    echo "  ID={$s['id']}: {$s['name']} (parent_id={$s['parent_id']})\n";
}

echo "\nJSON: " . json_encode($subs, JSON_UNESCAPED_UNICODE) . "\n";

// 也查 parent_id 的資料型態
echo "\n=== parent_id 資料型態 ===\n";
$stmt2 = $db->query("SELECT id, name, parent_id, IFNULL(parent_id,'NULL') as pid_str FROM product_categories WHERE parent_id = 13 LIMIT 5");
$rows = $stmt2->fetchAll();
echo "用 parent_id = 13 查: " . count($rows) . " 筆\n";

$stmt3 = $db->query("SELECT id, name, parent_id FROM product_categories WHERE parent_id = '13' LIMIT 5");
$rows3 = $stmt3->fetchAll();
echo "用 parent_id = '13' 查: " . count($rows3) . " 筆\n";

// 看所有有 parent_id 的
$stmt4 = $db->query("SELECT parent_id, COUNT(*) as cnt FROM product_categories WHERE parent_id IS NOT NULL AND parent_id > 0 GROUP BY parent_id ORDER BY cnt DESC LIMIT 10");
echo "\n=== 有子分類的父分類 ===\n";
foreach ($stmt4->fetchAll() as $r) {
    echo "  parent_id={$r['parent_id']}: {$r['cnt']} 個子分類\n";
}
