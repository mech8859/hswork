<?php
/**
 * Ragic → hswork 產品目錄同步
 * Ragic 有系統沒有 → 新增
 * Ragic 有系統有 → 比對更新（以 Ragic 為準）
 * Ragic 沒有系統有 → 刪除（但圖片檔案保留）
 * 系統專有欄位不覆蓋：gallery, datasheet, stock, retail_price, labor_cost
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!in_array(Session::getUser()['role'], array('boss', 'vice_president'))) {
    die('需要管理員權限');
}

set_time_limit(600);
ini_set('memory_limit', '512M');
header('Content-Type: text/html; charset=utf-8');
echo '<h2>Ragic 產品目錄同步</h2><pre>';
ob_flush(); flush();

$db = Database::getInstance();
$dryRun = !isset($_GET['execute']);

if ($dryRun) {
    echo "【預覽模式】加 ?execute=1 執行。\n\n";
} else {
    echo "【執行模式】\n\n";
}

// ===== 載入 Ragic =====
$jsonFile = __DIR__ . '/../database/ragic_products_20260405.json';
if (!file_exists($jsonFile)) die('找不到 JSON');
$ragicData = json_decode(file_get_contents($jsonFile), true);
echo "Ragic 產品: " . count($ragicData) . " 筆\n";

// ===== 載入系統產品 =====
$existingProducts = array(); // model → row, source_id → row
$existingById = array();
$stmt = $db->query("SELECT * FROM products");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $existingById[$row['id']] = $row;
    if ($row['model']) $existingProducts['model:' . strtoupper(trim($row['model']))] = $row;
    if ($row['source_id']) $existingProducts['src:' . strtoupper(trim($row['source_id']))] = $row;
}
echo "系統產品: " . count($existingById) . " 筆\n";

// ===== 分類映射 =====
$categoryMap = array();
$stmt = $db->query("SELECT id, name FROM product_categories");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $categoryMap[$row['name']] = (int)$row['id'];
}

// ===== 同步 =====
$insertCount = 0;
$updateCount = 0;
$skipCount = 0;
$deleteCount = 0;
$errorCount = 0;
$ragicProductIds = array(); // track system IDs that exist in Ragic

$parseNum = function($v) {
    if (!$v || !trim($v)) return 0;
    $v = str_replace(',', '', trim($v));
    return is_numeric($v) ? round((float)$v, 2) : 0;
};

// 可同步的欄位 (Ragic欄位 → hswork欄位)
$fieldMap = array(
    '商品名稱' => 'name',
    '商品型號' => 'model',
    '品牌'     => 'brand',
    '單位'     => 'unit',
    '成本'     => 'cost',
    '建議售價' => 'price',
    '規格'     => 'specifications',
    '備註'     => 'description',
    '分類'     => '_classification',
    '類別'     => '_category',
    '商品狀態' => '_status',
);

foreach ($ragicData as $ragicId => $r) {
    $productCode = trim($r['商品編號'] ?? '');
    $model = trim($r['商品型號'] ?? '');
    $name = trim($r['商品名稱'] ?? '');

    if (!$productCode && !$model && !$name) continue;

    // 查找系統中是否已有
    $existing = null;
    if ($model && isset($existingProducts['model:' . strtoupper($model)])) {
        $existing = $existingProducts['model:' . strtoupper($model)];
    } elseif ($productCode && isset($existingProducts['src:' . strtoupper($productCode)])) {
        $existing = $existingProducts['src:' . strtoupper($productCode)];
    }

    // 分類 — Ragic類別 → 系統分類ID 對照表
    $ragicCategoryMap = array(
        'HDMI'          => 3,   // HDMI VGA配件
        'PDU'           => 86,  // 機櫃
        'UPS'           => 1,   // CyberPower UPS不斷電系統
        'VGA'           => 3,   // HDMI VGA配件
        '不斷電系統'     => 1,   // CyberPower UPS不斷電系統
        '中央監控'       => 99,  // 監控系統
        '五金另料'       => 176, // 五金配件
        '保險櫃'        => 2,   // EASY KEY 保險箱
        '儲存'          => 123, // 監控專用硬碟
        '光纖'          => 186, // 光纖-線材&配件 專區
        '動力箱/防水盒'  => 176, // 五金配件
        '商用音響'       => 313, // 音響喇叭設備
        '壓條/線槽'      => 176, // 五金配件
        '太陽能'        => 303, // 電源
        '工作項次'       => 327, // 工程項次
        '工具/設備'      => 352, // 施工工具/設備
        '感應/開門按鈕'  => 300, // 開門按鈕
        '授權'          => 275, // 門禁系統
        '支架/立柱'      => 310, // 電視吊架
        '文具用品'       => 176, // 五金配件
        '智能家居'       => 18,  // Panasonic IoT智慧開關(WiFi)
        '機櫃'          => 86,  // 機櫃
        '燈具'          => 353, // 照明設備
        '監控'          => 99,  // 監控系統
        '立柱/支架'      => 310, // 電視吊架
        '紅外線'        => 260, // 防盜器材
        '網通'          => 145, // 網通設備
        '緊急系統'       => 254, // 自保防盜設備
        '線材'          => 175, // 線材&相關配件
        '資訊插座'       => 214, // 網路配件
        '車道系統'       => 83,  // 車道系列
        '配件'          => 176, // 五金配件
        '門禁'          => 275, // 門禁系統
        '電子鎖'        => 296, // 電鎖及配件
        '電源'          => 303, // 電源
        '電腦.視聽'      => 47,  // 投影機、配件
        '電視/螢幕'      => 97,  // 液晶螢幕
        '電話總機'       => 230, // 總機系統
        '電風扇'        => 176, // 五金配件
        '高空作業車'     => 327, // 工程項次
        '麥克風'        => 324, // 麥克風設備
    );
    $category = trim($r['類別'] ?? '');
    $categoryId = isset($ragicCategoryMap[$category]) ? $ragicCategoryMap[$category] : null;

    // 狀態
    $status = trim($r['商品狀態'] ?? '正常');
    $isActive = ($status === '停用' || $status === '停售') ? 0 : 1;

    if ($existing) {
        // === 更新 ===
        $ragicProductIds[$existing['id']] = true;

        if (!$dryRun) {
            $updates = array();
            $params = array();

            // 名稱
            $newName = $name ?: $existing['name'];
            if ($newName && $newName !== $existing['name']) {
                $updates[] = 'name = ?'; $params[] = $newName;
            }
            // 型號
            if ($model && $model !== ($existing['model'] ?? '')) {
                $updates[] = 'model = ?'; $params[] = $model;
            }
            // source_id
            if ($productCode && $productCode !== ($existing['source_id'] ?? '')) {
                $updates[] = 'source_id = ?'; $params[] = $productCode;
            }
            // 品牌
            $brand = trim($r['品牌'] ?? '');
            if ($brand && $brand !== ($existing['brand'] ?? '')) {
                $updates[] = 'brand = ?'; $params[] = $brand;
            }
            // 單位
            $unit = trim($r['單位'] ?? '');
            if ($unit && $unit !== ($existing['unit'] ?? '')) {
                $updates[] = 'unit = ?'; $params[] = $unit;
            }
            // 成本
            $cost = $parseNum($r['成本'] ?? '');
            if ($cost && $cost != (float)($existing['cost'] ?? 0)) {
                $updates[] = 'cost = ?'; $params[] = $cost;
            }
            // 售價
            $price = $parseNum($r['建議售價'] ?? '');
            if ($price && $price != (float)($existing['price'] ?? 0)) {
                $updates[] = 'price = ?'; $params[] = $price;
            }
            // 規格
            $specs = trim($r['規格'] ?? '');
            if ($specs && $specs !== ($existing['specifications'] ?? '')) {
                $updates[] = 'specifications = ?'; $params[] = $specs;
            }
            // 分類 — 已有分類的不動，只補空的
            if ($categoryId && empty($existing['category_id'])) {
                $updates[] = 'category_id = ?'; $params[] = $categoryId;
            }
            // 狀態
            if ($isActive != (int)($existing['is_active'] ?? 1)) {
                $updates[] = 'is_active = ?'; $params[] = $isActive;
            }

            if (!empty($updates)) {
                $params[] = $existing['id'];
                try {
                    $db->prepare("UPDATE products SET " . implode(', ', $updates) . " WHERE id = ?")->execute($params);
                    $updateCount++;
                } catch (Exception $e) {
                    $errorCount++;
                    echo "❌ 更新失敗 {$productCode}: " . $e->getMessage() . "\n";
                }
            } else {
                $skipCount++;
            }
        } else {
            $ragicProductIds[$existing['id']] = true;
            $updateCount++;
        }
    } else {
        // === 新增 ===
        if (!$dryRun) {
            try {
                $db->prepare("INSERT INTO products (source_id, name, model, brand, unit, cost, price, specifications, category_id, is_active) VALUES (?,?,?,?,?,?,?,?,?,?)")
                    ->execute(array(
                        $productCode ?: null,
                        $name ?: $model ?: $productCode,
                        $model ?: null,
                        trim($r['品牌'] ?? '') ?: null,
                        trim($r['單位'] ?? '') ?: '個',
                        $parseNum($r['成本'] ?? ''),
                        $parseNum($r['建議售價'] ?? ''),
                        trim($r['規格'] ?? '') ?: null,
                        $categoryId,
                        $isActive,
                    ));
                $newId = (int)$db->lastInsertId();
                $ragicProductIds[$newId] = true;
                echo "新增 {$productCode} / {$model} / {$name} → ID:{$newId}\n";
            } catch (Exception $e) {
                $errorCount++;
                echo "❌ 新增失敗 {$productCode}: " . $e->getMessage() . "\n";
            }
        } else {
            echo "將新增: {$productCode} / {$model} / {$name}\n";
        }
        $insertCount++;
    }
}

// === 刪除（Ragic 沒有但系統有的）===
// 注意：只刪除 DB 記錄，圖片檔案保留
if (!$dryRun) {
    foreach ($existingById as $id => $row) {
        if (!isset($ragicProductIds[$id])) {
            $deleteCount++;
            // 標記停用而非真刪除（避免影響庫存/報價等關聯）
            $db->prepare("UPDATE products SET is_active = 0 WHERE id = ?")->execute(array($id));
        }
    }
} else {
    foreach ($existingById as $id => $row) {
        if (!isset($ragicProductIds[$id])) {
            $deleteCount++;
            if ($deleteCount <= 10) {
                echo "將停用: ID:{$id} / {$row['model']} / {$row['name']}\n";
            }
        }
    }
    if ($deleteCount > 10) echo "... 還有 " . ($deleteCount - 10) . " 筆將停用\n";
}

echo "\n===== 同步結果 =====\n";
echo "新增: {$insertCount} 筆\n";
echo "更新: {$updateCount} 筆\n";
echo "無變更: {$skipCount} 筆\n";
echo "停用(Ragic無): {$deleteCount} 筆\n";
echo "錯誤: {$errorCount} 筆\n";
echo "Ragic 總筆數: " . count($ragicData) . "\n";
echo "系統原有: " . count($existingById) . " 筆\n";

if ($dryRun) {
    echo "\n<a href='?execute=1' style='font-size:1.2em;color:red'>⚠ 點此執行同步</a>\n";
}
echo '</pre>';
