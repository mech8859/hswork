<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!in_array(Session::getUser()['role'], array('boss', 'vice_president'))) die('需要管理員權限');

set_time_limit(300);
header('Content-Type: text/html; charset=utf-8');
echo '<h2>Ragic 採購單同步</h2><pre>';

$db = Database::getInstance();

$jsonFile = __DIR__ . '/../database/ragic_purchase_orders_20260405.json';
if (!file_exists($jsonFile)) die('找不到 JSON');
$ragicData = json_decode(file_get_contents($jsonFile), true);
echo "Ragic: " . count($ragicData) . " 筆\n";

$branchMap = array();
$bStmt = $db->query('SELECT id, name FROM branches');
while ($b = $bStmt->fetch(PDO::FETCH_ASSOC)) $branchMap[$b['name']] = $b['id'];

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
} catch (Exception $e) {}

$productMap = array();
$pStmt = $db->query('SELECT id, model, source_id FROM products');
while ($p = $pStmt->fetch(PDO::FETCH_ASSOC)) {
    if ($p['model']) $productMap[strtoupper(trim($p['model']))] = (int)$p['id'];
    if ($p['source_id']) $productMap[strtoupper(trim($p['source_id']))] = (int)$p['id'];
}

$existCount = (int)$db->query("SELECT COUNT(*) FROM purchase_orders")->fetchColumn();
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
$db->exec("DELETE FROM purchase_order_items");
$db->exec("DELETE FROM purchase_orders");
$db->exec("SET FOREIGN_KEY_CHECKS = 1");
echo "✓ 已清空\n\n";

$insertPO = $db->prepare("
    INSERT INTO purchase_orders
        (po_number, po_date, status, purchaser_name, requisition_number,
         case_name, branch_id, sales_name, urgency,
         vendor_id, vendor_name, vendor_tax_id, vendor_contact, vendor_email,
         payment_method, payment_terms, invoice_method, invoice_type,
         payment_date, is_paid, paid_amount,
         subtotal, tax_type, tax_rate, tax_amount, total_amount, this_amount,
         delivery_location, note,
         created_by, created_at, updated_by, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$insertItem = $db->prepare("
    INSERT INTO purchase_order_items
        (purchase_order_id, product_id, model, product_name, unit_price, quantity, amount, sort_order)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");

$insertCount = 0;
$itemCount = 0;
$errorCount = 0;

foreach ($ragicData as $ragicId => $r) {
    $poNumber = trim($r['採購單號'] ?? '');
    if (!$poNumber) { $poNumber = 'PO-RAGIC-' . $ragicId; }

    $branchName = trim($r['所屬分公司'] ?? '');
    $branchId = isset($branchMap[$branchName]) ? $branchMap[$branchName] : null;

    $purchaserName = trim($r['採購人員'] ?? '');
    $createdBy = isset($userMap[$purchaserName]) ? $userMap[$purchaserName] : Auth::id();
    $lastModifier = trim($r['最後修改人員'] ?? '');
    $lastModifiedBy = isset($userMap[$lastModifier]) ? $userMap[$lastModifier] : null;

    $vendorName = trim($r['廠商名稱'] ?? '');
    $vendorCode = trim($r['廠商編號'] ?? '');
    $vendorId = isset($vendorMap[$vendorCode]) ? $vendorMap[$vendorCode] : (isset($vendorMap[$vendorName]) ? $vendorMap[$vendorName] : null);

    $status = trim($r['狀態'] ?? '');
    $statusMap = array('確認進貨' => '已確認', '尚未進貨' => '待進貨', '取消採購' => '已取消');
    $mappedStatus = isset($statusMap[$status]) ? $statusMap[$status] : $status;

    $poDate = $parseDate($r['採購日期'] ?? '') ?: date('Y-m-d');
    $isPaid = (trim($r['是否已付款'] ?? '') === 'yes' || trim($r['是否已付款'] ?? '') === 'YES') ? 1 : 0;

    // 建檔時間
    $createdAtRaw = trim($r['建檔日期時間'] ?? '');
    $createdAt = $createdAtRaw ? str_replace('/', '-', $createdAtRaw) : null;
    $updatedAtRaw = trim($r['最後修改日期時間'] ?? '');
    $updatedAt = $updatedAtRaw ? str_replace('/', '-', $updatedAtRaw) : null;

    $taxRateStr = trim($r['稅率'] ?? '');
    $taxRate = preg_match('/(\d+)/', $taxRateStr, $m) ? (float)$m[1] : 5;

    try {
        $insertPO->execute(array(
            $poNumber, $poDate, $mappedStatus, $purchaserName ?: null,
            trim($r['來自請購單號'] ?? '') ?: null,
            trim($r['案名'] ?? '') ?: null, $branchId,
            trim($r['負責業務'] ?? '') ?: null, trim($r['緊急程度'] ?? '一般件'),
            $vendorId, $vendorName ?: null,
            trim($r['統一編號'] ?? '') ?: null,
            trim($r['聯絡人'] ?? '') ?: null,
            trim($r['E-mail'] ?? '') ?: null,
            trim($r['付款方式'] ?? '') ?: null,
            trim($r['付款條件'] ?? '') ?: null,
            trim($r['發票方式'] ?? '') ?: null,
            trim($r['發票種類'] ?? '') ?: null,
            $parseDate($r['付款日期'] ?? ''), $isPaid,
            $parseNum($r['付款金額'] ?? '') ?: null,
            $parseNum($r['小計'] ?? ''), trim($r['課稅別'] ?? '') ?: null,
            $taxRate, $parseNum($r['稅額'] ?? ''),
            $parseNum($r['合計金額'] ?? ''), $parseNum($r['本單金額'] ?? ''),
            trim($r['交貨地點'] ?? '') ?: null,
            trim($r['備註2'] ?? '') ?: null,
            $createdBy, $createdAt, $lastModifiedBy, $updatedAt,
        ));
        $poId = (int)$db->lastInsertId();
        $insertCount++;

        $sub = $r['_subtable_1008838'] ?? array();
        foreach ($sub as $sItem) {
            $model = trim($sItem['商品型號'] ?? '');
            $productName = trim($sItem['商品名稱'] ?? '');
            $productId = null;
            if ($model && isset($productMap[strtoupper($model)])) $productId = $productMap[strtoupper($model)];

            $qty = $parseNum($sItem['數量'] ?? '');
            $price = $parseNum($sItem['單價'] ?? '');
            $amount = $parseNum($sItem['金額'] ?? '');
            if (!$amount && $qty && $price) $amount = round($qty * $price, 2);
            $sortOrder = (int)($sItem['項次'] ?? 0);

            $insertItem->execute(array(
                $poId, $productId, $model ?: null, $productName ?: null,
                $price, $qty, $amount, $sortOrder,
            ));
            $itemCount++;
        }
        echo "匯入 {$poNumber}\n";
    } catch (Exception $e) {
        $errorCount++;
        echo "❌ {$poNumber}: " . $e->getMessage() . "\n";
    }
}

echo "\n===== 同步結果 =====\n";
echo "採購單匯入: {$insertCount} 筆\n";
echo "明細匯入: {$itemCount} 筆\n";
echo "錯誤: {$errorCount} 筆\n";
echo '</pre>';
