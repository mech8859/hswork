<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!in_array(Session::getUser()['role'], array('boss', 'vice_president'))) die('需要管理員權限');

set_time_limit(300);
header('Content-Type: text/html; charset=utf-8');
echo '<h2>Ragic 調撥單同步</h2><pre>';

$db = Database::getInstance();

$jsonFile = __DIR__ . '/../database/ragic_transfers_20260405.json';
if (!file_exists($jsonFile)) die('找不到 JSON');
$ragicData = json_decode(file_get_contents($jsonFile), true);
echo "Ragic: " . count($ragicData) . " 筆\n";

$branchMap = array();
$bStmt = $db->query('SELECT id, name FROM branches');
while ($b = $bStmt->fetch(PDO::FETCH_ASSOC)) $branchMap[$b['name']] = $b['id'];

$warehouseMap = array();
$wStmt = $db->query('SELECT id, name FROM warehouses');
while ($w = $wStmt->fetch(PDO::FETCH_ASSOC)) $warehouseMap[$w['name']] = $w['id'];
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

$existCount = (int)$db->query("SELECT COUNT(*) FROM warehouse_transfers")->fetchColumn();
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

echo "\n清空...\n";
$db->exec("SET FOREIGN_KEY_CHECKS = 0");
$db->exec("DELETE FROM warehouse_transfer_items");
$db->exec("DELETE FROM warehouse_transfers");
$db->exec("SET FOREIGN_KEY_CHECKS = 1");
echo "✓ 已清空\n\n";

$insertT = $db->prepare("
    INSERT INTO warehouse_transfers
        (transfer_number, transfer_date, from_branch_id, to_branch_id,
         from_warehouse_id, to_warehouse_id, from_warehouse_name, to_warehouse_name,
         status, shipper_name, receiver_name, total_amount, note,
         created_by, created_at, updated_by, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)
");

$insertItem = $db->prepare("
    INSERT INTO warehouse_transfer_items
        (transfer_id, product_id, model, product_name, quantity, unit_price, amount, sort_order)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");

$insertCount = 0;
$itemCount = 0;
$errorCount = 0;

foreach ($ragicData as $ragicId => $r) {
    $transferNumber = trim($r['調撥單號'] ?? '');
    if (!$transferNumber) { $transferNumber = 'ST-RAGIC-' . $ragicId; }

    $fromBranch = trim($r['所屬分公司-出貨'] ?? '');
    $fromBranchId = isset($branchMap[$fromBranch]) ? $branchMap[$fromBranch] : null;
    $toBranch = trim($r['所屬分公司-進貨'] ?? '');
    $toBranchId = isset($branchMap[$toBranch]) ? $branchMap[$toBranch] : null;

    $fromWhName = trim($r['倉庫名稱-出貨'] ?? '');
    $fromWhNum = trim($r['倉庫編號-出貨'] ?? '');
    $fromWhId = isset($warehouseMap[$fromWhName]) ? $warehouseMap[$fromWhName] : (isset($warehouseNumMap[$fromWhNum]) ? $warehouseNumMap[$fromWhNum] : null);

    $toWhName = trim($r['倉庫名稱-進貨'] ?? '');
    $toWhNum = trim($r['倉庫編號-進貨'] ?? '');
    $toWhId = isset($warehouseMap[$toWhName]) ? $warehouseMap[$toWhName] : (isset($warehouseNumMap[$toWhNum]) ? $warehouseNumMap[$toWhNum] : null);

    $shipperName = trim($r['出貨人員'] ?? '');
    $receiverName = trim($r['進貨人員'] ?? '');
    $totalAmount = $parseNum($r['合計'] ?? '');
    $note = trim($r['備註'] ?? '') ?: null;
    $transferDate = $parseDate($r['出貨日期'] ?? $r['建檔日期'] ?? '') ?: date('Y-m-d');
    $status = trim($r['單據狀態'] ?? '已確認');

    $creatorName = trim($r['建檔人員'] ?? '');
    $createdBy = isset($userMap[$creatorName]) ? $userMap[$creatorName] : Auth::id();
    $lastModifier = trim($r['最後修改人員'] ?? '');
    $lastModifiedBy = isset($userMap[$lastModifier]) ? $userMap[$lastModifier] : null;
    $lastModifiedDate = $parseDate($r['最後修改日期'] ?? '');

    try {
        $insertT->execute(array(
            $transferNumber, $transferDate, $fromBranchId, $toBranchId,
            $fromWhId, $toWhId, $fromWhName ?: null, $toWhName ?: null,
            $status, $shipperName ?: null, $receiverName ?: null, $totalAmount, $note,
            $createdBy, $lastModifiedBy, $lastModifiedDate,
        ));
        $tId = (int)$db->lastInsertId();
        $insertCount++;

        $sub = $r['_subtable_1009482'] ?? array();
        foreach ($sub as $sItem) {
            $model = trim($sItem['產品型號'] ?? '');
            $productKey = trim($sItem['產品ＫＥＹ'] ?? $sItem['產品KEY'] ?? '');
            $productName = trim($sItem['產品名稱'] ?? '');

            $productId = null;
            if ($model && isset($productMap[strtoupper($model)])) $productId = $productMap[strtoupper($model)];
            elseif ($productKey && isset($productMap[strtoupper($productKey)])) $productId = $productMap[strtoupper($productKey)];

            $qty = $parseNum($sItem['數量'] ?? '');
            $price = $parseNum($sItem['單價'] ?? '');
            $amount = $parseNum($sItem['小計'] ?? '');
            if (!$amount && $qty && $price) $amount = round($qty * $price, 2);
            $sortOrder = (int)($sItem['項次'] ?? 0);

            $insertItem->execute(array(
                $tId, $productId, $model ?: null, $productName ?: null,
                $qty, $price, $amount, $sortOrder,
            ));
            $itemCount++;
        }
    } catch (Exception $e) {
        $errorCount++;
        echo "❌ {$transferNumber}: " . $e->getMessage() . "\n";
    }
}

echo "\n===== 同步結果 =====\n";
echo "調撥單匯入: {$insertCount} 筆\n";
echo "明細匯入: {$itemCount} 筆\n";
echo "錯誤: {$errorCount} 筆\n";
echo '</pre>';
