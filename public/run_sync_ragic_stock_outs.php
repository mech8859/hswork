<?php
/**
 * Ragic → hswork 出庫單同步
 * 先清空系統出庫單，再從 Ragic 全部匯入
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!in_array(Session::getUser()['role'], array('boss', 'vice_president'))) {
    die('需要管理員權限');
}

set_time_limit(600);
ini_set('memory_limit', '512M');
header('Content-Type: text/html; charset=utf-8');
echo '<h2>Ragic 出庫單同步</h2><pre>';
ob_flush(); flush();

$db = Database::getInstance();
$dryRun = !isset($_GET['execute']);

if ($dryRun) {
    echo "【預覽模式】加 ?execute=1 執行。\n\n";
} else {
    echo "【執行模式】\n\n";
}

// ===== 載入 Ragic =====
$jsonFile = __DIR__ . '/../database/ragic_stock_outs_20260405.json';
if (!file_exists($jsonFile)) die('找不到 JSON');
$ragicData = json_decode(file_get_contents($jsonFile), true);
echo "Ragic 出庫單: " . count($ragicData) . " 筆\n";

// ===== 對照表 =====
$branchMap = array();
$bStmt = $db->query('SELECT id, name FROM branches');
while ($b = $bStmt->fetch(PDO::FETCH_ASSOC)) {
    $branchMap[$b['name']] = $b['id'];
}

$warehouseMap = array(); // name → id, also ragic 倉庫編號 → id
$wStmt = $db->query('SELECT id, name FROM warehouses');
while ($w = $wStmt->fetch(PDO::FETCH_ASSOC)) {
    $warehouseMap[$w['name']] = $w['id'];
}
// Ragic 倉庫編號對照（1=潭子,2=員林,3=清水,4=東區電子鎖,5=清水電子鎖）
$warehouseNumMap = array(
    '1' => isset($warehouseMap['潭子倉庫']) ? $warehouseMap['潭子倉庫'] : 1,
    '2' => isset($warehouseMap['員林倉庫']) ? $warehouseMap['員林倉庫'] : 2,
    '3' => isset($warehouseMap['清水倉庫']) ? $warehouseMap['清水倉庫'] : 3,
    '4' => isset($warehouseMap['東區電子鎖倉庫']) ? $warehouseMap['東區電子鎖倉庫'] : 4,
    '5' => isset($warehouseMap['清水電子鎖倉庫']) ? $warehouseMap['清水電子鎖倉庫'] : 5,
);

$userMap = array();
$uStmt = $db->query('SELECT id, real_name FROM users WHERE is_active = 1');
while ($u = $uStmt->fetch(PDO::FETCH_ASSOC)) {
    $userMap[$u['real_name']] = $u['id'];
}

$productMap = array();
$pStmt = $db->query('SELECT id, model, source_id FROM products');
while ($p = $pStmt->fetch(PDO::FETCH_ASSOC)) {
    if ($p['model']) $productMap[strtoupper(trim($p['model']))] = (int)$p['id'];
    if ($p['source_id']) $productMap[strtoupper(trim($p['source_id']))] = (int)$p['id'];
}
echo "商品映射: " . count($productMap) . " 筆\n";

// ===== 現有資料統計 =====
$existCount = (int)$db->query("SELECT COUNT(*) FROM stock_outs")->fetchColumn();
$existItemCount = (int)$db->query("SELECT COUNT(*) FROM stock_out_items")->fetchColumn();
echo "系統現有出庫單: {$existCount} 筆, 明細: {$existItemCount} 筆\n\n";

$parseDate = function($v) {
    if (!$v || !trim($v)) return null;
    $v = str_replace('/', '-', trim($v));
    if (preg_match('/^\d{4}-\d{2}-\d{2}/', $v)) return substr($v, 0, 10);
    return null;
};

$parseNum = function($v) {
    if (!$v || !trim($v)) return 0;
    $v = str_replace(',', '', trim($v));
    return is_numeric($v) ? round((float)$v, 2) : 0;
};

// ===== 統計預覽 =====
$totalItems = 0;
$statusCounts = array();
foreach ($ragicData as $rid => $r) {
    $status = trim($r['單據狀態'] ?? '');
    $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
    $sub = $r['_subtable_1008754'] ?? array();
    $totalItems += count($sub);
}
echo "Ragic 狀態統計:\n";
foreach ($statusCounts as $s => $c) echo "  {$s}: {$c}\n";
echo "Ragic 明細總數: {$totalItems}\n\n";

if ($dryRun) {
    echo "將清空系統出庫單 {$existCount} 筆 + 明細 {$existItemCount} 筆\n";
    echo "將匯入 " . count($ragicData) . " 筆出庫單 + {$totalItems} 筆明細\n";
    echo "\n<a href='?execute=1' style='font-size:1.2em;color:red'>⚠ 點此執行同步（會先清空再匯入）</a>\n";
    echo '</pre>';
    exit;
}

// ===== 執行：清空 =====
echo "清空系統出庫單...\n";
$db->exec("SET FOREIGN_KEY_CHECKS = 0");
$db->exec("DELETE FROM stock_out_items");
$db->exec("DELETE FROM stock_outs");
$db->exec("ALTER TABLE stock_outs AUTO_INCREMENT = 1");
$db->exec("ALTER TABLE stock_out_items AUTO_INCREMENT = 1");
$db->exec("SET FOREIGN_KEY_CHECKS = 1");
echo "✓ 已清空\n\n";
flush();

// ===== 執行：匯入 =====
$insertCount = 0;
$itemCount = 0;
$errorCount = 0;

$insertSO = $db->prepare("
    INSERT INTO stock_outs
        (so_number, so_date, status, source_type, warehouse_id,
         customer_name, branch_id, branch_name, note, total_qty,
         created_by, created_at, updated_by, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)
");

$insertItem = $db->prepare("
    INSERT INTO stock_out_items
        (stock_out_id, product_id, model, product_name, unit, quantity, request_qty, unit_price, note, sort_order)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

foreach ($ragicData as $ragicId => $r) {
    $soNumber = trim($r['出貨單號'] ?? '');
    if (!$soNumber) { $errorCount++; continue; }

    $branchName = trim($r['所屬分公司'] ?? '');
    $branchId = isset($branchMap[$branchName]) ? $branchMap[$branchName] : null;

    $warehouseNum = trim($r['倉庫編號'] ?? '');
    $warehouseId = isset($warehouseNumMap[$warehouseNum]) ? $warehouseNumMap[$warehouseNum] : null;
    // 也試用倉庫名稱
    if (!$warehouseId) {
        $whName = trim($r['倉庫名稱'] ?? '');
        $warehouseId = isset($warehouseMap[$whName]) ? $warehouseMap[$whName] : null;
    }

    $creatorName = trim($r['建檔人員'] ?? '');
    $createdBy = isset($userMap[$creatorName]) ? $userMap[$creatorName] : Auth::id();

    $status = trim($r['單據狀態'] ?? '待確認');
    // 狀態對照
    $statusMap = array('已出貨' => '已確認', '備貨中' => '待確認', '草稿' => '待確認', '已取消' => '已取消');
    $mappedStatus = isset($statusMap[$status]) ? $statusMap[$status] : $status;

    $customerName = trim($r['客戶名稱'] ?? '');
    if (!$customerName) $customerName = trim($r['客戶名稱(暫用)'] ?? '');

    $sourceType = trim($r['出貨類型'] ?? '') ?: null;
    $note = trim($r['備註'] ?? '') ?: null;
    $soDate = $parseDate($r['建立日期'] ?? '') ?: date('Y-m-d');

    // 計算明細總數量
    $sub = $r['_subtable_1008754'] ?? array();
    $totalQty = 0;
    foreach ($sub as $item) {
        $totalQty += $parseNum($item['出貨數量'] ?? $item['備貨數量'] ?? '');
    }

    try {
        $lastModifier = trim($r['最後修改人員'] ?? '');
        $lastModifiedBy = isset($userMap[$lastModifier]) ? $userMap[$lastModifier] : null;
        $lastModifiedDate = $parseDate($r['最後修改日期'] ?? '');

        $insertSO->execute(array(
            $soNumber, $soDate, $mappedStatus, $sourceType, $warehouseId,
            $customerName ?: null, $branchId, $branchName ?: null, $note, $totalQty,
            $createdBy, $lastModifiedBy, $lastModifiedDate,
        ));
        $soId = (int)$db->lastInsertId();
        $insertCount++;

        // 明細
        foreach ($sub as $sItem) {
            $model = trim($sItem['商品型號'] ?? '');
            $productKey = trim($sItem['商品key'] ?? '');
            $productName = trim($sItem['商品名稱'] ?? '');

            $productId = null;
            if ($model && isset($productMap[strtoupper($model)])) $productId = $productMap[strtoupper($model)];
            elseif ($productKey && isset($productMap[strtoupper($productKey)])) $productId = $productMap[strtoupper($productKey)];

            $requestQty = $parseNum($sItem['報價數量'] ?? '');
            $shippedQty = $parseNum($sItem['出貨數量'] ?? '');
            $price = $parseNum($sItem['售價'] ?? '');
            $serial = trim($sItem['序號'] ?? '') ?: null;
            $sortOrder = (int)($sItem['項次'] ?? 0);

            // quantity = 出貨數量(出庫數量), request_qty = 報價數量(需求)
            $insertItem->execute(array(
                $soId, $productId, $model ?: null, $productName ?: null,
                null, $shippedQty, $requestQty, $price, $serial, $sortOrder,
            ));
            // 有出貨數量的標記已確認
            if ($shippedQty > 0) {
                $lastItemId = (int)$db->lastInsertId();
                $db->prepare("UPDATE stock_out_items SET is_confirmed = 1, confirmed_at = NOW() WHERE id = ?")->execute(array($lastItemId));
            }
            $itemCount++;
        }

    } catch (Exception $e) {
        $errorCount++;
        echo "❌ {$soNumber}: " . $e->getMessage() . "\n";
    }
}

echo "\n===== 同步結果 =====\n";
echo "出庫單匯入: {$insertCount} 筆\n";
echo "明細匯入: {$itemCount} 筆\n";
echo "錯誤: {$errorCount} 筆\n";
echo '</pre>';
