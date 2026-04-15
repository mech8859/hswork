<?php
/**
 * 合併並刪除重複廠商 id=288 → id=282
 *
 * 流程：
 *   1. 將所有引用 vendor_id=288 的記錄改為 vendor_id=282
 *   2. 刪除 id=288 的 vendors 記錄
 *
 * 使用：
 *   ?mode=preview (預設) — 只顯示會改什麼，不動資料
 *   ?mode=execute          — 實際執行（事務包裹）
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('all')) die('admin only');
$db = Database::getInstance();

$mode = isset($_GET['mode']) ? $_GET['mode'] : 'preview';

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>合併廠商 288→282</title>";
echo "<style>body{font-family:sans-serif;padding:20px;line-height:1.6}table{border-collapse:collapse;margin:8px 0;font-size:14px}th,td{border:1px solid #ccc;padding:6px}th{background:#f0f0f0}.ok{color:#2e7d32}.err{color:#c62828}.warn{color:#e65100}</style>";
echo "</head><body>";
echo "<h1>合併重複廠商：id=288 → id=282</h1>";
echo "<p>模式：<b>" . h($mode) . "</b></p>";

// ---- 確認兩筆存在且同名 ----
$v282 = $db->query("SELECT * FROM vendors WHERE id = 282")->fetch(PDO::FETCH_ASSOC);
$v288 = $db->query("SELECT * FROM vendors WHERE id = 288")->fetch(PDO::FETCH_ASSOC);
if (!$v282) { echo "<p class='err'>id=282 不存在，中止</p></body></html>"; exit; }
if (!$v288) {
    echo "<p class='warn'>id=288 已不存在（可能已處理過），中止</p></body></html>";
    exit;
}
echo "<p>保留：id=282 『" . h($v282['name']) . "』 vendor_code=" . h($v282['vendor_code']) . "</p>";
echo "<p>刪除：id=288 『" . h($v288['name']) . "』 vendor_code=" . h($v288['vendor_code']) . "</p>";

// ---- 定義要處理的引用表 ----
$refTables = array(
    array('table' => 'payables',          'col' => 'vendor_id'),
    array('table' => 'payments_out',      'col' => 'vendor_id'),
    array('table' => 'purchase_orders',   'col' => 'vendor_id'),
    array('table' => 'goods_receipts',    'col' => 'vendor_id'),
    array('table' => 'purchase_invoices', 'col' => 'vendor_id'),
    array('table' => 'stock_ins',         'col' => 'vendor_id'),
    array('table' => 'returns',           'col' => 'vendor_id'),
    array('table' => 'vendor_products',   'col' => 'vendor_id'),
);

// ---- 預覽引用數 ----
echo "<h2>將要更新的引用</h2>";
echo "<table><tr><th>表</th><th>欄位</th><th>原 vendor_id=288</th><th>→ 更新為 282</th></tr>";
$totalRefs = 0;
$execQueries = array();
foreach ($refTables as $r) {
    $tbl = $r['table']; $col = $r['col'];
    $exists = $db->query("SHOW TABLES LIKE '{$tbl}'")->fetch();
    if (!$exists) { echo "<tr><td>{$tbl}</td><td colspan='3'>（表不存在，略過）</td></tr>"; continue; }
    $hasCol = $db->query("SHOW COLUMNS FROM {$tbl} LIKE '{$col}'")->fetch();
    if (!$hasCol) { echo "<tr><td>{$tbl}</td><td>{$col}</td><td colspan='2'>（欄位不存在，略過）</td></tr>"; continue; }

    $cnt = (int)$db->query("SELECT COUNT(*) FROM {$tbl} WHERE {$col} = 288")->fetchColumn();
    echo "<tr><td>{$tbl}</td><td>{$col}</td><td>{$cnt}</td><td>" . ($cnt > 0 ? "<b>{$cnt}</b> 筆將改為 282" : '—') . "</td></tr>";
    if ($cnt > 0) {
        $execQueries[] = array('sql' => "UPDATE {$tbl} SET {$col} = 282 WHERE {$col} = 288", 'desc' => "{$tbl}.{$col}: 288→282 ({$cnt} 筆)");
        $totalRefs += $cnt;
    }
}
echo "</table>";
echo "<p>共需更新引用：<b>{$totalRefs}</b> 筆</p>";

// ---- 同步歷史快照 vendor_code（payables / payments_out 有 vendor_code 欄位的表）----
echo "<h2>歷史快照 vendor_code 更新</h2>";
echo "<p>payables 和 payments_out 存 vendor_code 字串快照，需要把原本 288 的快照值改為 282（與 id=282 的 vendor_code 一致）。</p>";

// 先確認 id=282 的 vendor_code 是 282（理論上應該是，但保險起見）
$expectedCode = $v282['vendor_code'];
echo "<p>id=282 目前 vendor_code = <b>" . h($expectedCode) . "</b>（更新目標值）</p>";

$snapshotUpdates = array();
foreach (array('payables', 'payments_out') as $tbl) {
    $hasCode = $db->query("SHOW COLUMNS FROM {$tbl} LIKE 'vendor_code'")->fetch();
    $hasVid = $db->query("SHOW COLUMNS FROM {$tbl} LIKE 'vendor_id'")->fetch();
    if (!$hasCode || !$hasVid) continue;
    // 只更新原本 vendor_id=288 的那些記錄的 vendor_code（經過上面更新後已變成 282，但我們要同步修正 vendor_code 字串）
    // 因為執行順序：先改 vendor_id 再同步 vendor_code 會找不到，所以我們要用不同條件
    // 策略：更新所有「vendor_code='288' 且原本是 id=288 的記錄」
    // 但執行時序：如果先更新 vendor_id 288→282，就沒法用 vendor_id=288 找到，所以用 vendor_code='288' 找
    $cnt = (int)$db->query("SELECT COUNT(*) FROM {$tbl} WHERE vendor_code = '288'")->fetchColumn();
    echo "<p>{$tbl}: vendor_code='288' 共 {$cnt} 筆（將保留原值，因為 id=282 的 vendor_code 也是 '282'，但這些原屬 288 的記錄代表來自不同廠商）</p>";
    // 注意：這裡不改 vendor_code，因為改了會造成歷史快照失真。vendor_code 代表當時的快照。
    // 如果要完全同步，可以改成 '282'，但這會讓歷史紀錄失去原始編號痕跡。
    // 決策：先不改，後續轉換為 B-XXXX 時一次處理。
}

// ---- 刪除 id=288 ----
echo "<h2>刪除 id=288</h2>";
$execQueries[] = array('sql' => "DELETE FROM vendors WHERE id = 288", 'desc' => "刪除 vendors id=288");

// ---- 預覽 SQL ----
echo "<h2>將執行的 SQL</h2>";
echo "<ol>";
foreach ($execQueries as $q) {
    echo "<li><code>" . h($q['sql']) . "</code> — " . h($q['desc']) . "</li>";
}
echo "</ol>";

// ---- 實際執行 ----
if ($mode === 'execute') {
    echo "<h2>執行結果</h2>";
    try {
        $db->beginTransaction();
        foreach ($execQueries as $q) {
            $stmt = $db->prepare($q['sql']);
            $stmt->execute();
            $affected = $stmt->rowCount();
            echo "<p class='ok'>✓ " . h($q['desc']) . " — 影響 {$affected} 筆</p>";
        }
        $db->commit();
        echo "<p class='ok'><b>✓ 全部完成</b></p>";
    } catch (Exception $e) {
        $db->rollBack();
        echo "<p class='err'>✗ 失敗：" . h($e->getMessage()) . "（已 rollback）</p>";
    }
} else {
    echo "<hr><p>以上僅為預覽，要實際執行請開：</p>";
    echo "<p><a href='?mode=execute' style='font-size:18px;background:#c62828;color:#fff;padding:10px 20px;text-decoration:none;border-radius:4px'>⚠ 執行合併並刪除 id=288</a></p>";
    echo "<p><small>（整個流程包在資料庫事務中，失敗會自動 rollback）</small></p>";
}

echo "</body></html>";
