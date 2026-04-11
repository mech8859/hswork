<?php
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../includes/bootstrap.php';
$db = Database::getInstance();

// 目標分類 ID
$cats = array(
    'ip_2mp'    => 387,  // 聯順聯網 IP → 2MP IPC
    'ip_5mp'    => 388,  // 聯順聯網 IP → 5MP IPC
    'ip_8mp'    => 389,  // 聯順聯網 IP → 8MP IPC
    'ip_nvr'    => 391,  // 聯順聯網 IP → NVR 數位監控主機
    'hdcvi_2mp' => 383,  // 聯順聯網 類比 → 2MP 類比
    'hdcvi_5mp' => 384,  // 聯順聯網 類比 → 5MP類比
    'hdcvi_8mp' => 385,  // 聯順聯網 類比 → 8MP 類比
    'hdcvi_dvr' => 390,  // 聯順聯網 類比 → DVR/XVR 監控主機
);

$mode = isset($_GET['run']) ? 'execute' : 'preview';

// 查所有大華/聯順聯網相關產品（型號含 IPC-、HAC-、XVR、NVR、DH-、HS-4K、HS-I3）
$products = $db->query("SELECT p.id, p.name, p.model, p.category_id, c.name as cat_name
    FROM products p LEFT JOIN product_categories c ON p.category_id = c.id
    WHERE p.model LIKE 'IPC-%' OR p.model LIKE 'HAC-%'
    OR p.model LIKE 'XVR%' OR p.model LIKE 'NVR%'
    OR p.model LIKE 'DH-IPC-%' OR p.model LIKE 'DH-HAC-%'
    OR p.model LIKE 'DH-XVR%' OR p.model LIKE 'DH-NVR%'
    OR p.model LIKE 'HS-4K%' OR p.model LIKE 'HS-I3%'
    OR p.model LIKE 'SD-%' OR p.model LIKE 'DH-SD-%'
    ORDER BY p.model")->fetchAll(PDO::FETCH_ASSOC);

echo "=== 聯順聯網（大華 DAHUA）產品分類 ===\n";
echo "模式: $mode（加 ?run=1 執行更新）\n";
echo "符合型號規則的產品: " . count($products) . " 筆\n\n";

$stats = array_fill_keys(array_keys($cats), 0);
$stats['skip'] = 0;
$changes = array();

foreach ($products as $p) {
    $name = $p['name'];
    $model = $p['model'];
    $newCat = null;
    $reason = '';

    // 去掉 DH- 前綴
    $m = preg_replace('/^DH-/i', '', $model);

    // === NVR 主機 ===
    if (preg_match('/^NVR/i', $m) || preg_match('/^HS-4K/i', $m)) {
        $newCat = 'ip_nvr';
        $reason = 'NVR主機';
    }
    // === DVR/XVR 主機 ===
    elseif (preg_match('/^XVR/i', $m) || preg_match('/^HS-I3/i', $m)) {
        $newCat = 'hdcvi_dvr';
        $reason = 'DVR/XVR主機';
    }
    // === IPC 網路攝影機 ===
    elseif (preg_match('/^IPC-/i', $m)) {
        $mp = detectMP($name, $model);
        if ($mp >= 8) { $newCat = 'ip_8mp'; $reason = $mp . 'MP IPC'; }
        elseif ($mp >= 5) { $newCat = 'ip_5mp'; $reason = $mp . 'MP IPC'; }
        elseif ($mp >= 3) { $newCat = 'ip_5mp'; $reason = $mp . 'MP IPC→5MP'; }
        elseif ($mp === 2) { $newCat = 'ip_2mp'; $reason = '2MP IPC'; }
        else { $newCat = 'ip_5mp'; $reason = '?MP IPC→5MP'; }
    }
    // === HAC 類比攝影機 ===
    elseif (preg_match('/^HAC-/i', $m)) {
        $mp = detectMP($name, $model);
        if ($mp >= 8) { $newCat = 'hdcvi_8mp'; $reason = $mp . 'MP 類比'; }
        elseif ($mp >= 5) { $newCat = 'hdcvi_5mp'; $reason = $mp . 'MP 類比'; }
        elseif ($mp >= 3) { $newCat = 'hdcvi_5mp'; $reason = $mp . 'MP 類比→5MP'; }
        elseif ($mp === 2) { $newCat = 'hdcvi_2mp'; $reason = '2MP 類比'; }
        else { $newCat = 'hdcvi_5mp'; $reason = '?MP 類比→5MP'; }
    }
    // === SD 快速球（通常是IP）===
    elseif (preg_match('/^SD-/i', $m)) {
        $mp = detectMP($name, $model);
        if ($mp >= 8) { $newCat = 'ip_8mp'; $reason = $mp . 'MP 快速球'; }
        elseif ($mp >= 5) { $newCat = 'ip_5mp'; $reason = $mp . 'MP 快速球'; }
        else { $newCat = 'ip_2mp'; $reason = $mp . 'MP 快速球'; }
    }

    if ($newCat) {
        $targetCatId = $cats[$newCat];
        // 如果已經在正確分類就跳過
        if ($p['category_id'] == $targetCatId) {
            $stats['skip']++;
            continue;
        }
        $stats[$newCat]++;
        $changes[] = array('id' => $p['id'], 'cat_id' => $targetCatId);
        echo sprintf("[%s] #%s %s → %s (原:%s)\n", $reason, $p['id'], $m, $newCat, $p['cat_name']);
    }
}

echo "\n=== 統計 ===\n";
foreach ($stats as $k => $v) {
    if ($v > 0) echo "$k: $v 筆\n";
}
echo "需變更: " . count($changes) . " 筆\n";

if ($mode === 'execute' && !empty($changes)) {
    echo "\n=== 執行更新 ===\n";
    $stmt = $db->prepare("UPDATE products SET category_id = ? WHERE id = ?");
    $count = 0;
    foreach ($changes as $ch) {
        $stmt->execute(array($ch['cat_id'], $ch['id']));
        $count++;
    }
    echo "已更新 $count 筆產品分類！\n";
}

function detectMP($name, $model) {
    // 從品名判斷
    if (preg_match('/8MP|800萬|4K/u', $name)) return 8;
    if (preg_match('/6MP|600萬/u', $name)) return 6;
    if (preg_match('/5MP|500萬/u', $name)) return 5;
    if (preg_match('/4MP|400萬/u', $name)) return 4;
    if (preg_match('/3MP|3K|300萬/u', $name)) return 3;
    if (preg_match('/2MP|200萬/u', $name)) return 2;
    if (preg_match('/1080P/u', $name)) return 2;

    // 從大華型號規則判斷（型號中的數字暗示畫素）
    // 例如 IPC-HFW2831T = 8MP, IPC-HFW2531T = 5MP, IPC-HFW2231T = 2MP
    // 第4碼: 8=8MP, 5=5MP, 4=4MP, 2=2MP
    if (preg_match('/(?:IPC|HAC)-[A-Z]+(\d)\d{2}/i', $model, $m)) {
        $digit = (int)$m[1];
        if ($digit === 8) return 8;
        if ($digit === 5) return 5;
        if ($digit === 4) return 4;
        if ($digit === 2) return 2;
    }

    return 0;
}
