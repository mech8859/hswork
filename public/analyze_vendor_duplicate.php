<?php
/**
 * 重複廠商 id=282, id=288 引用分析
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('all')) die('admin only');
$db = Database::getInstance();

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>重複廠商分析</title>";
echo "<style>body{font-family:sans-serif;padding:20px;line-height:1.6}table{border-collapse:collapse;margin:8px 0;font-size:14px}th,td{border:1px solid #ccc;padding:6px}th{background:#f0f0f0}</style>";
echo "</head><body>";

echo "<h1>重複廠商 id=282 vs id=288 詳細比對</h1>";

// ---- 兩筆廠商完整欄位對照 ----
echo "<h2>1. 兩筆 vendors 完整欄位</h2>";
$vendors = $db->query("SELECT * FROM vendors WHERE id IN (282, 288)")->fetchAll(PDO::FETCH_ASSOC);

if (count($vendors) < 2) {
    echo "<p>找不到兩筆資料</p></body></html>";
    exit;
}

echo "<table><tr><th>欄位</th><th>id=282</th><th>id=288</th><th>差異</th></tr>";
$v1 = $vendors[0]['id'] == 282 ? $vendors[0] : $vendors[1];
$v2 = $vendors[0]['id'] == 288 ? $vendors[0] : $vendors[1];
foreach ($v1 as $col => $val) {
    $v1v = $v1[$col];
    $v2v = $v2[$col];
    $same = ($v1v == $v2v);
    $diff = $same ? '相同' : '<b style="color:red">不同</b>';
    echo "<tr><td>{$col}</td><td>" . h($v1v) . "</td><td>" . h($v2v) . "</td><td>{$diff}</td></tr>";
}
echo "</table>";

// ---- 引用統計 ----
echo "<h2>2. 兩筆廠商被引用筆數</h2>";
$refTables = array(
    'payables'              => 'vendor_id',
    'payments_out'          => 'vendor_id',
    'purchase_orders'       => 'vendor_id',
    'goods_receipts'        => 'vendor_id',
    'purchase_invoices'     => 'vendor_id',
    'stock_ins'             => 'vendor_id',
    'returns'               => 'vendor_id',
);

echo "<table><tr><th>表</th><th>欄位</th><th>id=282 引用數</th><th>id=288 引用數</th></tr>";
foreach ($refTables as $tbl => $col) {
    try {
        $exists = $db->query("SHOW TABLES LIKE '{$tbl}'")->fetch();
        if (!$exists) { echo "<tr><td>{$tbl}</td><td colspan='3'>（表不存在）</td></tr>"; continue; }
        $hasCol = $db->query("SHOW COLUMNS FROM {$tbl} LIKE '{$col}'")->fetch();
        if (!$hasCol) { echo "<tr><td>{$tbl}</td><td>{$col}</td><td colspan='2'>（欄位不存在）</td></tr>"; continue; }

        $n282 = (int)$db->query("SELECT COUNT(*) FROM {$tbl} WHERE {$col} = 282")->fetchColumn();
        $n288 = (int)$db->query("SELECT COUNT(*) FROM {$tbl} WHERE {$col} = 288")->fetchColumn();
        echo "<tr><td>{$tbl}</td><td>{$col}</td><td>{$n282}</td><td>{$n288}</td></tr>";
    } catch (Exception $e) {
        echo "<tr><td>{$tbl}</td><td colspan='3'>" . h($e->getMessage()) . "</td></tr>";
    }
}
echo "</table>";

// ---- vendor_products 關聯（產品廠商對照）----
echo "<h2>3. vendor_products 關聯產品數</h2>";
try {
    $n282 = (int)$db->query("SELECT COUNT(*) FROM vendor_products WHERE vendor_id = 282")->fetchColumn();
    $n288 = (int)$db->query("SELECT COUNT(*) FROM vendor_products WHERE vendor_id = 288")->fetchColumn();
    echo "<p>id=282：{$n282} 個產品關聯<br>id=288：{$n288} 個產品關聯</p>";
} catch (Exception $e) {
    echo "<p>（無此表或欄位）</p>";
}

echo "<h2>4. 建議處理方式</h2>";
echo "<ul>";
echo "<li><b>方案 A（合併）</b>：將 id=288 的所有引用改為 id=282，然後停用 id=288（is_active=0）。適用於確認是重複資料。</li>";
echo "<li><b>方案 B（分別編號）</b>：保留兩筆，id=282 轉為 B-0282、id=288 轉為 B-0288。適用於其實是兩個不同單位（如不同廠商但同名）。</li>";
echo "</ul>";

echo "</body></html>";
