<?php
/**
 * inventory UK 修正前分析（只讀）
 * 檢查：
 *   1. (product_id, warehouse_id) 有無重複（會擋新 UK）
 *   2. branch_id=0 的孤立記錄
 *   3. 孤立記錄與正規記錄是否可以合併
 *   4. warehouse ↔ branch 對應關係
 *   5. 總資料量（確保不遺失）
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('all')) die('admin only');
$db = Database::getInstance();

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

echo "<!DOCTYPE html><html><head><meta charset=utf-8><title>inventory UK 修正分析</title>";
echo "<style>body{font-family:sans-serif;padding:20px;line-height:1.6}table{border-collapse:collapse;font-size:13px;margin:8px 0}th,td{border:1px solid #ccc;padding:6px}th{background:#f0f0f0}.ok{color:#2e7d32;font-weight:bold}.warn{color:#e65100}.err{color:#c62828;font-weight:bold}</style></head><body>";
echo "<h1>inventory UK 修正前分析</h1>";

// 基本統計
$total = (int)$db->query("SELECT COUNT(*) FROM inventory")->fetchColumn();
$distinctProd = (int)$db->query("SELECT COUNT(DISTINCT product_id) FROM inventory")->fetchColumn();
$sumStock = (float)$db->query("SELECT COALESCE(SUM(stock_qty),0) FROM inventory")->fetchColumn();
$sumAvail = (float)$db->query("SELECT COALESCE(SUM(available_qty),0) FROM inventory")->fetchColumn();
echo "<h2>1. 總體資料量</h2>";
echo "<p>總記錄：<b>{$total}</b> 筆，不重複商品：<b>{$distinctProd}</b>，總 stock_qty：<b>" . number_format($sumStock, 2) . "</b>，總 available_qty：<b>" . number_format($sumAvail, 2) . "</b></p>";

// warehouse / branch 分布
echo "<h2>2. warehouse / branch 分布</h2>";
$byWh = $db->query("SELECT warehouse_id, COUNT(*) AS cnt FROM inventory GROUP BY warehouse_id ORDER BY warehouse_id")->fetchAll(PDO::FETCH_ASSOC);
echo "<table><tr><th>warehouse_id</th><th>筆數</th></tr>";
foreach ($byWh as $r) echo "<tr><td>" . h($r['warehouse_id'] === null ? 'NULL' : $r['warehouse_id']) . "</td><td>{$r['cnt']}</td></tr>";
echo "</table>";

$byBr = $db->query("SELECT branch_id, COUNT(*) AS cnt FROM inventory GROUP BY branch_id ORDER BY branch_id")->fetchAll(PDO::FETCH_ASSOC);
echo "<table><tr><th>branch_id</th><th>筆數</th></tr>";
foreach ($byBr as $r) echo "<tr><td>" . h($r['branch_id'] === null ? 'NULL' : $r['branch_id']) . "</td><td>{$r['cnt']}</td></tr>";
echo "</table>";

// 3. (product_id, warehouse_id) 重複檢查 ← 會擋新 UK
echo "<h2>3. (product_id, warehouse_id) 重複檢查（關鍵）</h2>";
$dups = $db->query("
    SELECT product_id, warehouse_id, COUNT(*) AS cnt, GROUP_CONCAT(id) AS ids, GROUP_CONCAT(branch_id) AS branches, GROUP_CONCAT(stock_qty) AS stocks
    FROM inventory
    GROUP BY product_id, warehouse_id
    HAVING cnt > 1
    ORDER BY cnt DESC
    LIMIT 100
")->fetchAll(PDO::FETCH_ASSOC);
if (empty($dups)) {
    echo "<p class='ok'>✓ 無 (product_id, warehouse_id) 重複，可安全建立新 UK</p>";
} else {
    echo "<p class='err'>✗ 有 " . count($dups) . " 組重複（前 100 筆）— 需合併後才能建 UK</p>";
    echo "<table><tr><th>product_id</th><th>warehouse_id</th><th>筆數</th><th>ids</th><th>branch_ids</th><th>stock_qtys</th></tr>";
    foreach ($dups as $r) {
        echo "<tr><td>{$r['product_id']}</td><td>" . h($r['warehouse_id'] ?? 'NULL') . "</td><td>{$r['cnt']}</td><td>{$r['ids']}</td><td>{$r['branches']}</td><td>{$r['stocks']}</td></tr>";
    }
    echo "</table>";
}

// 4. branch_id=0 的孤立記錄
echo "<h2>4. branch_id=0 的記錄（可能是舊匯入殘留）</h2>";
$zeroBr = (int)$db->query("SELECT COUNT(*) FROM inventory WHERE branch_id = 0")->fetchColumn();
echo "<p>branch_id=0 共 <b>{$zeroBr}</b> 筆</p>";

// 檢查這些記錄的 warehouse_id 分布
$zeroDetail = $db->query("
    SELECT warehouse_id, COUNT(*) AS cnt, SUM(stock_qty) AS total_stock
    FROM inventory
    WHERE branch_id = 0
    GROUP BY warehouse_id
    ORDER BY warehouse_id
")->fetchAll(PDO::FETCH_ASSOC);
echo "<table><tr><th>warehouse_id</th><th>筆數</th><th>庫存總和</th></tr>";
foreach ($zeroDetail as $r) echo "<tr><td>" . h($r['warehouse_id'] ?? 'NULL') . "</td><td>{$r['cnt']}</td><td>" . number_format((float)$r['total_stock'], 2) . "</td></tr>";
echo "</table>";

// 5. 檢查 branch_id=0 是否和正規記錄重複（同 product 同 warehouse 但 branch_id≠0）
echo "<h2>5. branch_id=0 與正規記錄的重疊（會合併的對象）</h2>";
$overlap = $db->query("
    SELECT z.id AS zero_id, z.product_id, z.warehouse_id,
           z.branch_id AS zero_branch, z.stock_qty AS zero_stock,
           n.id AS normal_id, n.branch_id AS normal_branch, n.stock_qty AS normal_stock
    FROM inventory z
    JOIN inventory n ON z.product_id = n.product_id AND z.warehouse_id = n.warehouse_id AND n.branch_id != 0
    WHERE z.branch_id = 0
    ORDER BY z.product_id
    LIMIT 100
")->fetchAll(PDO::FETCH_ASSOC);

if (empty($overlap)) {
    echo "<p class='ok'>✓ 無 branch_id=0 與正規記錄重疊的情況</p>";
} else {
    echo "<p class='warn'>⚠ 有 " . count($overlap) . " 組（前 100 筆）會需合併：zero 記錄的 stock 需加到 normal 記錄，然後刪除 zero</p>";
    echo "<table><tr><th>product_id</th><th>warehouse</th><th>zero_id</th><th>zero_branch</th><th>zero_stock</th><th>normal_id</th><th>normal_branch</th><th>normal_stock</th><th>合併後</th></tr>";
    foreach ($overlap as $r) {
        echo "<tr><td>{$r['product_id']}</td><td>{$r['warehouse_id']}</td><td>{$r['zero_id']}</td><td>{$r['zero_branch']}</td><td>" . number_format((float)$r['zero_stock'], 2) . "</td><td>{$r['normal_id']}</td><td>{$r['normal_branch']}</td><td>" . number_format((float)$r['normal_stock'], 2) . "</td><td>" . number_format((float)$r['zero_stock'] + (float)$r['normal_stock'], 2) . "</td></tr>";
    }
    echo "</table>";
}

// 6. branch_id=0 的孤立記錄（沒對應正規）
echo "<h2>6. branch_id=0 的純孤立記錄（無同 product+warehouse 的正規記錄）</h2>";
$orphan = $db->query("
    SELECT z.id, z.product_id, z.warehouse_id, z.stock_qty
    FROM inventory z
    WHERE z.branch_id = 0
      AND NOT EXISTS (
        SELECT 1 FROM inventory n
        WHERE n.product_id = z.product_id AND n.warehouse_id = z.warehouse_id AND n.branch_id != 0
      )
    ORDER BY z.product_id
    LIMIT 30
")->fetchAll(PDO::FETCH_ASSOC);
$orphanTotal = (int)$db->query("
    SELECT COUNT(*)
    FROM inventory z
    WHERE z.branch_id = 0
      AND NOT EXISTS (
        SELECT 1 FROM inventory n
        WHERE n.product_id = z.product_id AND n.warehouse_id = z.warehouse_id AND n.branch_id != 0
      )
")->fetchColumn();
echo "<p>孤立記錄共 <b>{$orphanTotal}</b> 筆（這些要補 branch_id，不能刪）</p>";
if (!empty($orphan)) {
    echo "<table><tr><th>id</th><th>product_id</th><th>warehouse_id</th><th>stock_qty</th><th>建議新 branch_id（從 warehouse 反查）</th></tr>";
    // 查 warehouses 表反查 branch
    $whStmt = $db->query("SELECT id, branch_id FROM warehouses");
    $whBranchMap = array();
    foreach ($whStmt->fetchAll(PDO::FETCH_ASSOC) as $w) $whBranchMap[$w['id']] = $w['branch_id'];
    foreach ($orphan as $r) {
        $newBr = isset($whBranchMap[$r['warehouse_id']]) ? $whBranchMap[$r['warehouse_id']] : '(無對應)';
        echo "<tr><td>{$r['id']}</td><td>{$r['product_id']}</td><td>{$r['warehouse_id']}</td><td>" . number_format((float)$r['stock_qty'], 2) . "</td><td>{$newBr}</td></tr>";
    }
    echo "</table>";
}

// 7. warehouses 表對應
echo "<h2>7. warehouses 表 branch 對應</h2>";
try {
    $whs = $db->query("SELECT id, name, branch_id FROM warehouses ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    echo "<table><tr><th>warehouse_id</th><th>倉庫名稱</th><th>branch_id</th></tr>";
    foreach ($whs as $w) echo "<tr><td>{$w['id']}</td><td>" . h($w['name']) . "</td><td>" . h($w['branch_id'] ?? 'NULL') . "</td></tr>";
    echo "</table>";
} catch (Exception $e) {
    echo "<p class='warn'>warehouses 表查詢失敗：" . h($e->getMessage()) . "</p>";
}

// 8. 最終結論
echo "<h2>8. 結論</h2>";
echo "<ul>";
echo "<li>總記錄：{$total} 筆</li>";
echo "<li>(product_id, warehouse_id) 重複：<b>" . count($dups) . "</b> 組（新 UK 要過，必須先處理）</li>";
echo "<li>branch_id=0 總筆數：{$zeroBr}</li>";
echo "<li>需合併（有正規對應）：" . count($overlap) . " 筆 zero 會合併到 normal</li>";
echo "<li>純孤立（無正規對應）：{$orphanTotal} 筆 zero 需補 branch_id</li>";
echo "</ul>";
echo "<p>此腳本僅讀取，未修改資料。</p>";

echo "</body></html>";
