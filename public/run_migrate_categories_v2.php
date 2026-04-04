<?php
/**
 * 分類遷移 v2 - 修正+全面掃描
 */
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('admin')) { die('需要管理員權限'); }

header('Content-Type: text/html; charset=utf-8');
echo '<pre style="font-family:monospace;font-size:13px;line-height:1.6">';

$db = Database::getInstance();
$dryRun = !isset($_GET['execute']);

echo $dryRun ? "=== 預覽模式 === (加 ?execute=1 執行)\n\n" : "=== 執行模式 ===\n\n";

// 取得新分類 ID
function getNewCatId($db, $name, $parentName = null) {
    if ($parentName) {
        $p = $db->prepare("SELECT id FROM product_categories WHERE name = ? AND (parent_id IS NULL OR parent_id = 0)");
        $p->execute(array($parentName));
        $pid = $p->fetchColumn();
        if (!$pid) return null;
        $s = $db->prepare("SELECT id FROM product_categories WHERE name = ? AND parent_id = ?");
        $s->execute(array($name, $pid));
        return $s->fetchColumn() ?: null;
    }
    $s = $db->prepare("SELECT id FROM product_categories WHERE name = ? AND (parent_id IS NULL OR parent_id = 0)");
    $s->execute(array($name));
    return $s->fetchColumn() ?: null;
}

// 遞迴取得所有子孫 ID
function getDescendants($db, $catId) {
    $all = array($catId);
    $queue = array($catId);
    while (!empty($queue)) {
        $cur = array_shift($queue);
        $ch = $db->prepare("SELECT id FROM product_categories WHERE parent_id = ?");
        $ch->execute(array($cur));
        foreach ($ch->fetchAll(PDO::FETCH_COLUMN) as $cid) {
            $all[] = $cid;
            $queue[] = $cid;
        }
    }
    return $all;
}

// 移動產品
function moveProducts($db, $oldCatName, $targetId, $dryRun) {
    // 找舊分類
    $stmt = $db->prepare("SELECT id FROM product_categories WHERE name = ?");
    $stmt->execute(array($oldCatName));
    $oldIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (empty($oldIds)) return 0;

    $allIds = array();
    foreach ($oldIds as $oid) {
        $allIds = array_merge($allIds, getDescendants($db, $oid));
    }
    $allIds = array_unique($allIds);
    // 排除新分類本身（避免把已移好的又移走）
    $allIds = array_filter($allIds, function($id) use ($targetId) { return $id != $targetId; });
    if (empty($allIds)) return 0;

    $ph = implode(',', array_fill(0, count($allIds), '?'));
    $cnt = $db->prepare("SELECT COUNT(*) FROM products WHERE category_id IN ({$ph})");
    $cnt->execute(array_values($allIds));
    $count = (int)$cnt->fetchColumn();

    if ($count > 0 && !$dryRun) {
        $params = array_merge(array($targetId), array_values($allIds));
        $db->prepare("UPDATE products SET category_id = ? WHERE category_id IN ({$ph})")->execute($params);
    }
    return $count;
}

// =============================================
// 全面移動對照表
// =============================================
echo "--- 全面移動產品 ---\n";

