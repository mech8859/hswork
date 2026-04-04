<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('admin')) { die('需要管理員權限'); }
header('Content-Type: text/plain; charset=utf-8');
$db = Database::getInstance();

$execute = isset($_GET['execute']) && $_GET['execute'] == '1';

echo $execute ? "=== 執行模式 ===\n\n" : "=== 預覽模式 === (加 ?execute=1 執行)\n\n";

// 把品牌「大華」改成「聯順」
$stmt = $db->query("SELECT id, name, brand FROM products WHERE brand = '大華'");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "品牌完全等於「大華」的產品: " . count($rows) . " 筆\n";

// 品牌欄位包含「大華」但不是純「大華」的
$stmt2 = $db->query("SELECT id, name, brand FROM products WHERE brand LIKE '%大華%' AND brand != '大華'");
$rows2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
echo "品牌包含「大華」但非純「大華」的: " . count($rows2) . " 筆\n";
if (count($rows2) > 0) {
    foreach ($rows2 as $r) {
        echo "  - ID:{$r['id']} | {$r['name']} | 品牌:{$r['brand']}\n";
    }
}

echo "\n";

if ($execute) {
    // 更新品牌 = '大華' 改為 '聯順'
    $upd = $db->prepare("UPDATE products SET brand = '聯順' WHERE brand = '大華'");
    $upd->execute();
    $cnt = $upd->rowCount();
    echo "[完成] 已將 {$cnt} 筆產品品牌從「大華」改為「聯順」\n";

    // 那筆品牌欄位是描述文字的也處理
    if (count($rows2) > 0) {
        foreach ($rows2 as $r) {
            $upd2 = $db->prepare("UPDATE products SET brand = '聯順' WHERE id = ?");
            $upd2->execute(array($r['id']));
            echo "[修正] ID:{$r['id']} 品牌改為「聯順」\n";
        }
    }
} else {
    echo "將執行:\n";
    echo "1. 將 " . count($rows) . " 筆品牌「大華」→「聯順」\n";
    if (count($rows2) > 0) {
        echo "2. 將 " . count($rows2) . " 筆品牌含「大華」→「聯順」\n";
    }
}

echo "\n完成\n";
