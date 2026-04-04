<?php
/**
 * 匯入工資單價到產品目錄
 * 主分類：工資單價 → 子分類 → 各品項
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

// 工資資料定義
$laborData = array(
    'PVC管' => array(
        array('name' => 'PVC管 E16 (1/2")', 'model' => 'E16', 'price' => 30, 'unit' => 'M'),
        array('name' => 'PVC管 E20 (3/4")', 'model' => 'E20', 'price' => 40, 'unit' => 'M'),
        array('name' => 'PVC管 E28 (1")', 'model' => 'E28', 'price' => 40, 'unit' => 'M'),
        array('name' => 'PVC管 E35 (1-1/4")', 'model' => 'E35', 'price' => 50, 'unit' => 'M'),
        array('name' => 'PVC管 E41 (1-1/2")', 'model' => 'E41', 'price' => 50, 'unit' => 'M'),
        array('name' => 'PVC管 E52 (2")', 'model' => 'E52', 'price' => 70, 'unit' => 'M'),
        array('name' => 'PVC管 E65 (2-1/2")', 'model' => 'E65', 'price' => 80, 'unit' => 'M'),
        array('name' => 'PVC管 E80 (3")', 'model' => 'E80', 'price' => 90, 'unit' => 'M'),
        array('name' => 'PVC管 E100 (4")', 'model' => 'E100', 'price' => 120, 'unit' => 'M'),
        array('name' => 'PVC管 E125 (5")', 'model' => 'E125', 'price' => 140, 'unit' => 'M'),
        array('name' => 'PVC管 E150 (6")', 'model' => 'E150', 'price' => 160, 'unit' => 'M'),
        array('name' => 'PVC管 E200 (8")', 'model' => 'E200', 'price' => 200, 'unit' => 'M'),
    ),
    'EMT管' => array(
        array('name' => 'EMT管 E19 (1/2")', 'model' => 'E19', 'price' => 60, 'unit' => 'M'),
        array('name' => 'EMT管 E25 (3/4")', 'model' => 'E25', 'price' => 80, 'unit' => 'M'),
        array('name' => 'EMT管 E31 (1")', 'model' => 'E31', 'price' => 110, 'unit' => 'M'),
        array('name' => 'EMT管 E39 (1-1/4")', 'model' => 'E39', 'price' => 130, 'unit' => 'M'),
        array('name' => 'EMT管 E51 (1-1/2")', 'model' => 'E51', 'price' => 150, 'unit' => 'M'),
        array('name' => 'EMT管 E63 (2")', 'model' => 'E63', 'price' => 200, 'unit' => 'M'),
        array('name' => 'EMT管 E75 (2-1/2")', 'model' => 'E75', 'price' => 220, 'unit' => 'M'),
    ),
    'RSG管' => array(
        array('name' => 'RSG管 G16 (1/2")', 'model' => 'G16', 'price' => 100, 'unit' => 'M'),
        array('name' => 'RSG管 G22 (3/4")', 'model' => 'G22', 'price' => 130, 'unit' => 'M'),
        array('name' => 'RSG管 G28 (1")', 'model' => 'G28', 'price' => 170, 'unit' => 'M'),
        array('name' => 'RSG管 G36 (1-1/4")', 'model' => 'G36', 'price' => 190, 'unit' => 'M'),
        array('name' => 'RSG管 G42 (1-1/2")', 'model' => 'G42', 'price' => 210, 'unit' => 'M'),
        array('name' => 'RSG管 G54 (2")', 'model' => 'G54', 'price' => 240, 'unit' => 'M'),
        array('name' => 'RSG管 G70 (2-1/2")', 'model' => 'G70', 'price' => 280, 'unit' => 'M'),
        array('name' => 'RSG管 G82 (3")', 'model' => 'G82', 'price' => 330, 'unit' => 'M'),
        array('name' => 'RSG管 G104 (4")', 'model' => 'G104', 'price' => 420, 'unit' => 'M'),
    ),
    '網路線' => array(
        array('name' => '網路線 Cat 5E 佈線工資', 'model' => 'Cat5E', 'price' => 20, 'unit' => 'M'),
        array('name' => '網路線 Cat 6 佈線工資', 'model' => 'Cat6', 'price' => 30, 'unit' => 'M'),
        array('name' => '網路線 Cat 6A 佈線工資', 'model' => 'Cat6A', 'price' => 30, 'unit' => 'M'),
    ),
    '隔離線 UL2464' => array(
        array('name' => 'UL2464 0.75mm² 1P/2C 佈線工資', 'model' => '0.75-1P2C', 'price' => 20, 'unit' => 'M'),
        array('name' => 'UL2464 0.75mm² 2P/4C 佈線工資', 'model' => '0.75-2P4C', 'price' => 30, 'unit' => 'M'),
        array('name' => 'UL2464 0.75mm² 4P/8C 佈線工資', 'model' => '0.75-4P8C', 'price' => 50, 'unit' => 'M'),
        array('name' => 'UL2464 1.25mm² 1P/2C 佈線工資', 'model' => '1.25-1P2C', 'price' => 30, 'unit' => 'M'),
        array('name' => 'UL2464 1.25mm² 2P/4C 佈線工資', 'model' => '1.25-2P4C', 'price' => 40, 'unit' => 'M'),
        array('name' => 'UL2464 1.25mm² 4P/8C 佈線工資', 'model' => '1.25-4P8C', 'price' => 60, 'unit' => 'M'),
    ),
    '通信電纜' => array(
        array('name' => '通信電纜 0.5mm² 10P 佈線工資', 'model' => '0.5-10P', 'price' => 40, 'unit' => 'M'),
        array('name' => '通信電纜 0.5mm² 20P 佈線工資', 'model' => '0.5-20P', 'price' => 60, 'unit' => 'M'),
        array('name' => '通信電纜 0.5mm² 30P 佈線工資', 'model' => '0.5-30P', 'price' => 80, 'unit' => 'M'),
        array('name' => '通信電纜 0.5mm² 50P 佈線工資', 'model' => '0.5-50P', 'price' => 100, 'unit' => 'M'),
        array('name' => '通信電纜 0.5mm² 100P 佈線工資', 'model' => '0.5-100P', 'price' => 130, 'unit' => 'M'),
    ),
    '光纖纜線' => array(
        array('name' => '9/125 戶外鎧裝光纖 4C 佈線工資', 'model' => '9/125-4C', 'price' => 40, 'unit' => 'M'),
        array('name' => '9/125 戶外鎧裝光纖 8C 佈線工資', 'model' => '9/125-8C', 'price' => 50, 'unit' => 'M'),
        array('name' => '9/125 戶外鎧裝光纖 12C 佈線工資', 'model' => '9/125-12C', 'price' => 60, 'unit' => 'M'),
        array('name' => '9/125 戶外鎧裝光纖 24C 佈線工資', 'model' => '9/125-24C', 'price' => 60, 'unit' => 'M'),
        array('name' => '9/125 戶外鎧裝光纖 48C 佈線工資', 'model' => '9/125-48C', 'price' => 80, 'unit' => 'M'),
        array('name' => '9/125 戶外鎧裝光纖 96C 佈線工資', 'model' => '9/125-96C', 'price' => 90, 'unit' => 'M'),
        array('name' => '9/125 扁平光纜 4C 佈線工資', 'model' => '9/125F-4C', 'price' => 20, 'unit' => 'M'),
        array('name' => '9/125 扁平光纜 8C 佈線工資', 'model' => '9/125F-8C', 'price' => 20, 'unit' => 'M'),
    ),
    '動力線-單芯線' => array(
        array('name' => '單芯線 1.6mm² 佈線工資', 'model' => '1.6mm', 'price' => 15, 'unit' => 'M'),
        array('name' => '單芯線 2.0mm² 佈線工資', 'model' => '2.0mm', 'price' => 15, 'unit' => 'M'),
        array('name' => '單芯線 3.5mm² 佈線工資', 'model' => '3.5mm', 'price' => 20, 'unit' => 'M'),
        array('name' => '單芯線 5.0mm² 佈線工資', 'model' => '5.0mm', 'price' => 25, 'unit' => 'M'),
        array('name' => '單芯線 8mm² 佈線工資', 'model' => '8mm', 'price' => 30, 'unit' => 'M'),
    ),
    '電纜線-PVC' => array(
        array('name' => 'PVC電纜 14mm² 1C 佈線工資', 'model' => 'PVC-14-1C', 'price' => 40, 'unit' => 'M'),
        array('name' => 'PVC電纜 22mm² 1C 佈線工資', 'model' => 'PVC-22-1C', 'price' => 40, 'unit' => 'M'),
        array('name' => 'PVC電纜 30mm² 1C 佈線工資', 'model' => 'PVC-30-1C', 'price' => 60, 'unit' => 'M'),
        array('name' => 'PVC電纜 38mm² 1C 佈線工資', 'model' => 'PVC-38-1C', 'price' => 60, 'unit' => 'M'),
        array('name' => 'PVC電纜 50mm² 1C 佈線工資', 'model' => 'PVC-50-1C', 'price' => 70, 'unit' => 'M'),
        array('name' => 'PVC電纜 60mm² 1C 佈線工資', 'model' => 'PVC-60-1C', 'price' => 70, 'unit' => 'M'),
        array('name' => 'PVC電纜 80mm² 1C 佈線工資', 'model' => 'PVC-80-1C', 'price' => 80, 'unit' => 'M'),
        array('name' => 'PVC電纜 100mm² 1C 佈線工資', 'model' => 'PVC-100-1C', 'price' => 100, 'unit' => 'M'),
        array('name' => 'PVC電纜 125mm² 1C 佈線工資', 'model' => 'PVC-125-1C', 'price' => 130, 'unit' => 'M'),
        array('name' => 'PVC電纜 150mm² 1C 佈線工資', 'model' => 'PVC-150-1C', 'price' => 160, 'unit' => 'M'),
        array('name' => 'PVC電纜 200mm² 1C 佈線工資', 'model' => 'PVC-200-1C', 'price' => 180, 'unit' => 'M'),
        array('name' => 'PVC電纜 2.0mm² 2C 佈線工資', 'model' => 'PVC-2.0-2C', 'price' => 40, 'unit' => 'M'),
        array('name' => 'PVC電纜 3.5mm² 2C 佈線工資', 'model' => 'PVC-3.5-2C', 'price' => 60, 'unit' => 'M'),
        array('name' => 'PVC電纜 5.5mm² 2C 佈線工資', 'model' => 'PVC-5.5-2C', 'price' => 60, 'unit' => 'M'),
        array('name' => 'PVC電纜 2.0mm² 3C 佈線工資', 'model' => 'PVC-2.0-3C', 'price' => 50, 'unit' => 'M'),
        array('name' => 'PVC電纜 3.5mm² 3C 佈線工資', 'model' => 'PVC-3.5-3C', 'price' => 70, 'unit' => 'M'),
        array('name' => 'PVC電纜 5.5mm² 3C 佈線工資', 'model' => 'PVC-5.5-3C', 'price' => 90, 'unit' => 'M'),
        array('name' => 'PVC電纜 2.0mm² 4C 佈線工資', 'model' => 'PVC-2.0-4C', 'price' => 60, 'unit' => 'M'),
        array('name' => 'PVC電纜 3.5mm² 4C 佈線工資', 'model' => 'PVC-3.5-4C', 'price' => 80, 'unit' => 'M'),
        array('name' => 'PVC電纜 5.5mm² 4C 佈線工資', 'model' => 'PVC-5.5-4C', 'price' => 100, 'unit' => 'M'),
    ),
    '電纜線-XLPE' => array(
        array('name' => 'XLPE電纜 14mm² 1C 佈線工資', 'model' => 'XLPE-14-1C', 'price' => 50, 'unit' => 'M'),
        array('name' => 'XLPE電纜 22mm² 1C 佈線工資', 'model' => 'XLPE-22-1C', 'price' => 60, 'unit' => 'M'),
        array('name' => 'XLPE電纜 30mm² 1C 佈線工資', 'model' => 'XLPE-30-1C', 'price' => 70, 'unit' => 'M'),
        array('name' => 'XLPE電纜 38mm² 1C 佈線工資', 'model' => 'XLPE-38-1C', 'price' => 70, 'unit' => 'M'),
        array('name' => 'XLPE電纜 50mm² 1C 佈線工資', 'model' => 'XLPE-50-1C', 'price' => 80, 'unit' => 'M'),
        array('name' => 'XLPE電纜 60mm² 1C 佈線工資', 'model' => 'XLPE-60-1C', 'price' => 80, 'unit' => 'M'),
        array('name' => 'XLPE電纜 80mm² 1C 佈線工資', 'model' => 'XLPE-80-1C', 'price' => 100, 'unit' => 'M'),
        array('name' => 'XLPE電纜 100mm² 1C 佈線工資', 'model' => 'XLPE-100-1C', 'price' => 120, 'unit' => 'M'),
        array('name' => 'XLPE電纜 125mm² 1C 佈線工資', 'model' => 'XLPE-125-1C', 'price' => 150, 'unit' => 'M'),
        array('name' => 'XLPE電纜 150mm² 1C 佈線工資', 'model' => 'XLPE-150-1C', 'price' => 180, 'unit' => 'M'),
        array('name' => 'XLPE電纜 200mm² 1C 佈線工資', 'model' => 'XLPE-200-1C', 'price' => 200, 'unit' => 'M'),
        array('name' => 'XLPE電纜 250mm² 1C 佈線工資', 'model' => 'XLPE-250-1C', 'price' => 220, 'unit' => 'M'),
        array('name' => 'XLPE電纜 2.0mm² 2C 佈線工資', 'model' => 'XLPE-2.0-2C', 'price' => 50, 'unit' => 'M'),
        array('name' => 'XLPE電纜 3.5mm² 2C 佈線工資', 'model' => 'XLPE-3.5-2C', 'price' => 70, 'unit' => 'M'),
        array('name' => 'XLPE電纜 5.5mm² 2C 佈線工資', 'model' => 'XLPE-5.5-2C', 'price' => 70, 'unit' => 'M'),
        array('name' => 'XLPE電纜 2.0mm² 3C 佈線工資', 'model' => 'XLPE-2.0-3C', 'price' => 60, 'unit' => 'M'),
        array('name' => 'XLPE電纜 3.5mm² 3C 佈線工資', 'model' => 'XLPE-3.5-3C', 'price' => 80, 'unit' => 'M'),
        array('name' => 'XLPE電纜 5.5mm² 3C 佈線工資', 'model' => 'XLPE-5.5-3C', 'price' => 100, 'unit' => 'M'),
        array('name' => 'XLPE電纜 2.0mm² 4C 佈線工資', 'model' => 'XLPE-2.0-4C', 'price' => 70, 'unit' => 'M'),
        array('name' => 'XLPE電纜 3.5mm² 4C 佈線工資', 'model' => 'XLPE-3.5-4C', 'price' => 100, 'unit' => 'M'),
        array('name' => 'XLPE電纜 5.5mm² 4C 佈線工資', 'model' => 'XLPE-5.5-4C', 'price' => 120, 'unit' => 'M'),
    ),
    '線架-鋁製梯型' => array(
        array('name' => '鋁製梯型線架 100mm 工資', 'model' => 'AL-100', 'price' => 150, 'unit' => 'M'),
        array('name' => '鋁製梯型線架 200mm 工資', 'model' => 'AL-200', 'price' => 230, 'unit' => 'M'),
        array('name' => '鋁製梯型線架 300mm 工資', 'model' => 'AL-300', 'price' => 300, 'unit' => 'M'),
        array('name' => '鋁製梯型線架 400mm 工資', 'model' => 'AL-400', 'price' => 350, 'unit' => 'M'),
        array('name' => '鋁製梯型線架 500mm 工資', 'model' => 'AL-500', 'price' => 450, 'unit' => 'M'),
        array('name' => '鋁製梯型線架 600mm 工資', 'model' => 'AL-600', 'price' => 550, 'unit' => 'M'),
    ),
    '線架-密閉式' => array(
        array('name' => '密閉式線槽 100mm 工資', 'model' => 'CL-100', 'price' => 200, 'unit' => 'M'),
        array('name' => '密閉式線槽 200mm 工資', 'model' => 'CL-200', 'price' => 250, 'unit' => 'M'),
        array('name' => '密閉式線槽 300mm 工資', 'model' => 'CL-300', 'price' => 350, 'unit' => 'M'),
        array('name' => '密閉式線槽 400mm 工資', 'model' => 'CL-400', 'price' => 420, 'unit' => 'M'),
        array('name' => '密閉式線槽 500mm 工資', 'model' => 'CL-500', 'price' => 480, 'unit' => 'M'),
        array('name' => '密閉式線槽 600mm 工資', 'model' => 'CL-600', 'price' => 580, 'unit' => 'M'),
    ),
    '線架吊步' => array(
        array('name' => '線架吊步 100mm 一般 (工資)', 'model' => 'HS-100', 'price' => 200, 'unit' => '步'),
        array('name' => '線架吊步 200mm 一般 (工資)', 'model' => 'HS-200', 'price' => 200, 'unit' => '步'),
        array('name' => '線架吊步 300mm 一般 (工資)', 'model' => 'HS-300', 'price' => 200, 'unit' => '步'),
        array('name' => '線架吊步 400mm 一般 (工資)', 'model' => 'HS-400', 'price' => 200, 'unit' => '步'),
        array('name' => '線架吊步 500mm 一般 (工資)', 'model' => 'HS-500', 'price' => 250, 'unit' => '步'),
        array('name' => '線架吊步 600mm 一般 (工資)', 'model' => 'HS-600', 'price' => 300, 'unit' => '步'),
        array('name' => '線架吊步 100mm 白鐵 (工資)', 'model' => 'HSS-100', 'price' => 200, 'unit' => '步'),
        array('name' => '線架吊步 200mm 白鐵 (工資)', 'model' => 'HSS-200', 'price' => 200, 'unit' => '步'),
        array('name' => '線架吊步 300mm 白鐵 (工資)', 'model' => 'HSS-300', 'price' => 200, 'unit' => '步'),
        array('name' => '線架吊步 400mm 白鐵 (工資)', 'model' => 'HSS-400', 'price' => 200, 'unit' => '步'),
        array('name' => '線架吊步 500mm 白鐵 (工資)', 'model' => 'HSS-500', 'price' => 250, 'unit' => '步'),
        array('name' => '線架吊步 600mm 白鐵 (工資)', 'model' => 'HSS-600', 'price' => 300, 'unit' => '步'),
    ),
    '管類吊步' => array(
        array('name' => '單管固定 一般 (工資)', 'model' => 'PF-S', 'price' => 50, 'unit' => '組'),
        array('name' => '管排固定 一般 (工資)', 'model' => 'PF-M', 'price' => 200, 'unit' => '組'),
        array('name' => '吊步牙條固定 一般 (工資)', 'model' => 'PF-T', 'price' => 100, 'unit' => '組'),
        array('name' => '吊步管排固定 一般 (工資)', 'model' => 'PF-TM', 'price' => 200, 'unit' => '組'),
        array('name' => '單管固定 白鐵 (工資)', 'model' => 'PFS-S', 'price' => 50, 'unit' => '組'),
        array('name' => '管排固定 白鐵 (工資)', 'model' => 'PFS-M', 'price' => 200, 'unit' => '組'),
        array('name' => '吊步牙條固定 白鐵 (工資)', 'model' => 'PFS-T', 'price' => 100, 'unit' => '組'),
        array('name' => '吊步管排固定 白鐵 (工資)', 'model' => 'PFS-TM', 'price' => 200, 'unit' => '組'),
    ),
);

// 1. 建立主分類「工資單價」
echo "[1] 建立分類...\n";
$mainCatId = null;
$chk = $db->prepare("SELECT id FROM product_categories WHERE name = '工資單價' AND (parent_id IS NULL OR parent_id = 0)");
$chk->execute();
$mainCatId = $chk->fetchColumn();

if (!$mainCatId) {
    if ($execute) {
        $db->prepare("INSERT INTO product_categories (name, parent_id) VALUES ('工資單價', NULL)")->execute();
        $mainCatId = (int)$db->lastInsertId();
        echo "  [NEW] 主分類「工資單價」 id={$mainCatId}\n";
    } else {
        echo "  [PREVIEW] 將建立主分類「工資單價」\n";
        $mainCatId = 0;
    }
} else {
    echo "  [EXISTS] 主分類「工資單價」 id={$mainCatId}\n";
}

// 2. 建立子分類並匯入產品
$totalProducts = 0;
$newProducts = 0;
$skipProducts = 0;

foreach ($laborData as $subCatName => $items) {
    // 子分類
    $subCatId = null;
    if ($mainCatId) {
        $chk = $db->prepare("SELECT id FROM product_categories WHERE name = ? AND parent_id = ?");
        $chk->execute(array($subCatName, $mainCatId));
        $subCatId = $chk->fetchColumn();
    }
    if (!$subCatId) {
        if ($execute && $mainCatId) {
            $db->prepare("INSERT INTO product_categories (name, parent_id) VALUES (?, ?)")->execute(array($subCatName, $mainCatId));
            $subCatId = (int)$db->lastInsertId();
            echo "  [NEW] 子分類「{$subCatName}」 id={$subCatId}\n";
        } else {
            echo "  [PREVIEW] 將建立子分類「{$subCatName}」\n";
        }
    } else {
        echo "  [EXISTS] 子分類「{$subCatName}」 id={$subCatId}\n";
    }

    // 匯入產品
    foreach ($items as $item) {
        $totalProducts++;
        // 檢查是否已存在
        $exists = false;
        if ($subCatId) {
            $chk = $db->prepare("SELECT id FROM products WHERE name = ? AND category_id = ?");
            $chk->execute(array($item['name'], $subCatId));
            $exists = $chk->fetchColumn();
        }
        if ($exists) {
            $skipProducts++;
            continue;
        }

        echo "    + {$item['name']} ({$item['model']}) \${$item['price']}/{$item['unit']}\n";
        if ($execute && $subCatId) {
            $db->prepare("INSERT INTO products (name, model, price, unit, category_id, is_active, created_at) VALUES (?, ?, ?, ?, ?, 1, NOW())")
               ->execute(array($item['name'], $item['model'], $item['price'], $item['unit'], $subCatId));
            $newProducts++;
        }
    }
}

echo "\n=== 完成 ===\n";
echo "總品項: {$totalProducts}\n";
echo "新增: {$newProducts}\n";
echo "已存在跳過: {$skipProducts}\n";

if (!$execute) {
    echo "\n→ 確認無誤後加 ?execute=1 執行\n";
}
echo '</pre>';
