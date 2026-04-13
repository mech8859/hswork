<?php
/**
 * 修正庫存記錄 warehouse_id 為空的問題
 * Ragic 同步只寫了 branch_id，沒寫 warehouse_id
 *
 * 預覽: temp_fix_inventory_wh.php
 * 執行: temp_fix_inventory_wh.php?run=1
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../includes/bootstrap.php';
$db = Database::getInstance();

$mode = isset($_GET['run']) ? 'execute' : 'preview';
echo "=== 庫存倉庫修正 ===\n";
echo "模式: {$mode}\n\n";

// branch → warehouse 對應
$branchToWarehouse = array();
$mapping = $db->query("SELECT id, branch_id, name FROM warehouses ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
echo "=== 倉庫對應 ===\n";
foreach ($mapping as $w) {
    $branchToWarehouse[$w['branch_id']] = $w['id'];
    echo "branch#{$w['branch_id']} → warehouse#{$w['id']} ({$w['name']})\n";
}

// 取所有空 warehouse_id 且有庫存的記錄
$emptyRows = $db->query("
    SELECT i.id, i.product_id, i.branch_id, i.warehouse_id,
           i.stock_qty, i.available_qty, i.reserved_qty, i.borrowed_qty, i.prepared_qty, i.display_qty, i.min_qty,
           p.name AS product_name, p.model
    FROM inventory i
    LEFT JOIN products p ON i.product_id = p.id
    WHERE (i.warehouse_id IS NULL OR i.warehouse_id = 0)
    ORDER BY i.branch_id, i.id
")->fetchAll(PDO::FETCH_ASSOC);

echo "\n空 warehouse_id 的庫存: " . count($emptyRows) . " 筆\n\n";

$updateCount = 0;
$mergeCount = 0;
$skipCount = 0;
$deleteIds = array();

echo "=== 處理明細 ===\n";
foreach ($emptyRows as $row) {
    $brId = $row['branch_id'];
    $whId = isset($branchToWarehouse[$brId]) ? $branchToWarehouse[$brId] : null;

    if (!$whId) {
        echo "SKIP inv#{$row['id']} | branch#{$brId} 無對應倉庫 | {$row['product_name']}\n";
        $skipCount++;
        continue;
    }

    // 檢查是否有重複（同 product_id + 正確 warehouse_id 已存在）
    $existing = $db->prepare("
        SELECT id, stock_qty, available_qty, reserved_qty, borrowed_qty, prepared_qty, display_qty, min_qty
        FROM inventory
        WHERE product_id = ? AND warehouse_id = ? AND id != ?
        LIMIT 1
    ");
    $existing->execute(array($row['product_id'], $whId, $row['id']));
    $dup = $existing->fetch(PDO::FETCH_ASSOC);

    if ($dup) {
        // 重複：合併數量到已有記錄，刪除空記錄
        $newStock     = (float)$dup['stock_qty'] + (float)$row['stock_qty'];
        $newAvail     = (float)$dup['available_qty'] + (float)$row['available_qty'];
        $newReserved  = (float)$dup['reserved_qty'] + (float)$row['reserved_qty'];
        $newBorrowed  = (float)$dup['borrowed_qty'] + (float)$row['borrowed_qty'];
        $newPrepared  = (float)$dup['prepared_qty'] + (float)$row['prepared_qty'];
        $newDisplay   = (float)$dup['display_qty'] + (float)$row['display_qty'];
        $newMin       = max((float)$dup['min_qty'], (float)$row['min_qty']);

        echo "MERGE inv#{$row['id']}(qty:{$row['stock_qty']}) → inv#{$dup['id']}(qty:{$dup['stock_qty']}) = {$newStock} | wh#{$whId} | {$row['product_name']} ({$row['model']})\n";

        if ($mode === 'execute') {
            $db->prepare("UPDATE inventory SET stock_qty=?, available_qty=?, reserved_qty=?, borrowed_qty=?, prepared_qty=?, display_qty=?, min_qty=? WHERE id=?")
                ->execute(array($newStock, $newAvail, $newReserved, $newBorrowed, $newPrepared, $newDisplay, $newMin, $dup['id']));
            $deleteIds[] = $row['id'];
        }
        $mergeCount++;
    } else {
        // 無重複：直接補 warehouse_id
        echo "UPDATE inv#{$row['id']} → wh#{$whId} | qty:{$row['stock_qty']} | {$row['product_name']} ({$row['model']})\n";

        if ($mode === 'execute') {
            $db->prepare("UPDATE inventory SET warehouse_id = ? WHERE id = ?")->execute(array($whId, $row['id']));
        }
        $updateCount++;
    }
}

// 刪除已合併的空記錄
if ($mode === 'execute' && !empty($deleteIds)) {
    $ph = implode(',', array_fill(0, count($deleteIds), '?'));
    // 先移轉 inventory_transactions 的關聯
    foreach ($deleteIds as $delId) {
        // 查出被刪記錄的 product_id 和 warehouse_id（合併目標）
        $delRow = $db->prepare("SELECT product_id, branch_id FROM inventory WHERE id = ?")->fetch(PDO::FETCH_ASSOC);
    }
    $db->prepare("DELETE FROM inventory WHERE id IN ({$ph})")->execute($deleteIds);
}

echo "\n=== 統計 ===\n";
echo "直接補倉庫: {$updateCount} 筆\n";
echo "合併(重複): {$mergeCount} 筆\n";
echo "跳過(無對應): {$skipCount} 筆\n";
echo "總處理: " . ($updateCount + $mergeCount + $skipCount) . " 筆\n";

if ($mode === 'execute') {
    // 驗證
    $remainEmpty = $db->query("SELECT COUNT(*) FROM inventory WHERE (warehouse_id IS NULL OR warehouse_id = 0)")->fetchColumn();
    echo "\n=== 驗證 ===\n";
    echo "剩餘空 warehouse_id: {$remainEmpty} 筆\n";
} else {
    echo "\n預覽模式，加 ?run=1 執行修正\n";
}

echo "\n完成！\n";
