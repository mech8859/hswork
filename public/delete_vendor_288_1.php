<?php
/**
 * 刪除 vendor_code='288-1' 的重複廠商
 * 如有引用，先轉移到 vendor_code='288' 的那筆
 *
 * ?mode=preview (預設) — 只顯示不動資料
 * ?mode=execute          — 實際執行（事務）
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('all')) die('admin only');
$db = Database::getInstance();

$mode = isset($_GET['mode']) ? $_GET['mode'] : 'preview';

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>刪除 vendor 288-1</title>";
echo "<style>body{font-family:sans-serif;padding:20px;line-height:1.6}table{border-collapse:collapse;margin:8px 0;font-size:14px}th,td{border:1px solid #ccc;padding:6px}th{background:#f0f0f0}.ok{color:#2e7d32}.err{color:#c62828}.warn{color:#e65100}</style>";
echo "</head><body>";
echo "<h1>刪除 vendor_code='288-1' 並轉移引用</h1>";
echo "<p>模式：<b>" . h($mode) . "</b></p>";

// ---- 尋找兩筆 ----
$toDelete = $db->query("SELECT * FROM vendors WHERE vendor_code = '288-1'")->fetchAll(PDO::FETCH_ASSOC);
$keeper   = $db->query("SELECT * FROM vendors WHERE vendor_code = '288'")->fetchAll(PDO::FETCH_ASSOC);

if (count($toDelete) === 0) {
    echo "<p class='warn'>找不到 vendor_code='288-1' 的記錄（可能已刪除）</p></body></html>";
    exit;
}
if (count($toDelete) > 1) {
    echo "<p class='err'>vendor_code='288-1' 有多筆（" . count($toDelete) . " 筆），中止</p>";
    foreach ($toDelete as $d) echo "<p>id={$d['id']} - " . h($d['name']) . "</p>";
    exit;
}
if (count($keeper) === 0) {
    echo "<p class='err'>找不到 vendor_code='288' 的記錄，無法轉移引用</p></body></html>";
    exit;
}
if (count($keeper) > 1) {
    echo "<p class='err'>vendor_code='288' 仍有多筆（" . count($keeper) . " 筆），請先處理</p></body></html>";
    exit;
}

$delId = (int)$toDelete[0]['id'];
$keepId = (int)$keeper[0]['id'];

echo "<p>保留：id={$keepId} 『" . h($keeper[0]['name']) . "』 vendor_code=" . h($keeper[0]['vendor_code']) . "</p>";
echo "<p>刪除：id={$delId} 『" . h($toDelete[0]['name']) . "』 vendor_code=" . h($toDelete[0]['vendor_code']) . "</p>";

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

echo "<h2>將要更新的引用</h2>";
echo "<table><tr><th>表</th><th>欄位</th><th>原 vendor_id={$delId}</th><th>→ 更新為 {$keepId}</th></tr>";
$totalRefs = 0;
$execQueries = array();
foreach ($refTables as $r) {
    $tbl = $r['table']; $col = $r['col'];
    $exists = $db->query("SHOW TABLES LIKE '{$tbl}'")->fetch();
    if (!$exists) { echo "<tr><td>{$tbl}</td><td colspan='3'>（表不存在）</td></tr>"; continue; }
    $hasCol = $db->query("SHOW COLUMNS FROM {$tbl} LIKE '{$col}'")->fetch();
    if (!$hasCol) { echo "<tr><td>{$tbl}</td><td>{$col}</td><td colspan='2'>（欄位不存在）</td></tr>"; continue; }

    $cnt = (int)$db->query("SELECT COUNT(*) FROM {$tbl} WHERE {$col} = {$delId}")->fetchColumn();
    echo "<tr><td>{$tbl}</td><td>{$col}</td><td>{$cnt}</td><td>" . ($cnt > 0 ? "<b>{$cnt}</b> 筆將改為 {$keepId}" : '—') . "</td></tr>";
    if ($cnt > 0) {
        $execQueries[] = array('sql' => "UPDATE {$tbl} SET {$col} = {$keepId} WHERE {$col} = {$delId}", 'desc' => "{$tbl}.{$col}: {$delId}→{$keepId} ({$cnt} 筆)");
        $totalRefs += $cnt;
    }
}
echo "</table>";
echo "<p>共需更新引用：<b>{$totalRefs}</b> 筆</p>";

// 同時同步 vendor_code 快照字串 (payables / payments_out)
foreach (array('payables', 'payments_out') as $tbl) {
    $hasCode = $db->query("SHOW COLUMNS FROM {$tbl} LIKE 'vendor_code'")->fetch();
    if (!$hasCode) continue;
    $cnt = (int)$db->query("SELECT COUNT(*) FROM {$tbl} WHERE vendor_code = '288-1'")->fetchColumn();
    if ($cnt > 0) {
        $execQueries[] = array('sql' => "UPDATE {$tbl} SET vendor_code = '288' WHERE vendor_code = '288-1'", 'desc' => "{$tbl}.vendor_code: '288-1'→'288' ({$cnt} 筆)");
    }
}

// ---- 刪除 ----
$execQueries[] = array('sql' => "DELETE FROM vendors WHERE id = {$delId}", 'desc' => "刪除 vendors id={$delId}");

echo "<h2>將執行的 SQL</h2><ol>";
foreach ($execQueries as $q) {
    echo "<li><code>" . h($q['sql']) . "</code> — " . h($q['desc']) . "</li>";
}
echo "</ol>";

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
    echo "<hr><p><a href='?mode=execute' style='font-size:18px;background:#c62828;color:#fff;padding:10px 20px;text-decoration:none;border-radius:4px'>⚠ 執行合併並刪除</a></p>";
    echo "<p><small>（整個流程包在事務中，失敗會自動 rollback）</small></p>";
}

echo "</body></html>";
