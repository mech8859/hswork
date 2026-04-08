<?php
/**
 * Migration 111: stock_out_items 新增 shipped_qty 欄位 + 歷史資料遷移
 *
 * 目的：修正「部分出貨會把需求量覆寫成出貨量」的 bug
 *
 * 新語意：
 *   - quantity     = 業務需求量（固定，不再被覆寫）
 *   - shipped_qty  = 累計已出貨量（每次確認出貨累加）
 *   - is_confirmed = shipped_qty >= quantity 時才為 1
 *
 * 執行方式：
 *   預覽:  https://hswork.com.tw/run_migration_111.php
 *   執行:  https://hswork.com.tw/run_migration_111.php?go=1
 *
 * 安全措施：
 *   - 所有 UPDATE 包在 transaction 裡，失敗自動 rollback
 *   - 執行前先加新欄位（可逆）
 *   - 方案 C：超額出貨筆照常 swap，保留兩個原始數字
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');
header('Content-Type: text/plain; charset=utf-8');
set_time_limit(300);

$db = Database::getInstance();
$execute = isset($_GET['go']) && $_GET['go'] === '1';

echo "===========================================\n";
echo "  Migration 111: stock_out_items shipped_qty\n";
echo "===========================================\n";
echo $execute ? "模式：實際執行\n\n" : "模式：預覽（加 ?go=1 才會實際寫入）\n\n";

// ============================================================
// Step 1: 新增 shipped_qty 欄位（可重複執行）
// ============================================================
echo "[Step 1] 新增 shipped_qty 欄位\n";
try {
    $col = $db->query("SHOW COLUMNS FROM stock_out_items LIKE 'shipped_qty'")->fetch();
    if ($col) {
        echo "  SKIP: shipped_qty 欄位已存在\n";
    } else {
        if ($execute) {
            $db->exec("ALTER TABLE stock_out_items ADD COLUMN shipped_qty DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT '累計已出貨量' AFTER quantity");
            echo "  OK: shipped_qty 欄位已新增\n";
        } else {
            echo "  PREVIEW: 會執行 ALTER TABLE ADD COLUMN shipped_qty DECIMAL(10,2) NOT NULL DEFAULT 0\n";
        }
    }
} catch (Exception $e) {
    echo "  ERR: " . $e->getMessage() . "\n";
    exit;
}
echo "\n";

// ============================================================
// Step 2: 歷史資料遷移（transaction 保護）
// ============================================================
echo "[Step 2] 歷史資料遷移\n";

// 統計執行前狀況
$rule1Count = (int)$db->query("SELECT COUNT(*) FROM stock_out_items WHERE request_qty > 0")->fetchColumn();
$rule2Count = (int)$db->query("SELECT COUNT(*) FROM stock_out_items WHERE (request_qty IS NULL OR request_qty = 0) AND is_confirmed = 1")->fetchColumn();
$untouchedCount = (int)$db->query("SELECT COUNT(*) FROM stock_out_items WHERE (request_qty IS NULL OR request_qty = 0) AND is_confirmed = 0")->fetchColumn();

echo "\n--- Rule 1: Ragic 資料 swap ---\n";
echo "  預計影響 {$rule1Count} 筆\n";
echo "  SQL: UPDATE stock_out_items\n";
echo "       SET shipped_qty = quantity, quantity = request_qty\n";
echo "       WHERE request_qty > 0\n";
echo "       (此動作 : quantity 變成需求量, shipped_qty 變成實際出貨量)\n";

echo "\n--- Rule 2: 手動確認資料 ---\n";
echo "  預計影響 {$rule2Count} 筆\n";
echo "  SQL: UPDATE stock_out_items\n";
echo "       SET shipped_qty = quantity\n";
echo "       WHERE (request_qty IS NULL OR request_qty = 0) AND is_confirmed = 1\n";
echo "       (只填入 shipped_qty, 不動 quantity)\n";

echo "\n--- 不受影響 ---\n";
echo "  {$untouchedCount} 筆（未確認的手動資料，shipped_qty 維持預設 0）\n\n";

if (!$execute) {
    echo "===========================================\n";
    echo "預覽完成，未寫入任何資料\n";
    echo "\n要執行請訪問:\n";
    echo "  https://hswork.com.tw/run_migration_111.php?go=1\n";
    echo "===========================================\n";
    exit;
}

// ============================================================
// 實際執行
// ============================================================
echo "[Step 2-EXEC] 開始執行（transaction 保護）\n";

// 檢查欄位是否已存在（必須存在才能 UPDATE）
$colCheck = $db->query("SHOW COLUMNS FROM stock_out_items LIKE 'shipped_qty'")->fetch();
if (!$colCheck) {
    echo "ERR: shipped_qty 欄位不存在，無法繼續\n";
    exit;
}

$db->beginTransaction();
try {
    // Rule 1: Ragic swap
    $upd1 = $db->prepare("
        UPDATE stock_out_items
        SET shipped_qty = quantity, quantity = request_qty
        WHERE request_qty > 0
    ");
    $upd1->execute();
    $affected1 = $upd1->rowCount();
    echo "  Rule 1 完成: {$affected1} 筆\n";

    // Rule 2: 手動確認資料填 shipped_qty
    $upd2 = $db->prepare("
        UPDATE stock_out_items
        SET shipped_qty = quantity
        WHERE (request_qty IS NULL OR request_qty = 0) AND is_confirmed = 1
    ");
    $upd2->execute();
    $affected2 = $upd2->rowCount();
    echo "  Rule 2 完成: {$affected2} 筆\n";

    $db->commit();
    echo "\n✓ Transaction committed\n";
} catch (Exception $e) {
    $db->rollBack();
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    echo "  Transaction rolled back, 沒有任何變更\n";
    exit;
}

echo "\n";

// ============================================================
// Step 3: 驗證結果
// ============================================================
echo "[Step 3] 驗證結果\n";

// 總筆數（應該不變）
$totalAfter = (int)$db->query("SELECT COUNT(*) FROM stock_out_items")->fetchColumn();
echo "  stock_out_items 總筆數: {$totalAfter} (應該跟遷移前一樣)\n";

// 檢查測試單 SO-20260408-004
echo "\n  SO-20260408-004 測試單狀況:\n";
$test = $db->query("
    SELECT soi.id, soi.product_name, soi.quantity, soi.shipped_qty, soi.is_confirmed, so.status
    FROM stock_out_items soi
    JOIN stock_outs so ON soi.stock_out_id = so.id
    WHERE so.so_number = 'SO-20260408-004'
")->fetchAll(PDO::FETCH_ASSOC);
foreach ($test as $t) {
    printf("    %-25s quantity=%-6s shipped_qty=%-6s is_confirmed=%s\n",
        mb_substr($t['product_name'], 0, 20), $t['quantity'], $t['shipped_qty'], $t['is_confirmed']);
}

// 檢查幾筆 Ragic swap 後的狀況
echo "\n  Ragic swap 後範例（前 5 筆 part shipment）:\n";
$ragicSamples = $db->query("
    SELECT soi.id, so.so_number, soi.product_name, soi.quantity, soi.shipped_qty, soi.request_qty
    FROM stock_out_items soi
    JOIN stock_outs so ON soi.stock_out_id = so.id
    WHERE soi.shipped_qty < soi.quantity AND soi.shipped_qty > 0
    ORDER BY soi.id DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
foreach ($ragicSamples as $r) {
    printf("    id=%-6s %-20s %-25s quantity=%-6s shipped_qty=%-6s\n",
        $r['id'],
        substr($r['so_number'], 0, 18),
        mb_substr($r['product_name'], 0, 20),
        $r['quantity'],
        $r['shipped_qty']
    );
}

// 檢查超額出貨筆數
$overCount = (int)$db->query("SELECT COUNT(*) FROM stock_out_items WHERE shipped_qty > quantity")->fetchColumn();
echo "\n  超額出貨筆數 (shipped_qty > quantity): {$overCount}\n";

echo "\n===========================================\n";
echo "  Migration 111 完成\n";
echo "===========================================\n";
echo "\n下一步：\n";
echo "  1. 確認資料正確\n";
echo "  2. 執行階段 3：修正 StockModel.php 核心邏輯\n";
echo "\n若要還原請用 DB 備份（shipped_qty 欄位可以直接 DROP）\n";
echo "Done.\n";
