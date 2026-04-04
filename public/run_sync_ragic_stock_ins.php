<?php
/**
 * Ragic → hswork 入庫單同步
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
echo '<h2>Ragic 入庫單同步</h2><pre>';

$db = Database::getInstance();

// ===== 載入 Ragic =====
$jsonFile = __DIR__ . '/../database/ragic_stock_ins_20260405.json';
if (!file_exists($jsonFile)) die('找不到 JSON');
$ragicData = json_decode(file_get_contents($jsonFile), true);
echo "Ragic 入庫單: " . count($ragicData) . " 筆\n";

// ===== 對照表 =====
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

$productMap = array();
$pStmt = $db->query('SELECT id, model, source_id FROM products');
while ($p = $pStmt->fetch(PDO::FETCH_ASSOC)) {
    if ($p['model']) $productMap[strtoupper(trim($p['model']))] = (int)$p['id'];
    if ($p['source_id']) $productMap[strtoupper(trim($p['source_id']))] = (int)$p['id'];
}

$existCount = (int)$db->query("SELECT COUNT(*) FROM stock_ins")->fetchColumn();
$existItemCount = (int)$db->query("SELECT COUNT(*) FROM stock_in_items")->fetchColumn();
echo "系統現有: {$existCount} 筆, 明細: {$existItemCount} 筆\n";

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

// ===== 清空 =====
echo "\n清空系統入庫單...\n";
$db->exec("SET FOREIGN_KEY_CHECKS = 0");
$db->exec("DELETE FROM stock_in_items");
$db->exec("DELETE FROM stock_ins");
$db->exec("ALTER TABLE stock_ins AUTO_INCREMENT = 1");
$db->exec("ALTER TABLE stock_in_items AUTO_INCREMENT = 1");
$db->exec("SET FOREIGN_KEY_CHECKS = 1");
echo "✓ 已清空\n\n";

// ===== 匯入 =====
$insertSI = $db->prepare("
    INSERT INTO stock_ins
        (si_number, si_date, status, source_type, source_number, warehouse_id,
         branch_id, branch_name, customer_name, note, total_qty,
         created_by, created_at, updated_by, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)
");

$insertItem = $db->prepare("
    INSERT INTO stock_in_items
        (stock_in_id, product_id, model, product_name, unit, quantity, note, sort_order)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");

$insertCount = 0;
$itemCount = 0;
$errorCount = 0;

foreach ($ragicData as $ragicId => $r) {
    $siNumber = trim($r['歸還單號'] ?? '');
    if (!$siNumber) { $errorCount++; continue; }

    $branchName = trim($r['所屬分公司'] ?? '');
    $branchId = isset($branchMap[$branchName]) ? $branchMap[$branchName] : null;

    $warehouseNum = trim($r['倉庫編號'] ?? '');
    $warehouseId = isset($warehouseNumMap[$warehouseNum]) ? $warehouseNumMap[$warehouseNum] : null;
    if (!$warehouseId) {
        $whName = trim($r['倉庫名稱'] ?? '');
        $warehouseId = isset($warehouseMap[$whName]) ? $warehouseMap[$whName] : null;
    }

    $creatorName = trim($r['建檔人員'] ?? '');
    $createdBy = isset($userMap[$creatorName]) ? $userMap[$creatorName] : Auth::id();

    $lastModifier = trim($r['最後修改人員'] ?? '');
    $lastModifiedBy = isset($userMap[$lastModifier]) ? $userMap[$lastModifier] : null;
    $lastModifiedDate = $parseDate($r['最後修改日期'] ?? '');

    $status = trim($r['單據狀態'] ?? '');
    $mappedStatus = ($status === '已入庫') ? '已確認' : (($status === '已取消') ? '已取消' : '待確認');

    $sourceNumber = trim($r['來源出貨單號'] ?? '') ?: null;
    $sourceType = $sourceNumber ? 'return_material' : null;
    $note = trim($r['備註'] ?? '') ?: null;
    $siDate = $parseDate($r['歸還日期'] ?? '') ?: date('Y-m-d');

    $sub = $r['_subtable_1008885'] ?? array();
    $totalQty = 0;
    foreach ($sub as $item) {
        $totalQty += $parseNum($item['歸還數量'] ?? '');
    }

    try {
        $customerName = trim($r['客戶名稱'] ?? '');
        if (!$customerName) $customerName = trim($r['客戶名稱(暫用)'] ?? '');

        $insertSI->execute(array(
            $siNumber, $siDate, $mappedStatus, $sourceType, $sourceNumber, $warehouseId,
            $branchId, $branchName ?: null, $customerName ?: null, $note, $totalQty,
            $createdBy, $lastModifiedBy, $lastModifiedDate,
        ));
        $siId = (int)$db->lastInsertId();
        $insertCount++;

        foreach ($sub as $sItem) {
            $model = trim($sItem['商品型號'] ?? '');
            $productKey = trim($sItem['商品KEY'] ?? '');
            $productName = trim($sItem['商品名稱'] ?? '');

            $productId = null;
            if ($model && isset($productMap[strtoupper($model)])) $productId = $productMap[strtoupper($model)];
            elseif ($productKey && isset($productMap[strtoupper($productKey)])) $productId = $productMap[strtoupper($productKey)];

            $qty = $parseNum($sItem['歸還數量'] ?? '');
            $serial = trim($sItem['序號'] ?? '') ?: null;
            $sortOrder = (int)($sItem['項次'] ?? 0);

            $insertItem->execute(array(
                $siId, $productId, $model ?: null, $productName ?: null,
                null, $qty, $serial, $sortOrder,
            ));
            $itemCount++;
        }
    } catch (Exception $e) {
        $errorCount++;
        echo "❌ {$siNumber}: " . $e->getMessage() . "\n";
    }
}

echo "\n===== 同步結果 =====\n";
echo "入庫單匯入: {$insertCount} 筆\n";
echo "明細匯入: {$itemCount} 筆\n";
echo "錯誤: {$errorCount} 筆\n";
echo '</pre>';
