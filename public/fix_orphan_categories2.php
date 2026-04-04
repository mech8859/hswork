<?php
/**
 * 修復孤兒產品分類 — 第二批：處理剩餘未匹配的713筆
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }
header('Content-Type: text/html; charset=utf-8');
echo '<pre style="font-family:monospace;font-size:13px;line-height:1.6">';

$execute = isset($_GET['execute']) && $_GET['execute'] == '1';
echo $execute ? "=== 執行模式 ===" : "=== 預覽模式 === (加 ?execute=1 執行)";
echo "\n\n";

$db = Database::getInstance();

// 先確認/建立需要的子分類
$ensureCats = array(
    array('name' => '螺絲/管件/五金', 'parent' => '五金配件'),
    array('name' => '施工工具/設備', 'parent' => '五金配件'),
    array('name' => '照明設備', 'parent' => '五金配件'),
    array('name' => '控制線/隔離線', 'parent' => '常用線材區'),
    array('name' => 'SFP模組/網路配件', 'parent' => '網通設備'),
    array('name' => '繼電器/控制模組', 'parent' => '門禁系統'),
    array('name' => '偵測器/感測器', 'parent' => '自保防盜設備'),
    array('name' => '影像輸入輸出卡', 'parent' => '監控系統'),
    array('name' => '停車場設備', 'parent' => '斌仕科技BS系列'),
);
$newCatIds = array();
foreach ($ensureCats as $ec) {
    $parentId = $db->prepare("SELECT id FROM product_categories WHERE name = ? AND (parent_id IS NULL OR parent_id = 0)")->execute(array($ec['parent']));
    $parentStmt = $db->prepare("SELECT id FROM product_categories WHERE name = ?");
    $parentStmt->execute(array($ec['parent']));
    $pid = $parentStmt->fetchColumn();
    if (!$pid) {
        echo "[WARN] 找不到父分類「{$ec['parent']}」\n";
        continue;
    }
    $chk = $db->prepare("SELECT id FROM product_categories WHERE name = ? AND parent_id = ?");
    $chk->execute(array($ec['name'], $pid));
    $catId = $chk->fetchColumn();
    if (!$catId && $execute) {
        $db->prepare("INSERT INTO product_categories (name, parent_id) VALUES (?, ?)")->execute(array($ec['name'], $pid));
        $catId = (int)$db->lastInsertId();
        echo "[NEW CAT] {$ec['parent']} > {$ec['name']} id={$catId}\n";
    } elseif ($catId) {
        echo "[EXISTS] {$ec['parent']} > {$ec['name']} id={$catId}\n";
    } else {
        echo "[PREVIEW] 將建立 {$ec['parent']} > {$ec['name']}\n";
    }
    $newCatIds[$ec['name']] = $catId ?: 0;
}
echo "\n";

// 用 old category_id 直接批次對照
$oldCatMap = array(
    // cat=624: 五金零件雜項
    624 => $newCatIds['螺絲/管件/五金'] ?: 177, // fallback 固定夾
    // cat=655: 機架配件
    655 => 91, // 機櫃配件（已存在）
    // cat=680: 機架配件
    680 => 91,
    // cat=610: SFP/網路配件
    610 => $newCatIds['SFP模組/網路配件'] ?: 160,
    // cat=628: 光纖配件
    628 => 190, // 光纖配件（已存在）
    // cat=651: 偵測器
    651 => $newCatIds['偵測器/感測器'] ?: 264,
    // cat=585: 影像卡
    585 => $newCatIds['影像輸入輸出卡'] ?: 127,
    // cat=586: 監控配件
    586 => 127,
    // cat=597: 對講機配件
    597 => 34, // 室內機
    // cat=590: 電話配件
    590 => 251, // 電話單機
    // cat=594: 電話單機
    594 => 251,
    // cat=595: 電話錄音
    595 => 253,
    // cat=598: 電子鎖
    598 => 298, // 磁力鎖
    // cat=600: 繼電器
    600 => $newCatIds['繼電器/控制模組'] ?: 291,
    // cat=614: 無線設備
    614 => 159, // omada商用無線基地台
    // cat=616: HDMI
    616 => 11,
    // cat=621: 感測器/IoT
    621 => $newCatIds['偵測器/感測器'] ?: 264,
    // cat=625: 線槽
    625 => 183,
    // cat=631: 網路線/接頭
    631 => 201,
    // cat=635: 通訊線材
    635 => 205,
    // cat=636: 資訊插座
    636 => 195,
    // cat=637: 電源
    637 => 307,
    // cat=644: 停車場
    644 => 83, // 車道系列
    // cat=660: 電源插座
    660 => 185, // 電源延長線
    // cat=661: 廣播
    661 => 316,
    // cat=662: 擴大機
    662 => 322,
    // cat=663: 麥克風
    663 => 325,
    // cat=664: 喇叭
    664 => 321,
    // cat=667: 電腦
    667 => 97, // 液晶螢幕
    // cat=671: 螢幕
    671 => 97,
    // cat=676: 施工服務
    676 => 329, // 工程項次 > 基本工程
    // cat=589: UPS
    589 => 1,
);

// 取出剩餘孤兒
$orphanStmt = $db->query("
    SELECT p.id, p.name, p.category_id
    FROM products p
    LEFT JOIN product_categories pc ON p.category_id = pc.id
    WHERE p.category_id IS NOT NULL AND p.category_id > 0 AND pc.id IS NULL AND p.is_active = 1
    ORDER BY p.category_id, p.name
");
$orphans = $orphanStmt->fetchAll(PDO::FETCH_ASSOC);
echo "剩餘孤兒: " . count($orphans) . " 筆\n\n";

$matched = 0;
$unmatched = 0;

foreach ($orphans as $p) {
    $oldCat = (int)$p['category_id'];
    if (isset($oldCatMap[$oldCat]) && $oldCatMap[$oldCat] > 0) {
        // 確認目標分類存在
        $chk = $db->prepare("SELECT name FROM product_categories WHERE id = ?");
        $chk->execute(array($oldCatMap[$oldCat]));
        $catName = $chk->fetchColumn();
        if ($catName) {
            echo "[MATCH] {$p['name']} (old={$oldCat}) → {$catName}\n";
            if ($execute) {
                $db->prepare("UPDATE products SET category_id = ? WHERE id = ?")->execute(array($oldCatMap[$oldCat], $p['id']));
            }
            $matched++;
            continue;
        }
    }
    $unmatched++;
}

echo "\n=== 統計 ===\n";
echo "已匹配: {$matched}\n";
echo "仍未匹配: {$unmatched}\n";

if (!$execute) echo "\n→ 確認後加 ?execute=1 執行\n";
echo '</pre>';
