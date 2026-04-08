<?php
/**
 * 出庫單 shipped_qty migration 預覽腳本
 *
 * 純 SELECT，不寫入任何資料。用於確認 migration 的影響範圍。
 *
 * 使用方式：
 *   https://hswork.com.tw/preview_stock_out_migration.php
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');
header('Content-Type: text/plain; charset=utf-8');
set_time_limit(120);

$db = Database::getInstance();

echo "===========================================\n";
echo "  出庫單 shipped_qty Migration 預覽\n";
echo "  （純 SELECT，不會修改任何資料）\n";
echo "===========================================\n\n";

// ---- 1. 確認 request_qty 欄位是否存在 ----
echo "[1] 檢查 stock_out_items 現有欄位...\n";
$cols = $db->query("SHOW COLUMNS FROM stock_out_items")->fetchAll(PDO::FETCH_ASSOC);
$colNames = array();
foreach ($cols as $c) $colNames[] = $c['Field'];
echo "現有欄位: " . implode(', ', $colNames) . "\n\n";

$hasRequestQty = in_array('request_qty', $colNames);
$hasShippedQty = in_array('shipped_qty', $colNames);

if (!$hasRequestQty) {
    echo "⚠️  警告：stock_out_items 目前沒有 request_qty 欄位！\n";
    echo "   這代表 /www/add_request_qty.php 可能沒執行過，或欄位被刪除。\n\n";
} else {
    echo "✓ request_qty 欄位存在\n";
}
if ($hasShippedQty) {
    echo "⚠️  shipped_qty 欄位已存在（migration 可能已經跑過）\n";
} else {
    echo "✓ shipped_qty 欄位尚未存在（等待 migration 建立）\n";
}
echo "\n";

// ---- 2. 總覽統計 ----
echo "[2] 整體統計\n";
$total = (int)$db->query("SELECT COUNT(*) FROM stock_out_items")->fetchColumn();
echo "  stock_out_items 總筆數: {$total}\n";

$confirmed = (int)$db->query("SELECT COUNT(*) FROM stock_out_items WHERE is_confirmed = 1")->fetchColumn();
$unconfirmed = $total - $confirmed;
echo "  is_confirmed = 1: {$confirmed} 筆\n";
echo "  is_confirmed = 0: {$unconfirmed} 筆\n\n";

if ($hasRequestQty) {
    $reqGt0 = (int)$db->query("SELECT COUNT(*) FROM stock_out_items WHERE request_qty > 0")->fetchColumn();
    $reqEq0 = (int)$db->query("SELECT COUNT(*) FROM stock_out_items WHERE request_qty = 0 OR request_qty IS NULL")->fetchColumn();
    echo "  request_qty > 0: {$reqGt0} 筆  ← Rule 1 會影響\n";
    echo "  request_qty = 0 或 NULL: {$reqEq0} 筆\n\n";
}

// ---- 3. Rule 1 影響統計（Ragic swap） ----
if ($hasRequestQty) {
    echo "===========================================\n";
    echo "  RULE 1: Ragic 資料 swap\n";
    echo "  UPDATE stock_out_items\n";
    echo "  SET shipped_qty = quantity, quantity = request_qty\n";
    echo "  WHERE request_qty > 0\n";
    echo "===========================================\n\n";

    // 分組統計
    $sameCount = (int)$db->query("SELECT COUNT(*) FROM stock_out_items WHERE request_qty > 0 AND quantity = request_qty")->fetchColumn();
    $diffCount = (int)$db->query("SELECT COUNT(*) FROM stock_out_items WHERE request_qty > 0 AND quantity != request_qty")->fetchColumn();
    $shippedLtRequest = (int)$db->query("SELECT COUNT(*) FROM stock_out_items WHERE request_qty > 0 AND quantity < request_qty")->fetchColumn();
    $shippedGtRequest = (int)$db->query("SELECT COUNT(*) FROM stock_out_items WHERE request_qty > 0 AND quantity > request_qty")->fetchColumn();

    echo "受影響總筆數: " . ($sameCount + $diffCount) . "\n";
    echo "  quantity == request_qty (完全出貨): {$sameCount} 筆 → swap 前後值相同，無實質變化\n";
    echo "  quantity < request_qty (部分出貨): {$shippedLtRequest} 筆 → swap 後恢復「需求>已出」的正確語意\n";
    echo "  quantity > request_qty (超量?): {$shippedGtRequest} 筆 → 異常，需要檢查\n\n";

    // 範例 20 筆
    echo "--- 範例前 20 筆 (改前 vs 改後) ---\n";
    $sample = $db->query("
        SELECT soi.id, so.so_number, soi.product_name, soi.model,
               soi.quantity AS old_quantity, soi.request_qty,
               soi.is_confirmed, so.status AS so_status
        FROM stock_out_items soi
        LEFT JOIN stock_outs so ON soi.stock_out_id = so.id
        WHERE soi.request_qty > 0
        ORDER BY soi.id DESC
        LIMIT 20
    ")->fetchAll(PDO::FETCH_ASSOC);

    printf("%-6s %-20s %-25s %-10s %-10s %-12s %-12s %-10s\n",
        'id', 'so_number', 'product_name', 'old_qty', 'req_qty', 'new_qty(=req)', 'shipped(=old)', 'confirmed');
    echo str_repeat('-', 120) . "\n";
    foreach ($sample as $r) {
        printf("%-6s %-20s %-25s %-10s %-10s %-12s %-12s %-10s\n",
            $r['id'],
            substr($r['so_number'] ?: '-', 0, 20),
            mb_substr($r['product_name'] ?: '-', 0, 20),
            $r['old_quantity'],
            $r['request_qty'],
            $r['request_qty'],       // 新的 quantity
            $r['old_quantity'],      // 新的 shipped_qty
            $r['is_confirmed']
        );
    }
    echo "\n";

    // 異常：quantity > request_qty
    if ($shippedGtRequest > 0) {
        echo "⚠️  異常資料：quantity > request_qty（已出 > 需求，可能資料錯誤）\n";
        $abn = $db->query("
            SELECT soi.id, so.so_number, soi.product_name, soi.quantity, soi.request_qty
            FROM stock_out_items soi
            LEFT JOIN stock_outs so ON soi.stock_out_id = so.id
            WHERE soi.request_qty > 0 AND soi.quantity > soi.request_qty
            LIMIT 10
        ")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($abn as $r) {
            echo "  id={$r['id']} {$r['so_number']} {$r['product_name']} quantity={$r['quantity']} request_qty={$r['request_qty']}\n";
        }
        echo "\n";
    }
}

// ---- 4. Rule 2 影響統計 ----
echo "===========================================\n";
echo "  RULE 2: 手動建立已確認資料\n";
echo "  UPDATE stock_out_items\n";
echo "  SET shipped_qty = quantity\n";
echo "  WHERE (request_qty IS NULL OR request_qty = 0)\n";
echo "    AND is_confirmed = 1\n";
echo "  (不改 quantity，只是把 shipped_qty 填上)\n";
echo "===========================================\n\n";

$rule2Where = $hasRequestQty
    ? "(request_qty IS NULL OR request_qty = 0) AND is_confirmed = 1"
    : "is_confirmed = 1";

$rule2Count = (int)$db->query("SELECT COUNT(*) FROM stock_out_items WHERE {$rule2Where}")->fetchColumn();
echo "受影響筆數: {$rule2Count}\n\n";

// 範例 10 筆
echo "--- 範例前 10 筆 ---\n";
$sample2 = $db->query("
    SELECT soi.id, so.so_number, soi.product_name, soi.quantity,
           " . ($hasRequestQty ? "soi.request_qty" : "0 AS request_qty") . ",
           soi.is_confirmed, so.status AS so_status
    FROM stock_out_items soi
    LEFT JOIN stock_outs so ON soi.stock_out_id = so.id
    WHERE {$rule2Where}
    ORDER BY soi.id DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

printf("%-6s %-20s %-25s %-10s %-15s\n", 'id', 'so_number', 'product_name', 'quantity', 'shipped→quantity');
echo str_repeat('-', 90) . "\n";
foreach ($sample2 as $r) {
    printf("%-6s %-20s %-25s %-10s %-15s\n",
        $r['id'],
        substr($r['so_number'] ?: '-', 0, 20),
        mb_substr($r['product_name'] ?: '-', 0, 20),
        $r['quantity'],
        $r['quantity']
    );
}
echo "\n";

// ---- 5. 未受影響的資料 ----
echo "===========================================\n";
echo "  不受 migration 影響的資料\n";
echo "===========================================\n\n";

$untouchedWhere = $hasRequestQty
    ? "(request_qty IS NULL OR request_qty = 0) AND is_confirmed = 0"
    : "is_confirmed = 0";
$untouched = (int)$db->query("SELECT COUNT(*) FROM stock_out_items WHERE {$untouchedWhere}")->fetchColumn();
echo "筆數: {$untouched}（shipped_qty 預設 0，quantity 不動）\n\n";

// ---- 6. 特別檢查您剛建的測試單 ----
echo "===========================================\n";
echo "  特別檢查：您剛建立的測試單\n";
echo "===========================================\n\n";
$testCheck = $db->query("
    SELECT so.id, so.so_number, so.status, so.so_date,
           (SELECT COUNT(*) FROM stock_out_items WHERE stock_out_id = so.id) AS item_count
    FROM stock_outs so
    WHERE so.so_date >= DATE_SUB(CURDATE(), INTERVAL 3 DAY)
    ORDER BY so.id DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($testCheck as $t) {
    echo "SO {$t['so_number']} | {$t['so_date']} | 狀態: {$t['status']} | {$t['item_count']} 項\n";

    $items = $db->query("
        SELECT id, product_name, quantity,
               " . ($hasRequestQty ? "COALESCE(request_qty, 0) AS request_qty" : "0 AS request_qty") . ",
               is_confirmed
        FROM stock_out_items
        WHERE stock_out_id = {$t['id']}
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($items as $it) {
        echo sprintf("  id=%-6s %-25s quantity=%-6s request_qty=%-6s is_confirmed=%s\n",
            $it['id'],
            mb_substr($it['product_name'] ?: '-', 0, 20),
            $it['quantity'],
            $it['request_qty'],
            $it['is_confirmed']
        );
    }
    echo "\n";
}

// ---- 7. 結論 ----
echo "===========================================\n";
echo "  預覽完成（未修改任何資料）\n";
echo "===========================================\n";

if ($hasShippedQty) {
    echo "\n⚠️  shipped_qty 欄位已存在，若要重跑 migration 請先確認。\n";
} else {
    $rule1Count = $hasRequestQty ? (int)$db->query("SELECT COUNT(*) FROM stock_out_items WHERE request_qty > 0")->fetchColumn() : 0;
    echo "\n如果確認執行 migration，將會：\n";
    echo "  1. 新增 shipped_qty 欄位\n";
    echo "  2. Rule 1 更新: {$rule1Count} 筆 Ragic 資料\n";
    echo "  3. Rule 2 更新: {$rule2Count} 筆手動確認資料\n";
    echo "  4. 剩餘 {$untouched} 筆未受影響（shipped_qty=0）\n";
}
echo "\nDone.\n";
