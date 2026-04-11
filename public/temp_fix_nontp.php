<?php
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../includes/bootstrap.php';
$db = Database::getInstance();

$mode = isset($_GET['run']) ? 'execute' : 'preview';
echo "=== 非TP產品歸位 + 建新分類 ===\n";
echo "模式: $mode（加 ?run=1 執行）\n\n";

// 1. 建新分類（如果不存在）
// UniFi → 網通設備(145) 下
$unifiCatId = null;
$chk = $db->prepare("SELECT id FROM product_categories WHERE name = 'UniFi' AND parent_id = 145");
$chk->execute();
$existing = $chk->fetch(PDO::FETCH_ASSOC);
if ($existing) {
    $unifiCatId = $existing['id'];
    echo "UniFi 分類已存在 (id:$unifiCatId)\n";
} else {
    if ($mode === 'execute') {
        $db->prepare("INSERT INTO product_categories (name, parent_id) VALUES ('UniFi', 145)")->execute();
        $unifiCatId = $db->lastInsertId();
        echo "已建立 UniFi 分類 (id:$unifiCatId)\n";
    } else {
        $unifiCatId = 'NEW';
        echo "[預覽] 將建立 UniFi 分類 (parent:145 網通設備)\n";
    }
}

// 網通-其他
$otherNetCatId = null;
$chk = $db->prepare("SELECT id FROM product_categories WHERE name LIKE '%其他%' AND parent_id = 145");
$chk->execute();
$existing = $chk->fetch(PDO::FETCH_ASSOC);
if ($existing) {
    $otherNetCatId = $existing['id'];
    echo "網通-其他 分類已存在 (id:$otherNetCatId)\n";
} else {
    if ($mode === 'execute') {
        $db->prepare("INSERT INTO product_categories (name, parent_id) VALUES ('其他網通設備', 145)")->execute();
        $otherNetCatId = $db->lastInsertId();
        echo "已建立 其他網通設備 分類 (id:$otherNetCatId)\n";
    } else {
        $otherNetCatId = 'NEW';
        echo "[預覽] 將建立 其他網通設備 分類 (parent:145 網通設備)\n";
    }
}

echo "\n";

// 2. 分類規則
// Zyxel 分類 ID
$zyxelCatId = 162;
// 監控相關配件
$monitorAccessoryCatId = 125;

// TP-LINK 相關分類（目前塞了非TP產品的）
$tpCatIds = '152,153,154,155,156,157,158,159,160,161,371';

$products = $db->query("SELECT p.id, p.model, SUBSTRING(p.name,1,50) as name, p.category_id, c.name as cat_name
    FROM products p LEFT JOIN product_categories c ON p.category_id = c.id
    WHERE p.category_id IN ($tpCatIds)
    ORDER BY p.model")->fetchAll(PDO::FETCH_ASSOC);

$changes = array();
$stats = array('Zyxel'=>0, 'UniFi'=>0, '大華PFS'=>0, '其他網通'=>0, 'TP保留'=>0);

foreach ($products as $p) {
    $model = $p['model'];
    $newCat = null;
    $reason = '';

    // TP-LINK 自家產品 — 不動
    if (preg_match('/^(EAP|ER[0-9]|OC[23]00|SG[0-9]|SX[0-9]|IES|ES[0-9]|DS[01]|TL-|SM[0-9]|MC[12]|POE[0-9]|PoE[0-9]|SF[0-9]|SL[0-9]|DECO)/i', $model)) {
        $stats['TP保留']++;
        continue;
    }

    // Zyxel
    if (preg_match('/^(GS[0-9]|GS-|NWA|XGS|XMG|LS1|GS1[0-9])/i', $model)) {
        $newCat = $zyxelCatId;
        $reason = 'Zyxel→162';
        $stats['Zyxel']++;
    }
    // UniFi
    elseif (preg_match('/^(USW|U6|U-POE|UAP)/i', $model)) {
        $newCat = $unifiCatId;
        $reason = 'UniFi';
        $stats['UniFi']++;
    }
    // 大華 PFS 交換器 → 監控配件
    elseif (preg_match('/^PFS/i', $model)) {
        $newCat = $monitorAccessoryCatId;
        $reason = '大華PFS→監控配件';
        $stats['大華PFS']++;
    }
    // 其他品牌交換器/基地台
    elseif (preg_match('/^(PS-|NF|WSG|HS-812|6GK|G4 |D-Link|FG8|JS-|M330|MR400|G403)/i', $model) ||
            preg_match('/^(Netgear|USW|U6-IW|2327|2328)/i', $p['name'])) {
        $newCat = $otherNetCatId;
        $reason = '其他網通';
        $stats['其他網通']++;
    }
    // G4 Doorbell (UniFi)
    elseif (preg_match('/^G4/i', $model)) {
        $newCat = $unifiCatId;
        $reason = 'UniFi門鈴';
        $stats['UniFi']++;
    }
    // 4G基地台（非TP的）
    elseif (preg_match('/4G基地台|5G基地台/u', $p['name']) && !preg_match('/^(TL-|ER)/i', $model)) {
        $newCat = $otherNetCatId;
        $reason = '其他4G/5G';
        $stats['其他網通']++;
    }
    else {
        // 不確定的跳過
        echo sprintf("[?] #%s %s | %s | %s\n", $p['id'], $model, $p['name'], $p['cat_name']);
        continue;
    }

    if ($newCat && $newCat !== 'NEW') {
        $changes[] = array('id' => $p['id'], 'cat_id' => $newCat);
    }
    echo sprintf("[%s] #%s %s → %s\n", $reason, $p['id'], $model, $reason);
}

echo "\n=== 統計 ===\n";
foreach ($stats as $k => $v) {
    if ($v > 0) echo "$k: $v 筆\n";
}
echo "需變更: " . count($changes) . " 筆\n";

if ($mode === 'execute' && !empty($changes)) {
    echo "\n=== 執行更新 ===\n";
    $stmt = $db->prepare("UPDATE products SET category_id = ? WHERE id = ?");
    foreach ($changes as $ch) {
        $stmt->execute(array($ch['cat_id'], $ch['id']));
    }
    echo "已更新 " . count($changes) . " 筆\n";
}
