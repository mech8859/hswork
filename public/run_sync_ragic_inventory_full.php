<?php
/**
 * Ragic 庫存完整同步（含自動新增缺少商品）
 * Step 1: 找出 Ragic 有但系統沒有的商品 → 自動新增到 products
 * Step 2: 同步庫存數量
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!in_array(Session::getUser()['role'], array('boss', 'vice_president'))) {
    die('需要管理員權限');
}

set_time_limit(600);
ini_set('memory_limit', '512M');
header('Content-Type: text/html; charset=utf-8');
echo '<h2>Ragic 庫存完整同步（含新增商品）</h2><pre>';
ob_flush(); flush();

$db = Database::getInstance();
$dryRun = !isset($_GET['execute']);

if ($dryRun) {
    echo "【預覽模式】加 ?execute=1 執行。\n\n";
} else {
    echo "【執行模式】\n\n";
}

// ===== 載入 Ragic =====
$jsonFile = __DIR__ . '/../database/ragic_inventory_20260405.json';
if (!file_exists($jsonFile)) die('找不到 JSON');
$ragicData = json_decode(file_get_contents($jsonFile), true);
echo "Ragic: " . count($ragicData) . " 筆商品\n";

// ===== 建立商品映射 =====
function buildProductMap($db) {
    $map = array();
    $stmt = $db->query("SELECT id, model, source_id, name FROM products");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['model']) $map[strtoupper(trim($row['model']))] = (int)$row['id'];
        if ($row['source_id']) $map[strtoupper(trim($row['source_id']))] = (int)$row['id'];
    }
    return $map;
}

// ===== 分類映射 =====
$categoryMap = array();
$stmt = $db->query("SELECT id, name FROM product_categories");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $categoryMap[$row['name']] = (int)$row['id'];
}

$productMap = buildProductMap($db);
echo "系統商品映射: " . count($productMap) . " 筆\n";

// ===== Step 1: 新增缺少的商品 =====
echo "\n--- Step 1: 新增缺少商品 ---\n";
$newProductCount = 0;

foreach ($ragicData as $ragicId => $r) {
    $model = trim($r['商品型號'] ?? '');
    $productKey = trim($r['商品KEY'] ?? '');
    $productName = trim($r['商品名稱'] ?? '');

    // 已有 → 跳過
    if ($model && isset($productMap[strtoupper($model)])) continue;
    if ($productKey && isset($productMap[strtoupper($productKey)])) continue;

    // 需要新增
    $unit = trim($r['單位'] ?? '') ?: '個';
    $cost = str_replace(',', '', trim($r['成本'] ?? ''));
    $price = str_replace(',', '', trim($r['售價'] ?? ''));
    $category = trim($r['類別'] ?? '');
    $categoryId = isset($categoryMap[$category]) ? $categoryMap[$category] : null;

    if (!$dryRun) {
        try {
            $db->prepare("INSERT INTO products (name, model, source_id, unit, cost, price, category_id, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)")
                ->execute(array(
                    $productName ?: $model ?: $productKey,
                    $model ?: null,
                    $productKey ?: null,
                    $unit,
                    is_numeric($cost) ? (float)$cost : 0,
                    is_numeric($price) ? (float)$price : 0,
                    $categoryId,
                ));
            $newId = (int)$db->lastInsertId();
            // 加入映射
            if ($model) $productMap[strtoupper($model)] = $newId;
            if ($productKey) $productMap[strtoupper($productKey)] = $newId;
            echo "新增商品: {$productKey} / {$model} / {$productName} → ID:{$newId}\n";
        } catch (Exception $e) {
            echo "❌ 新增失敗: {$productKey}: " . $e->getMessage() . "\n";
        }
    } else {
        echo "將新增: {$productKey} / {$model} / {$productName}\n";
    }
    $newProductCount++;
}
echo "商品新增: {$newProductCount} 筆\n";

// Rebuild map after inserts
if (!$dryRun && $newProductCount > 0) {
    $productMap = buildProductMap($db);
    echo "映射重建: " . count($productMap) . " 筆\n";
}

// ===== Step 2: 同步庫存 =====
echo "\n--- Step 2: 同步庫存 ---\n";
flush();

$branchPrefixes = array(
    '潭子' => 1,
    '員林' => 3,
    '清水' => 2,
    '東區電子鎖' => 4,
    '清水電子鎖' => 5,
);

$existingInv = array();
$stmt = $db->query("SELECT * FROM inventory");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $existingInv[$row['product_id'] . '-' . $row['branch_id']] = $row;
}

$parseNum = function($v) {
    if (!$v || !trim($v)) return 0;
    return is_numeric(str_replace(',', '', trim($v))) ? (int)round((float)str_replace(',', '', trim($v))) : 0;
};

$insertCount = 0;
$updateCount = 0;
$skipCount = 0;
$deleteCount = 0;
$productUpdateCount = 0;
$noMatchCount = 0;
$errorCount = 0;
$ragicInvKeys = array();

foreach ($ragicData as $ragicId => $r) {
    $model = trim($r['商品型號'] ?? '');
    $productKey = trim($r['商品KEY'] ?? '');

    $productId = null;
    if ($model && isset($productMap[strtoupper($model)])) $productId = $productMap[strtoupper($model)];
    elseif ($productKey && isset($productMap[strtoupper($productKey)])) $productId = $productMap[strtoupper($productKey)];

    if (!$productId) { $noMatchCount++; continue; }

    // 更新商品成本/售價
    $cost = $parseNum($r['成本'] ?? '');
    $price = $parseNum($r['售價'] ?? '');
    $unit = trim($r['單位'] ?? '');
    if (!$dryRun && ($cost || $price || $unit)) {
        $ups = array();
        $ps = array();
        if ($cost) { $ups[] = 'cost=?'; $ps[] = $cost; }
        if ($price) { $ups[] = 'price=?'; $ps[] = $price; }
        if ($unit) { $ups[] = 'unit=?'; $ps[] = $unit; }
        $ps[] = $productId;
        $db->prepare("UPDATE products SET " . implode(',', $ups) . " WHERE id=?")->execute($ps);
        $productUpdateCount++;
    }

    // 各倉庫
    foreach ($branchPrefixes as $prefix => $branchId) {
        $stockQty = $parseNum($r[$prefix . '庫存'] ?? '');
        $availableQty = $parseNum($r[$prefix . '可用'] ?? '');
        $reservedQty = $parseNum($r[$prefix . '已備貨'] ?? '');
        $borrowedQty = $parseNum($r[$prefix . '借出'] ?? '');
        $displayQty = $parseNum($r[$prefix . '展示'] ?? '');
        $hasData = ($stockQty || $availableQty || $reservedQty || $borrowedQty || $displayQty);

        $invKey = $productId . '-' . $branchId;
        $ragicInvKeys[$invKey] = true;

        if (isset($existingInv[$invKey])) {
            $cur = $existingInv[$invKey];
            if ((int)$cur['stock_qty'] != $stockQty || (int)$cur['available_qty'] != $availableQty ||
                (int)$cur['reserved_qty'] != $reservedQty || (int)$cur['borrowed_qty'] != $borrowedQty ||
                (int)$cur['display_qty'] != $displayQty) {
                if (!$dryRun) {
                    $db->prepare("UPDATE inventory SET stock_qty=?,available_qty=?,reserved_qty=?,borrowed_qty=?,display_qty=? WHERE id=?")
                        ->execute(array($stockQty, $availableQty, $reservedQty, $borrowedQty, $displayQty, $cur['id']));
                }
                $updateCount++;
            } else {
                $skipCount++;
            }
        } elseif ($hasData) {
            if (!$dryRun) {
                try {
                    $db->prepare("INSERT INTO inventory (product_id, branch_id, stock_qty, available_qty, reserved_qty, borrowed_qty, display_qty) VALUES (?,?,?,?,?,?,?)")
                        ->execute(array($productId, $branchId, $stockQty, $availableQty, $reservedQty, $borrowedQty, $displayQty));
                } catch (Exception $e) { $errorCount++; }
            }
            $insertCount++;
        }
    }
}

// 刪除
foreach ($existingInv as $key => $row) {
    if (!isset($ragicInvKeys[$key])) {
        $deleteCount++;
        if (!$dryRun) {
            $db->prepare("DELETE FROM inventory WHERE id=?")->execute(array($row['id']));
        }
    }
}

echo "\n===== 同步結果 =====\n";
echo "商品新增: {$newProductCount} 筆\n";
echo "商品資料更新: {$productUpdateCount} 筆\n";
echo "庫存新增: {$insertCount} 筆\n";
echo "庫存更新: {$updateCount} 筆\n";
echo "庫存無變更: {$skipCount} 筆\n";
echo "庫存刪除: {$deleteCount} 筆\n";
echo "仍找不到商品: {$noMatchCount} 筆\n";
echo "錯誤: {$errorCount} 筆\n";

if ($dryRun) {
    echo "\n<a href='?execute=1' style='font-size:1.2em;color:red'>⚠ 點此執行同步</a>\n";
}
echo '</pre>';
