<?php
/**
 * Ragic → hswork 庫存同步
 * 規則同案件同步：新增/更新/刪除，系統專有欄位不覆蓋
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!in_array(Session::getUser()['role'], array('boss', 'vice_president'))) {
    die('需要管理員權限');
}

set_time_limit(600);
ini_set('memory_limit', '512M');
header('Content-Type: text/html; charset=utf-8');
echo '<h2>Ragic 庫存同步</h2><pre>';
ob_flush(); flush();

$db = Database::getInstance();
$dryRun = !isset($_GET['execute']);

if ($dryRun) {
    echo "【預覽模式】加 ?execute=1 執行同步。\n\n";
} else {
    echo "【執行模式】同步中...\n\n";
}

// ===== 1. 載入 Ragic JSON =====
$jsonFile = __DIR__ . '/../database/ragic_inventory_20260405.json';
if (!file_exists($jsonFile)) die('找不到 JSON: ' . $jsonFile);
$ragicData = json_decode(file_get_contents($jsonFile), true);
echo "Ragic 庫存: " . count($ragicData) . " 筆商品\n";

// ===== 2. 建立商品映射 =====
$productMap = array(); // model/vendor_model → product_id
$productById = array();
$stmt = $db->query("SELECT id, model, source_id, name FROM products");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $productById[$row['id']] = $row;
    if ($row['model']) $productMap[strtoupper(trim($row['model']))] = (int)$row['id'];
    if ($row['source_id']) $productMap[strtoupper(trim($row['source_id']))] = (int)$row['id'];
}
echo "系統商品: " . count($productById) . " 筆\n";

// 分公司映射（Ragic 欄位前綴 → branch_id）
$branchPrefixes = array(
    '潭子' => 1,
    '員林' => 3,
    '清水' => 2,
    '東區電子鎖' => 4,
    '清水電子鎖' => 5,
);

// 現有庫存
$existingInv = array(); // "product_id-branch_id" → row
$stmt = $db->query("SELECT * FROM inventory");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $key = $row['product_id'] . '-' . $row['branch_id'];
    $existingInv[$key] = $row;
}
echo "系統庫存: " . count($existingInv) . " 筆\n\n";

$parseNum = function($v) {
    if (!$v || !trim($v)) return 0;
    $v = str_replace(',', '', trim($v));
    return is_numeric($v) ? (int)round((float)$v) : 0;
};

$insertCount = 0;
$updateCount = 0;
$skipCount = 0;
$deleteCount = 0;
$productUpdateCount = 0;
$noMatchCount = 0;
$errorCount = 0;
$ragicInvKeys = array(); // track which keys exist in Ragic

foreach ($ragicData as $ragicId => $r) {
    $model = trim($r['商品型號'] ?? '');
    $productKey = trim($r['商品KEY'] ?? '');
    $productName = trim($r['商品名稱'] ?? '');

    // 查找對應商品
    $productId = null;
    if ($model && isset($productMap[strtoupper($model)])) {
        $productId = $productMap[strtoupper($model)];
    } elseif ($productKey && isset($productMap[strtoupper($productKey)])) {
        $productId = $productMap[strtoupper($productKey)];
    }

    if (!$productId) {
        $noMatchCount++;
        if ($noMatchCount <= 10) {
            echo "⚠ 找不到商品: {$productKey} / {$model} / {$productName}\n";
        }
        continue;
    }

    // === 更新商品基本資料 ===
    $cost = $parseNum($r['成本'] ?? '');
    $price = $parseNum($r['售價'] ?? '');
    $unit = trim($r['單位'] ?? '');
    if (!$dryRun && ($cost || $price || $unit)) {
        $updates = array();
        $params = array();
        if ($cost) { $updates[] = 'cost = ?'; $params[] = $cost; }
        if ($price) { $updates[] = 'price = ?'; $params[] = $price; }
        if ($unit) { $updates[] = 'unit = ?'; $params[] = $unit; }
        if ($updates) {
            $params[] = $productId;
            $db->prepare("UPDATE products SET " . implode(', ', $updates) . " WHERE id = ?")->execute($params);
            $productUpdateCount++;
        }
    }

    // === 各倉庫庫存 ===
    foreach ($branchPrefixes as $prefix => $branchId) {
        $stockQty = $parseNum($r[$prefix . '庫存'] ?? '');
        $availableQty = $parseNum($r[$prefix . '可用'] ?? '');
        $reservedQty = $parseNum($r[$prefix . '已備貨'] ?? '');
        $borrowedQty = $parseNum($r[$prefix . '借出'] ?? '');
        $displayQty = $parseNum($r[$prefix . '展示'] ?? '');

        // 全部為 0 且系統也沒有 → 跳過
        $hasData = ($stockQty || $availableQty || $reservedQty || $borrowedQty || $displayQty);

        $invKey = $productId . '-' . $branchId;
        $ragicInvKeys[$invKey] = true;

        if (isset($existingInv[$invKey])) {
            // 更新
            $cur = $existingInv[$invKey];
            $changed = false;
            if ((int)$cur['stock_qty'] != $stockQty) $changed = true;
            if ((int)$cur['available_qty'] != $availableQty) $changed = true;
            if ((int)$cur['reserved_qty'] != $reservedQty) $changed = true;
            if ((int)$cur['borrowed_qty'] != $borrowedQty) $changed = true;
            if ((int)$cur['display_qty'] != $displayQty) $changed = true;

            if ($changed) {
                if (!$dryRun) {
                    $db->prepare("UPDATE inventory SET stock_qty=?, available_qty=?, reserved_qty=?, borrowed_qty=?, display_qty=? WHERE id=?")
                        ->execute(array($stockQty, $availableQty, $reservedQty, $borrowedQty, $displayQty, $cur['id']));
                }
                $updateCount++;
            } else {
                $skipCount++;
            }
        } else {
            // 新增（只有有數量的才新增）
            if ($hasData) {
                if (!$dryRun) {
                    try {
                        $db->prepare("INSERT INTO inventory (product_id, branch_id, stock_qty, available_qty, reserved_qty, borrowed_qty, display_qty) VALUES (?, ?, ?, ?, ?, ?, ?)")
                            ->execute(array($productId, $branchId, $stockQty, $availableQty, $reservedQty, $borrowedQty, $displayQty));
                    } catch (Exception $e) {
                        $errorCount++;
                        echo "❌ 新增失敗 product:{$productId} branch:{$branchId}: " . $e->getMessage() . "\n";
                        continue;
                    }
                }
                $insertCount++;
            }
        }
    }
}

// === 刪除（Ragic 沒有但系統有的）===
foreach ($existingInv as $key => $row) {
    if (!isset($ragicInvKeys[$key])) {
        $deleteCount++;
        if (!$dryRun) {
            $db->prepare("DELETE FROM inventory WHERE id = ?")->execute(array($row['id']));
        }
    }
}

if ($noMatchCount > 10) {
    echo "... 還有 " . ($noMatchCount - 10) . " 筆商品找不到\n";
}

echo "\n===== 同步結果 =====\n";
echo "庫存新增: {$insertCount} 筆\n";
echo "庫存更新: {$updateCount} 筆\n";
echo "庫存無變更: {$skipCount} 筆\n";
echo "庫存刪除: {$deleteCount} 筆\n";
echo "商品資料更新: {$productUpdateCount} 筆\n";
echo "找不到商品: {$noMatchCount} 筆\n";
echo "錯誤: {$errorCount} 筆\n";

if ($dryRun) {
    echo "\n<a href='?execute=1' style='font-size:1.2em;color:red'>⚠ 點此執行同步</a>\n";
}
echo '</pre>';
