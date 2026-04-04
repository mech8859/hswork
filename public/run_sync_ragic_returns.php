<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!in_array(Session::getUser()['role'], array('boss', 'vice_president'))) die('需要管理員權限');

set_time_limit(300);
header('Content-Type: text/html; charset=utf-8');
echo '<h2>Ragic 退貨單同步</h2><pre>';

$db = Database::getInstance();

$jsonFile = __DIR__ . '/../database/ragic_returns_20260405.json';
if (!file_exists($jsonFile)) die('找不到 JSON');
$ragicData = json_decode(file_get_contents($jsonFile), true);
echo "Ragic 退貨單: " . count($ragicData) . " 筆\n";

$branchMap = array();
$bStmt = $db->query('SELECT id, name FROM branches');
while ($b = $bStmt->fetch(PDO::FETCH_ASSOC)) $branchMap[$b['name']] = $b['id'];

$warehouseNumMap = array('1'=>1,'2'=>2,'3'=>3,'4'=>4,'5'=>5);

$userMap = array();
$uStmt = $db->query('SELECT id, real_name FROM users WHERE is_active = 1');
while ($u = $uStmt->fetch(PDO::FETCH_ASSOC)) $userMap[$u['real_name']] = $u['id'];

$productMap = array();
$pStmt = $db->query('SELECT id, model, source_id FROM products');
while ($p = $pStmt->fetch(PDO::FETCH_ASSOC)) {
    if ($p['model']) $productMap[strtoupper(trim($p['model']))] = (int)$p['id'];
    if ($p['source_id']) $productMap[strtoupper(trim($p['source_id']))] = (int)$p['id'];
}

// 進貨單號→ID
$grMap = array();
$grStmt = $db->query('SELECT id, gr_number FROM goods_receipts');
while ($g = $grStmt->fetch(PDO::FETCH_ASSOC)) $grMap[$g['gr_number']] = $g['id'];

echo "系統現有: " . $db->query("SELECT COUNT(*) FROM returns")->fetchColumn() . " 筆\n";

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

echo "\n清空...\n";
$db->exec("DELETE FROM return_items");
$db->exec("DELETE FROM returns");
echo "✓ 已清空\n\n";

$insertR = $db->prepare("
    INSERT INTO returns
        (return_number, return_date, status, return_type, branch_id, vendor_name,
         warehouse_id, gr_id, note, total_qty, total_amount,
         created_by, created_at, updated_by, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)
");

$insertItem = $db->prepare("
    INSERT INTO return_items
        (return_id, product_id, model, product_name, unit, quantity, unit_price, amount, reason, sort_order)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$insertCount = 0;
$itemCount = 0;
$errorCount = 0;

foreach ($ragicData as $ragicId => $r) {
    $returnNumber = trim($r['退回單號'] ?? '');
    if (!$returnNumber) {
        // 自動產生單號
        $returnNumber = 'PR-RAGIC-' . $ragicId;
    }

    $branchName = trim($r['所屬分公司'] ?? '');
    $branchId = isset($branchMap[$branchName]) ? $branchMap[$branchName] : null;

    $warehouseNum = trim($r['倉庫編號'] ?? '');
    $warehouseId = isset($warehouseNumMap[$warehouseNum]) ? $warehouseNumMap[$warehouseNum] : null;

    $vendorName = trim($r['廠商名稱'] ?? '');

    $creatorName = trim($r['建檔人員'] ?? '');
    $createdBy = isset($userMap[$creatorName]) ? $userMap[$creatorName] : Auth::id();
    $lastModifier = trim($r['最後修改人員'] ?? '');
    $lastModifiedBy = isset($userMap[$lastModifier]) ? $userMap[$lastModifier] : null;
    $lastModifiedDate = $parseDate($r['最後修改日期'] ?? '');

    $status = trim($r['單據狀態'] ?? '');
    $statusMap = array('已退回' => '已確認', '確認進貨' => '已確認');
    $mappedStatus = isset($statusMap[$status]) ? $statusMap[$status] : '待確認';

    $returnDate = $parseDate($r['退貨日期'] ?? '') ?: date('Y-m-d');
    $reason = trim($r['退回原因'] ?? '') ?: null;
    $refundAmount = $parseNum($r['退款金額'] ?? '');

    $sourceGrNumber = trim($r['來源進貨單號'] ?? '');
    $grId = isset($grMap[$sourceGrNumber]) ? $grMap[$sourceGrNumber] : null;

    $sub = $r['_subtable_1008862'] ?? array();
    $totalQty = 0;
    foreach ($sub as $item) $totalQty += $parseNum($item['本次退回數量'] ?? '');

    try {
        $insertR->execute(array(
            $returnNumber, $returnDate, $mappedStatus, 'purchase_return', $branchId, $vendorName ?: null,
            $warehouseId, $grId, $reason, $totalQty, $refundAmount,
            $createdBy, $lastModifiedBy, $lastModifiedDate,
        ));
        $rId = (int)$db->lastInsertId();
        $insertCount++;

        foreach ($sub as $sItem) {
            $model = trim($sItem['商品型號'] ?? '');
            $productKey = trim($sItem['商品KEY'] ?? '');
            $productName = trim($sItem['商品名稱'] ?? '');

            $productId = null;
            if ($model && isset($productMap[strtoupper($model)])) $productId = $productMap[strtoupper($model)];
            elseif ($productKey && isset($productMap[strtoupper($productKey)])) $productId = $productMap[strtoupper($productKey)];

            $qty = $parseNum($sItem['本次退回數量'] ?? '');
            $price = $parseNum($sItem['單價(未稅)'] ?? '');
            $amount = $parseNum($sItem['退回金額(含稅)'] ?? '');
            $sortOrder = (int)($sItem['項次'] ?? 0);

            $insertItem->execute(array(
                $rId, $productId, $model ?: null, $productName ?: null,
                null, $qty, $price, $amount, null, $sortOrder,
            ));
            $itemCount++;
        }
        echo "匯入 {$returnNumber} - {$vendorName}\n";
    } catch (Exception $e) {
        $errorCount++;
        echo "❌ {$returnNumber}: " . $e->getMessage() . "\n";
    }
}

echo "\n===== 同步結果 =====\n";
echo "退貨單匯入: {$insertCount} 筆\n";
echo "明細匯入: {$itemCount} 筆\n";
echo "錯誤: {$errorCount} 筆\n";
echo '</pre>';
