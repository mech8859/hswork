<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!in_array(Session::getUser()['role'], array('boss', 'vice_president'))) die('需要管理員權限');

set_time_limit(600);
header('Content-Type: text/html; charset=utf-8');
echo '<h2>Ragic 應付帳款 - 進貨明細同步</h2><pre>';

$db = Database::getInstance();

$jsonFile = __DIR__ . '/../database/ragic_payables_20260405.json';
if (!file_exists($jsonFile)) die('找不到 JSON');
$ragicData = json_decode(file_get_contents($jsonFile), true);
echo "Ragic: " . count($ragicData) . " 筆\n";

$branchMap = array();
$bStmt = $db->query('SELECT id, name FROM branches');
while ($b = $bStmt->fetch(PDO::FETCH_ASSOC)) $branchMap[$b['name']] = $b['id'];

// 載入系統應付帳款
$sysPayablesExact = array(); // vendor+period+amount → row
$sysPayablesLoose = array(); // vendor+period → [rows]
$stmt = $db->query('SELECT id, payable_number, vendor_name, payment_period, total_amount FROM payables');
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $exactKey = trim($r['vendor_name']) . '|' . trim($r['payment_period']) . '|' . (int)round((float)$r['total_amount']);
    $sysPayablesExact[$exactKey] = $r;
    $looseKey = trim($r['vendor_name']) . '|' . trim($r['payment_period']);
    if (!isset($sysPayablesLoose[$looseKey])) $sysPayablesLoose[$looseKey] = array();
    $sysPayablesLoose[$looseKey][] = $r;
}
echo "系統應付帳款: " . count($sysPayablesExact) . " 筆\n\n";

$parseNum = function($v) {
    if (!$v || !trim($v)) return 0;
    $v = str_replace(',', '', trim($v));
    return is_numeric($v) ? round((float)$v) : 0;
};

$matchCount = 0;
$noMatchCount = 0;
$itemCount = 0;
$errorCount = 0;

