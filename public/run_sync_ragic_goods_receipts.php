<?php
/**
 * Ragic → hswork 進貨單同步
 * 先清空再匯入
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!in_array(Session::getUser()['role'], array('boss', 'vice_president'))) {
    die('需要管理員權限');
}

set_time_limit(600);
ini_set('memory_limit', '512M');
header('Content-Type: text/html; charset=utf-8');
echo '<h2>Ragic 進貨單同步</h2><pre>';

$db = Database::getInstance();

$jsonFile = __DIR__ . '/../database/ragic_goods_receipts_20260405.json';
if (!file_exists($jsonFile)) die('找不到 JSON');
$ragicData = json_decode(file_get_contents($jsonFile), true);
echo "Ragic 進貨單: " . count($ragicData) . " 筆\n";
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 對照表
$branchMap = array();
$bStmt = $db->query('SELECT id, name FROM branches');
while ($b = $bStmt->fetch(PDO::FETCH_ASSOC)) $branchMap[$b['name']] = $b['id'];

$warehouseNumMap = array('1' => 1, '2' => 2, '3' => 3, '4' => 4, '5' => 5);
$warehouseMap = array();
$wStmt = $db->query('SELECT id, name FROM warehouses');
while ($w = $wStmt->fetch(PDO::FETCH_ASSOC)) $warehouseMap[$w['name']] = $w['id'];

$userMap = array();
$uStmt = $db->query('SELECT id, real_name FROM users WHERE is_active = 1');
while ($u = $uStmt->fetch(PDO::FETCH_ASSOC)) $userMap[$u['real_name']] = $u['id'];

$vendorMap = array();
try {
    $vStmt = $db->query('SELECT id, vendor_code, name FROM vendors');
    while ($v = $vStmt->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($v['vendor_code'])) $vendorMap[$v['vendor_code']] = $v['id'];
        if (!empty($v['name'])) $vendorMap[$v['name']] = $v['id'];
    }
} catch (Exception $e) {
    echo "vendors查詢: " . $e->getMessage() . "\n";
}

$productMap = array();
$productUnits = array();
$pStmt = $db->query('SELECT id, model, source_id, unit FROM products');
while ($p = $pStmt->fetch(PDO::FETCH_ASSOC)) {
    if ($p['model']) $productMap[strtoupper(trim($p['model']))] = (int)$p['id'];
    if ($p['source_id']) $productMap[strtoupper(trim($p['source_id']))] = (int)$p['id'];
    $productUnits[$p['id']] = $p['unit'] ?: '個';
}

$existCount = (int)$db->query("SELECT COUNT(*) FROM goods_receipts")->fetchColumn();
echo "系統現有: {$existCount} 筆\n";

$parseDate = function($v) {
    if (!$v || !trim($v)) return null;
    $v = str_replace('/', '-', trim($v));
    return preg_match('/^\d{4}-\d{2}-\d{2}/', $v) ? substr($v, 0, 10) : null;
};
$parseNum = function($v) {
    if (!$v || !trim($v)) return 0;
    $v = str_replace(',', '', trim($v));
    return is_numeric($v) ? round((float)$v, 2) : 0;
};

// 清空
echo "\n清空...\n";
$db->exec("SET FOREIGN_KEY_CHECKS = 0");
$db->exec("DELETE FROM goods_receipt_items");
$db->exec("DELETE FROM goods_receipts");
$db->exec("ALTER TABLE goods_receipts AUTO_INCREMENT = 1");
$db->exec("ALTER TABLE goods_receipt_items AUTO_INCREMENT = 1");
$db->exec("SET FOREIGN_KEY_CHECKS = 1");
echo "✓ 已清空\n\n";

$insertGR = $db->prepare("
    INSERT INTO goods_receipts
        (gr_number, gr_date, status, vendor_id, vendor_name,
         warehouse_id, branch_id, branch_name, receiver_name, note,
         total_qty, total_amount, paid_amount, paid_date,
         created_by, created_at, updated_by, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)
");

$insertItem = $db->prepare("
    INSERT INTO goods_receipt_items
        (goods_receipt_id, product_id, model, product_name, unit, po_qty, received_qty, unit_price, amount, note, sort_order)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$insertCount = 0;
$itemCount = 0;
$errorCount = 0;

foreach ($ragicData as $ragicId => $r) {
    $grNumber = trim($r['進貨單號'] ?? '');
    if (!$grNumber) { $errorCount++; continue; }

    $branchName = trim($r['所屬分公司'] ?? '');
    $branchId = isset($branchMap[$branchName]) ? $branchMap[$branchName] : null;

    $warehouseNum = trim($r['倉庫編號'] ?? '');
    $warehouseId = isset($warehouseNumMap[$warehouseNum]) ? $warehouseNumMap[$warehouseNum] : null;
    if (!$warehouseId) {
        $whName = trim($r['倉庫名稱'] ?? '');
        $warehouseId = isset($warehouseMap[$whName]) ? $warehouseMap[$whName] : null;
    }

    $vendorCode = trim($r['廠商編號'] ?? '');
    $vendorId = isset($vendorMap[$vendorCode]) ? $vendorMap[$vendorCode] : null;
    $vendorName = trim($r['廠商名稱'] ?? '');

    $creatorName = trim($r['建檔人員'] ?? '');
    $createdBy = isset($userMap[$creatorName]) ? $userMap[$creatorName] : Auth::id();

    $lastModifier = trim($r['最後修改人員'] ?? '');
    $lastModifiedBy = isset($userMap[$lastModifier]) ? $userMap[$lastModifier] : null;
    $lastModifiedDate = $parseDate($r['最後修改日期'] ?? '');

    $status = trim($r['單據狀態'] ?? '');
    $mappedStatus = ($status === '確認進貨') ? '已確認' : (($status === '已取消') ? '已取消' : '待確認');

    $grDate = $parseDate($r['進貨日期'] ?? '') ?: date('Y-m-d');
    $note = trim($r['備註'] ?? '') ?: null;
    $totalAmount = $parseNum($r['含稅總計'] ?? '');

    // 子表明細
    $sub = $r['_subtable_1008226'] ?? array();
    $totalQty = 0;
    foreach ($sub as $item) {
        $totalQty += $parseNum($item['數量'] ?? '');
    }

    try {
        $paidAmount = $parseNum($r['已付金額'] ?? '');
        $paidDate = $parseDate($r['付款日期'] ?? '');

        $insertGR->execute(array(
            $grNumber, $grDate, $mappedStatus, $vendorId, $vendorName ?: null,
            $warehouseId, $branchId, $branchName ?: null, null, $note,
            $totalQty, $totalAmount, $paidAmount ?: null, $paidDate,
            $createdBy, $lastModifiedBy, $lastModifiedDate,
        ));
        $grId = (int)$db->lastInsertId();
        $insertCount++;

        foreach ($sub as $sItem) {
            $model = trim($sItem['商品型號'] ?? '');
            $productKey = trim($sItem['商品key'] ?? '');
            $productName = trim($sItem['商品名稱'] ?? '');

            $productId = null;
            if ($model && isset($productMap[strtoupper($model)])) $productId = $productMap[strtoupper($model)];
            elseif ($productKey && isset($productMap[strtoupper($productKey)])) $productId = $productMap[strtoupper($productKey)];

            $qty = $parseNum($sItem['數量'] ?? '');
            $price = $parseNum($sItem['單價'] ?? '');
            $amount = $parseNum($sItem['小計'] ?? '');
            if (!$amount && $qty && $price) $amount = round($qty * $price, 2);
            $sortOrder = (int)($sItem['項次'] ?? 0);

            $unit = $productId && isset($productUnits[$productId]) ? $productUnits[$productId] : null;
            $insertItem->execute(array(
                $grId, $productId, $model ?: null, $productName ?: null,
                $unit, $qty, $qty, $price, $amount, null, $sortOrder,
            ));
            $itemCount++;
        }
    } catch (Exception $e) {
        $errorCount++;
        echo "❌ {$grNumber}: " . $e->getMessage() . "\n";
    }
}

echo "\n===== 同步結果 =====\n";
echo "進貨單匯入: {$insertCount} 筆\n";
echo "明細匯入: {$itemCount} 筆\n";
echo "錯誤: {$errorCount} 筆\n";
echo '</pre>';
