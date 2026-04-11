<?php
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../includes/bootstrap.php';
$db = Database::getInstance();

// TP-LINK 相關分類 ID
$tpCatIds = array(152,153,154,155,156,157,158,159,160,161,371,109,110,111,372,373);
$placeholders = implode(',', $tpCatIds);

echo "=== 目前在 TP-LINK 分類下，但可能不是 TP-LINK 的產品 ===\n\n";

$products = $db->query("SELECT p.id, p.model, SUBSTRING(p.name,1,45) as name, p.category_id, c.name as cat_name
    FROM products p LEFT JOIN product_categories c ON p.category_id = c.id
    WHERE p.category_id IN ($placeholders)
    ORDER BY c.name, p.model")->fetchAll(PDO::FETCH_ASSOC);

// TP-LINK 的型號前綴
$tpPrefixes = array('EAP','ER','OC2','OC3','SG','SX','IES','ES2','ES1','DS1','DS0',
    'TL-','MC1','MC2','SM','POE','PoE',
    'VIGI','NVR','InSight','Insight','S285','S385','S455','S485','S345','S445','S245','S325','S425','S225','S355','S655',
    'C250','C350','C450','C220','C320','C420','C230','C330','C430','C440','C540','C340',
    'BC5','H3AE');

$notTP = array();
foreach ($products as $p) {
    $isTP = false;
    foreach ($tpPrefixes as $prefix) {
        if (stripos($p['model'], $prefix) === 0) {
            $isTP = true;
            break;
        }
    }
    if (!$isTP) {
        $notTP[] = $p;
    }
}

echo "TP-LINK分類下共 " . count($products) . " 筆產品\n";
echo "其中非TP-LINK的: " . count($notTP) . " 筆\n\n";

foreach ($notTP as $p) {
    echo sprintf("#%-5s %-25s %-45s → %s\n", $p['id'], $p['model'], $p['name'], $p['cat_name']);
}