$moves = array(
    // [舊分類名, 新頂層, 新子分類(null=頂層本身)]
    // 監控
    array('監控', '01.監控系統', null),
    array('監控系統', '01.監控系統', null),
    array('中央監控', '01.監控系統', '中央監控'),
    // 門禁
    array('門禁', '02.門禁系統', null),
    array('門禁系統', '02.門禁系統', null),
    array('電子鎖', '02.門禁系統', '電子鎖'),
    array('感應/開門按鈕', '02.門禁系統', '開門按鈕/感應器'),
    // 對講機
    array('對講機系統', '03.對講機系統', null),
    array('斌仕科技BS系列', '03.對講機系統', null),
    // 總機
    array('電話總機', '04.總機/電話系統', null),
    array('總機系統', '04.總機/電話系統', null),
    // 網通
    array('網通', '05.網通設備', null),
    array('網通設備', '05.網通設備', null),
    // 音響
    array('商用音響', '06.音響/廣播系統', null),
    array('音響喇叭設備', '06.音響/廣播系統', null),
    array('麥克風', '06.音響/廣播系統', '麥克風'),
    // 投影/視聽
    array('投影機、配件', '07.投影機/視聽設備', null),
    array('電視/螢幕', '07.投影機/視聽設備', '電視/螢幕'),
    array('電視吊架', '07.投影機/視聽設備', '電視壁掛架'),
    array('液晶螢幕', '07.投影機/視聽設備', '液晶螢幕'),
    array('電腦.視聽', '07.投影機/視聽設備', null),
    // 線材
    array('線材', '08.線材', null),
    array('線材&相關配件', '08.線材', null),
    array('HDMI', '08.線材', 'HDMI/VGA訊號線'),
    array('HDMI VGA配件', '08.線材', 'HDMI/VGA訊號線'),
    array('VGA', '08.線材', 'HDMI/VGA訊號線'),
    // 光纖
    array('光纖', '09.光纖設備', null),
    // 配線
    array('資訊插座', '10.配線器材', '資訊插座/面板'),
    array('壓條/線槽', '10.配線器材', '線槽/壓條'),
    array('動力箱/防水盒', '10.配線器材', '動力箱/防水盒'),
    array('Panasonic IoT智慧開關(WiFi)', '10.配線器材', '蓋板(Glatima/RISNA)'),
    // 機櫃/電源
    array('機櫃', '11.機櫃/電源', '機櫃'),
    array('UPS', '11.機櫃/電源', 'UPS不斷電系統'),
    array('不斷電系統', '11.機櫃/電源', 'UPS不斷電系統'),
    array('CyberPower UPS不斷電系統', '11.機櫃/電源', 'UPS不斷電系統'),
    array('PDU', '11.機櫃/電源', 'PDU電源分配器'),
    array('電源', '11.機櫃/電源', '電源供應器'),
    array('鋰電池', '11.機櫃/電源', '鋰電池'),
    array('儲存', '11.機櫃/電源', null),
    // 防盜
    array('自保防盜設備', '12.防盜/保全系統', null),
    array('緊急系統', '12.防盜/保全系統', '緊急系統'),
    array('紅外線', '12.防盜/保全系統', '紅外線'),
    // 智能家居
    array('智能家居', '13.智能家居/IoT', null),
    array('IOT智能捲門控制', '13.智能家居/IoT', 'IoT捲門控制'),
    array('保險櫃', '13.智能家居/IoT', '保險櫃'),
    // 車道
    array('車道系統', '14.車道系統', null),
    // 工程耗材
    array('五金另料', '15.工程耗材/五金', '五金零件'),
    array('工具/設備', '15.工程耗材/五金', '工具/設備'),
    array('高空作業車', '15.工程耗材/五金', '高空作業車'),
    array('燈具', '15.工程耗材/五金', '燈具'),
    array('太陽能', '15.工程耗材/五金', '太陽能'),
    array('電風扇', '15.工程耗材/五金', '電風扇'),
    array('支架/立柱', '15.工程耗材/五金', null),
    array('立柱/支架', '15.工程耗材/五金', null),
    // 非產品（移到耗材暫存）
    array('廠商聯絡資料', '15.工程耗材/五金', null),
    array('財務行銷回報資料', '15.工程耗材/五金', null),
    array('特殊案例', '15.工程耗材/五金', null),
    array('授權', '15.工程耗材/五金', null),
    array('文具用品', '15.工程耗材/五金', null),
    // 工程項次
    array('工作項次', '16.工程施工項次', null),
    array('檢修收費標準', '16.工程施工項次', null),
);

$totalMoved = 0;
foreach ($moves as $m) {
    $targetId = null;
    if ($m[2]) {
        $targetId = getNewCatId($db, $m[2], $m[1]);
    }
    if (!$targetId) {
        $targetId = getNewCatId($db, $m[1]);
    }
    if (!$targetId) {
        echo "  [警告] 找不到: {$m[1]}" . ($m[2] ? " > {$m[2]}" : '') . "\n";
        continue;
    }

    $cnt = moveProducts($db, $m[0], $targetId, $dryRun);
    if ($cnt > 0) {
        $target = $m[1] . ($m[2] ? " > {$m[2]}" : '');
        echo "  [移動] {$m[0]} → {$target} ({$cnt} 個產品)\n";
        $totalMoved += $cnt;
    }
}