foreach ($ragicData as $ragicId => $r) {
    $vendor = trim($r['廠商名稱'] ?? '');
    if (!$vendor) { $noMatchCount++; continue; }

    // 對帳月份轉 YYYY-MM
    $periodRaw = trim($r['對帳月份'] ?? '');
    $period = '';
    if (preg_match('/^(\d{4})[\/\-](\d{2})/', $periodRaw, $m)) {
        $period = $m[1] . '-' . $m[2];
    }
    if (!$period) { $noMatchCount++; continue; }

    $totalAmount = $parseNum($r['應付含稅總額'] ?? '');

    $exactKey = $vendor . '|' . $period . '|' . $totalAmount;
    $looseKey = $vendor . '|' . $period;

    $matched = null;
    if (isset($sysPayablesExact[$exactKey])) {
        // 精確比對
        $matched = $sysPayablesExact[$exactKey];
    } elseif (isset($sysPayablesLoose[$looseKey]) && count($sysPayablesLoose[$looseKey]) === 1) {
        // 廠商+月份唯一 → 配對
        $matched = $sysPayablesLoose[$looseKey][0];
    }

    if (!$matched) {
        $noMatchCount++;
        echo "⚠ 找不到: {$vendor} | {$period} | \${$totalAmount}\n";
        continue;
    }

    $payableId = $matched['id'];
    $payableNumber = $matched['payable_number'];

    // 清空現有進貨明細 + 進退明細
    $db->prepare("DELETE FROM payable_purchase_details WHERE payable_id = ?")->execute(array($payableId));
    $db->prepare("DELETE FROM payable_return_details WHERE payable_id = ?")->execute(array($payableId));

    // 匯入進貨明細
    $sub = $r['_subtable_1004757'] ?? array();
    $insertPD = $db->prepare("INSERT INTO payable_purchase_details
        (payable_id, check_month, purchase_date, purchase_number, branch_name, vendor_name,
         amount_untaxed, tax_amount, total_amount, paid_amount, payment_date,
         invoice_date, invoice_track, invoice_amount, monthly_check, note, sort_order)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

    $sort = 0;
    foreach ($sub as $sItem) {
        $checkMonth = '';
        $cmRaw = trim($sItem['對帳月份'] ?? '');
        if (preg_match('/^(\d{4})[\/\-](\d{2})/', $cmRaw, $cm)) $checkMonth = $cm[1] . '-' . $cm[2];

        $purchaseDate = trim($sItem['進貨日期'] ?? '');
        if ($purchaseDate) $purchaseDate = str_replace('/', '-', $purchaseDate);

        $paymentDate = trim($sItem['付款日期'] ?? '');
        if ($paymentDate) $paymentDate = str_replace('/', '-', $paymentDate);

        $invoiceDate = '';
        $invoiceTrack = trim($sItem['發票字軌'] ?? '');

        try {
            $insertPD->execute(array(
                $payableId,
                $checkMonth ?: null,
                $purchaseDate ?: null,
                trim($sItem['進貨單號'] ?? '') ?: null,
                trim($sItem['分公司'] ?? $sItem['所屬分公司'] ?? '') ?: null,
                trim($sItem['廠商名稱'] ?? '') ?: null,
                $parseNum($sItem['進貨未稅金額'] ?? ''),
                $parseNum($sItem['進貨稅額'] ?? ''),
                $parseNum($sItem['進貨含稅金額'] ?? ''),
                $parseNum($sItem['已付金額'] ?? ''),
                $paymentDate ?: null,
                $invoiceDate ?: null,
                $invoiceTrack ?: null,
                $parseNum($sItem['發票金額'] ?? ''),
                trim($sItem['月結核對'] ?? '') ?: null,
                trim($sItem['備註'] ?? '') ?: null,
                $sort++,
            ));
            $itemCount++;
        } catch (Exception $e) {
            $errorCount++;
            echo "❌ {$payableNumber} 進貨: " . $e->getMessage() . "\n";
        }
    }

    // 匯入進退明細
    $subReturn = $r['_subtable_1008841'] ?? array();
    $insertRD = $db->prepare("INSERT INTO payable_return_details
        (payable_id, return_date, return_number, purchase_number, vendor_name, doc_status,
         branch_name, warehouse_name, refund_amount, return_reason, accounting_method, allowance_doc, sort_order)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");

    $rsort = 0;
    foreach ($subReturn as $rItem) {
        $returnDate = trim($rItem['退貨日期'] ?? '');
        if ($returnDate) $returnDate = str_replace('/', '-', $returnDate);

        try {
            $insertRD->execute(array(
                $payableId,
                $returnDate ?: null,
                trim($rItem['來源退回單號'] ?? '') ?: null,
                trim($rItem['來源進貨單號'] ?? '') ?: null,
                trim($rItem['廠商名稱'] ?? '') ?: null,
                trim($rItem['單據狀態'] ?? '') ?: null,
                trim($rItem['所屬分公司'] ?? '') ?: null,
                trim($rItem['倉庫名稱'] ?? '') ?: null,
                $parseNum($rItem['退款金額'] ?? ''),
                trim($rItem['退回原因'] ?? '') ?: null,
                trim($rItem['會計處理方式'] ?? '') ?: null,
                trim($rItem['折讓單據'] ?? '') ?: null,
                $rsort++,
            ));
            $itemCount++;
        } catch (Exception $e) {
            $errorCount++;
            echo "❌ {$payableNumber} 進退: " . $e->getMessage() . "\n";
        }
    }

    $matchCount++;
}

echo "\n===== 同步結果 =====\n";
echo "比對成功: {$matchCount} 筆\n";
echo "進貨明細匯入: {$itemCount} 筆\n";
echo "無法比對: {$noMatchCount} 筆\n";
echo "錯誤: {$errorCount} 筆\n";
echo '</pre>';
