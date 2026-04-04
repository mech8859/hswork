<?php
/**
 * 修復孤兒產品分類 — 根據產品名稱自動匹配到現有子分類
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

// 載入所有現有分類（只取有父分類的子分類）
$catStmt = $db->query("
    SELECT c.id, c.name, c.parent_id, p.name as parent_name
    FROM product_categories c
    LEFT JOIN product_categories p ON c.parent_id = p.id
    WHERE c.parent_id IS NOT NULL AND c.parent_id > 0
    ORDER BY p.name, c.name
");
$allCats = $catStmt->fetchAll(PDO::FETCH_ASSOC);

// 建立關鍵字 → 分類 ID 對照表（優先匹配最細的分類）
$keywordMap = array(
    // 監控主機
    'XVR' => 114, 'HVR' => 114, 'DVR' => 114, '數位錄影主機' => 114, '混合型錄影主機' => 114,
    'NVR' => 120, '網路型監控錄影主機' => 120, '網路錄影主機' => 120,
    // 攝影機
    '紅外線攝影機' => 144, '全彩攝影機' => 142, '槍型' => 144, '球型' => 144, '半球' => 144,
    '快速球' => 122, 'PTZ' => 122, 'Speed Dome' => 122,
    '網路攝影機' => 138, 'IP Cam' => 138, 'IPC' => 138,
    'VIGI' => 110,
    // 監控配件
    '支架' => 135, '防護架' => 135, '防護罩' => 135,
    '防水盒' => 134, '接線盒' => 134, '防水箱' => 134,
    '雙絞線影像' => 136, '同軸影像擴充' => 126,
    '控制鍵盤' => 131,
    '監控記憶卡' => 133, '記憶卡' => 133,
    // 硬碟
    '硬碟' => 124, 'HDD' => 124, 'Seagate' => 124, 'WD' => 124,
    '收音麥克風' => 112,
    // 機櫃
    '機櫃' => 90, '開放式機櫃' => 90,
    'PDU' => 88, '電源分配' => 88,
    '儀器支撐板' => 92, '儀器支架' => 92,
    // 網通
    '交換器' => 160, 'Switch' => 160, 'PoE' => 160,
    '光電轉換' => 192, '光纖模組' => 188,
    '路由器' => 150, 'Router' => 150,
    'Mesh' => 148, '無線AP' => 148, '基地台' => 159,
    'TP-LINK' => 160, 'Dray' => 149,
    // 對講機
    '室內機' => 34, '門口機' => 35,
    '對講' => 34,
    'Hometek' => 34,
    'BB Home' => 31,
    // 門禁
    '讀卡機' => 288, '維根讀卡' => 288,
    '門禁控制器' => 290, 'SOYAL' => 290,
    '磁力鎖' => 298, '陽極鎖' => 301,
    '開門按鈕' => 300,
    '磁力鎖配件' => 299, '陽極鎖配件' => 302,
    '感應卡' => 297,
    '人臉' => 278, 'Face' => 278,
    // 電子鎖
    '電子鎖' => 298,
    // 電話
    '電話總機' => 240, '聯盟' => 240, 'ISDK' => 234, 'UD-2100' => 238, 'UD-60' => 239,
    '萬國' => 244, 'FX-' => 244,
    '電話單機' => 251, '話機' => 241,
    '電話錄音' => 253,
    // HDMI
    'HDMI' => 11, 'HDMI光纖' => 8, 'HDMI切換' => 5, 'HDMI分配' => 9, 'HDMI延' => 10,
    'VGA' => 13,
    // 線材
    '網路線' => 201, 'Cat.6' => 201, 'Cat6' => 201, 'Cat.5' => 201,
    '同軸' => 200, 'RG' => 200,
    '光纖' => 191, '光纜' => 191,
    '電話電纜' => 205, '通信電纜' => 205,
    '控制電纜' => 203, '電源線' => 203,
    '防盜線' => 204,
    '資訊插座' => 195, 'Cat.6A' => 195,
    '網路跳線' => 216, 'Patch' => 216,
    // 五金
    '束帶' => 181, '壓條' => 178, '線槽' => 183, '固定夾' => 177,
    '矽利康' => 182,
    // 電源
    '不斷電' => 1, 'UPS' => 1, 'CyberPower' => 1,
    '電源供應器' => 307, '明緯' => 307, '變壓器' => 307,
    '對講機電源' => 304,
    // 防盜
    '磁簧' => 263, 'PIR' => 261, '紅外線感知' => 264, '緊急按鈕' => 265,
    '捲門感知' => 262, '警報喇叭' => 267,
    '保全' => 81,
    // 車道
    '車道' => 83, '柵欄' => 83, '車牌' => 281,
    // 音響廣播
    '喇叭' => 321, '擴大機' => 322, '麥克風' => 325, '無線麥克風' => 326,
    '混音器' => 317, '廣播' => 316,
    'IP PA' => 314, 'TONNET' => 314,
    // 投影機
    '投影機' => 59, 'EPSON' => 59,
    '布幕' => 54,
    '吊架' => 78,
    // 電視
    '壁掛架' => 311, '懸吊架' => 312,
    '天線' => 220, '分歧器' => 222, '分配器' => 223,
    // IoT
    'Glatima' => 20, 'RISNA' => 21, '調光開關' => 22,
    '捲門控制' => 17,
    // 螢幕
    '液晶螢幕' => 97, '螢幕' => 97,
    // 配電箱/弱電箱
    '烤漆' => 134, '白鐵' => 134, '配電箱' => 134, '弱電箱' => 134,
);

// 取出所有孤兒產品
$orphanStmt = $db->query("
    SELECT p.id, p.name, p.model, p.category_id
    FROM products p
    LEFT JOIN product_categories pc ON p.category_id = pc.id
    WHERE p.category_id IS NOT NULL AND p.category_id > 0 AND pc.id IS NULL AND p.is_active = 1
    ORDER BY p.name
");
$orphanProducts = $orphanStmt->fetchAll(PDO::FETCH_ASSOC);
echo "孤兒產品: " . count($orphanProducts) . " 筆\n\n";

$matched = 0;
$unmatched = 0;
$unmatchedList = array();

foreach ($orphanProducts as $p) {
    $newCatId = null;
    $matchedKeyword = '';

    // 嘗試用關鍵字匹配
    foreach ($keywordMap as $keyword => $catId) {
        if (mb_strpos($p['name'], $keyword) !== false || ($p['model'] && mb_strpos($p['model'], $keyword) !== false)) {
            $newCatId = $catId;
            $matchedKeyword = $keyword;
            break;
        }
    }

    if ($newCatId) {
        // 確認分類存在
        $chk = $db->prepare("SELECT name FROM product_categories WHERE id = ?");
        $chk->execute(array($newCatId));
        $catName = $chk->fetchColumn();
        if ($catName) {
            echo "[MATCH] {$p['name']} → {$catName} (keyword: {$matchedKeyword})\n";
            if ($execute) {
                $db->prepare("UPDATE products SET category_id = ? WHERE id = ?")->execute(array($newCatId, $p['id']));
            }
            $matched++;
        } else {
            $unmatched++;
            $unmatchedList[] = $p;
        }
    } else {
        $unmatched++;
        $unmatchedList[] = $p;
    }
}

echo "\n=== 統計 ===\n";
echo "已匹配: {$matched}\n";
echo "未匹配: {$unmatched}\n";

if ($unmatched > 0) {
    echo "\n=== 未匹配的產品（前50筆）===\n";
    foreach (array_slice($unmatchedList, 0, 50) as $p) {
        echo "  [{$p['id']}] {$p['name']} (old cat={$p['category_id']})\n";
    }
}

if (!$execute) echo "\n→ 確認後加 ?execute=1 執行\n";
echo '</pre>';
