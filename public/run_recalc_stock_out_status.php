<?php
/**
 * 重算所有出庫單狀態（依新的 shipped_qty 邏輯）
 *
 * 使用時機：Migration 111 執行後，部分出貨的 Ragic 單可能還顯示「已確認」狀態，
 *          需要重算為「部分出庫」才正確。
 *
 * 邏輯：
 *   - 全部品項 shipped_qty >= quantity → 已確認
 *   - 有品項 shipped_qty > 0 但有未完成 → 部分出庫
 *   - 全部 shipped_qty == 0 → 不動
 *   - 原狀態已取消 → 跳過
 *
 * 使用方式：
 *   預覽: https://hswork.com.tw/run_recalc_stock_out_status.php
 *   執行: https://hswork.com.tw/run_recalc_stock_out_status.php?go=1
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');
header('Content-Type: text/plain; charset=utf-8');
set_time_limit(300);

$db = Database::getInstance();
$execute = isset($_GET['go']) && $_GET['go'] === '1';

echo "===========================================\n";
echo "  重算出庫單狀態（依 shipped_qty 新邏輯）\n";
echo "===========================================\n";
echo $execute ? "模式：實際執行\n\n" : "模式：預覽（加 ?go=1 實際寫入）\n\n";

// 抓所有出庫單 + 其 items 聚合
$stmt = $db->query("
    SELECT
        so.id,
        so.so_number,
        so.status AS old_status,
        COUNT(soi.id) AS total_items,
        COALESCE(SUM(soi.quantity), 0) AS total_need,
        COALESCE(SUM(soi.shipped_qty), 0) AS total_shipped,
        COALESCE(SUM(CASE WHEN soi.shipped_qty > 0 THEN 1 ELSE 0 END), 0) AS any_shipped_count,
        COALESCE(SUM(CASE WHEN soi.shipped_qty >= soi.quantity THEN 1 ELSE 0 END), 0) AS fully_shipped_count
    FROM stock_outs so
    LEFT JOIN stock_out_items soi ON soi.stock_out_id = so.id
    WHERE so.status != '已取消'
    GROUP BY so.id
    ORDER BY so.id DESC
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "總計 " . count($rows) . " 筆（不含已取消）\n\n";

$stats = array(
    'no_change' => 0,
    'to_confirmed' => 0,
    'to_partial' => 0,
    'to_pending' => 0, // shipped=0 的從 已確認 狀態回來?
    'empty' => 0,
);
$samples = array();

foreach ($rows as $r) {
    $totalItems = (int)$r['total_items'];
    if ($totalItems === 0) {
        $stats['empty']++;
        continue;
    }

    $fullyShipped = (int)$r['fully_shipped_count'];
    $anyShipped = (int)$r['any_shipped_count'];

    // 計算新狀態
    if ($fullyShipped >= $totalItems) {
        $newStatus = '已確認';
    } elseif ($anyShipped > 0) {
        $newStatus = '部分出庫';
    } else {
        $newStatus = null; // 不動
    }

    $oldStatus = $r['old_status'];
    if ($newStatus === null || $newStatus === $oldStatus) {
        $stats['no_change']++;
        continue;
    }

    // 記錄變動
    if ($newStatus === '已確認') $stats['to_confirmed']++;
    elseif ($newStatus === '部分出庫') $stats['to_partial']++;
    elseif ($newStatus === '待確認') $stats['to_pending']++;

    if (count($samples) < 20) {
        $samples[] = array(
            'so_number' => $r['so_number'],
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'items' => $totalItems,
            'need' => $r['total_need'],
            'shipped' => $r['total_shipped'],
        );
    }

    // 實際更新
    if ($execute) {
        try {
            $db->prepare("UPDATE stock_outs SET status = ? WHERE id = ?")
               ->execute(array($newStatus, $r['id']));
        } catch (Exception $e) {
            echo "  ERR {$r['so_number']}: " . $e->getMessage() . "\n";
        }
    }
}

echo "=== 統計 ===\n";
echo "  不需變更: {$stats['no_change']}\n";
echo "  → 改為「已確認」: {$stats['to_confirmed']}\n";
echo "  → 改為「部分出庫」: {$stats['to_partial']}\n";
echo "  → 改為「待確認」: {$stats['to_pending']}\n";
echo "  空單（無 items）: {$stats['empty']}\n\n";

echo "=== 變更範例（前 20 筆） ===\n";
printf("%-25s %-10s → %-10s %-5s %-8s %-8s\n", 'so_number', 'old', 'new', 'items', 'need', 'shipped');
echo str_repeat('-', 80) . "\n";
foreach ($samples as $s) {
    printf("%-25s %-10s → %-10s %-5s %-8s %-8s\n",
        substr($s['so_number'], 0, 23),
        $s['old_status'],
        $s['new_status'],
        $s['items'],
        $s['need'],
        $s['shipped']
    );
}

echo "\n";
echo $execute ? "✓ 完成\n" : "預覽完成，加 ?go=1 執行\n";