echo "\n  共移動 {$totalMoved} 個產品\n";

// =============================================
// 刪除空的舊分類（新分類以外的）
// =============================================
echo "\n--- 清除空舊分類 ---\n";

// 取得所有新分類 ID（含子分類）
$newTopIds = array();
for ($i = 1; $i <= 16; $i++) {
    $prefix = str_pad($i, 2, '0', STR_PAD_LEFT) . '.';
    $stmt = $db->prepare("SELECT id FROM product_categories WHERE name LIKE ?");
    $stmt->execute(array($prefix . '%'));
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $nid) {
        $newTopIds = array_merge($newTopIds, getDescendants($db, $nid));
    }
}
$newTopIds = array_unique($newTopIds);

// 找所有不在新分類中且沒有產品的舊分類
$allCats = $db->query("SELECT c.id, c.name, c.parent_id,
    (SELECT COUNT(*) FROM products WHERE category_id = c.id) as pcnt,
    (SELECT COUNT(*) FROM product_categories WHERE parent_id = c.id) as ccnt
    FROM product_categories c ORDER BY c.id")->fetchAll(PDO::FETCH_ASSOC);

$deleteCount = 0;
$keepCount = 0;
// 先刪子分類再刪父分類，從最深的開始
$toDelete = array();
foreach ($allCats as $c) {
    if (in_array($c['id'], $newTopIds)) continue; // 新分類不動
    if ((int)$c['pcnt'] === 0 && (int)$c['ccnt'] === 0) {
        $toDelete[] = $c;
    } else {
        $keepCount++;
    }
}

if (!$dryRun) {
    foreach ($toDelete as $c) {
        $db->prepare("DELETE FROM product_categories WHERE id = ?")->execute(array($c['id']));
        $deleteCount++;
    }
    echo "  已刪除 {$deleteCount} 個空舊分類\n";
} else {
    echo "  將刪除 " . count($toDelete) . " 個空舊分類\n";
}

// 顯示仍有產品的舊分類
echo "\n--- 仍有產品的舊分類 ---\n";
foreach ($allCats as $c) {
    if (in_array($c['id'], $newTopIds)) continue;
    if ((int)$c['pcnt'] > 0) {
        echo "  ID:{$c['id']} {$c['name']} ({$c['pcnt']} 產品)\n";
    }
}

// =============================================
// 最終統計
// =============================================
echo "\n--- 最終統計 ---\n";
$totalProducts = $db->query("SELECT COUNT(*) FROM products")->fetchColumn();
$totalCats = $db->query("SELECT COUNT(*) FROM product_categories")->fetchColumn();
$uncategorized = $db->query("SELECT COUNT(*) FROM products WHERE category_id IS NULL OR category_id = 0")->fetchColumn();

echo "總產品: {$totalProducts}\n";
echo "總分類: {$totalCats}\n";
echo "未分類: {$uncategorized}\n";

// 新分類產品統計
echo "\n--- 新分類產品統計 ---\n";
for ($i = 1; $i <= 16; $i++) {
    $prefix = str_pad($i, 2, '0', STR_PAD_LEFT) . '.';
    $stmt = $db->prepare("SELECT id, name FROM product_categories WHERE name LIKE ? AND (parent_id IS NULL OR parent_id = 0)");
    $stmt->execute(array($prefix . '%'));
    $top = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$top) continue;
    $ids = getDescendants($db, $top['id']);
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $cnt = $db->prepare("SELECT COUNT(*) FROM products WHERE category_id IN ({$ph})");
    $cnt->execute($ids);
    echo "  {$top['name']}: " . $cnt->fetchColumn() . " 個產品\n";
}

if ($dryRun) {
    echo "\n=== 預覽模式，加 ?execute=1 執行 ===\n";
}
echo '</pre>';
