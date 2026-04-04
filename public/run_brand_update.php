<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('admin')) { die('需要管理員權限'); }
header('Content-Type: text/plain; charset=utf-8');
$db = Database::getInstance();

$execute = isset($_GET['execute']) && $_GET['execute'] == '1';

echo $execute ? "=== 執行模式 ===\n\n" : "=== 預覽模式 === (加 ?execute=1 執行)\n\n";

// 1. 大華 → 聯順/大華
$stmt1 = $db->query("SELECT COUNT(*) FROM products WHERE brand = '大華'");
$cnt1 = $stmt1->fetchColumn();
echo "品牌「大華」→「聯順/大華」: {$cnt1} 筆\n";

// 品牌欄位含「大華」但不是純「大華」也不是已經改過的
$stmt1b = $db->query("SELECT id, name, brand FROM products WHERE brand LIKE '%大華%' AND brand != '大華' AND brand NOT LIKE '聯順/%'");
$rows1b = $stmt1b->fetchAll(PDO::FETCH_ASSOC);
if (count($rows1b) > 0) {
    echo "品牌含「大華」但非純品牌名的: " . count($rows1b) . " 筆\n";
    foreach ($rows1b as $r) {
        echo "  - ID:{$r['id']} | {$r['name']} | 品牌:{$r['brand']}\n";
    }
}

// 2. 聲寶 → 正洋/聲寶
$stmt2 = $db->query("SELECT COUNT(*) FROM products WHERE brand = '聲寶'");
$cnt2 = $stmt2->fetchColumn();
echo "\n品牌「聲寶」→「正洋/聲寶」: {$cnt2} 筆\n";

// 3. 聯順維持不動
$stmt3 = $db->query("SELECT COUNT(*) FROM products WHERE brand = '聯順'");
$cnt3 = $stmt3->fetchColumn();
echo "\n品牌「聯順」維持不變: {$cnt3} 筆\n";

echo "\n";

if ($execute) {
    // 大華 → 聯順/大華
    $upd1 = $db->prepare("UPDATE products SET brand = '聯順/大華' WHERE brand = '大華'");
    $upd1->execute();
    echo "[完成] 大華 → 聯順/大華: " . $upd1->rowCount() . " 筆\n";

    // 含大華描述的也改
    foreach ($rows1b as $r) {
        $upd1b = $db->prepare("UPDATE products SET brand = '聯順/大華' WHERE id = ?");
        $upd1b->execute(array($r['id']));
        echo "[修正] ID:{$r['id']} → 聯順/大華\n";
    }

    // 聲寶 → 正洋/聲寶
    $upd2 = $db->prepare("UPDATE products SET brand = '正洋/聲寶' WHERE brand = '聲寶'");
    $upd2->execute();
    echo "[完成] 聲寶 → 正洋/聲寶: " . $upd2->rowCount() . " 筆\n";
} else {
    echo "將執行:\n";
    echo "1. {$cnt1} 筆「大華」→「聯順/大華」\n";
    if (count($rows1b) > 0) {
        echo "   + " . count($rows1b) . " 筆含大華描述→「聯順/大華」\n";
    }
    echo "2. {$cnt2} 筆「聲寶」→「正洋/聲寶」\n";
    echo "3. {$cnt3} 筆「聯順」不動\n";
}

echo "\n完成\n";
