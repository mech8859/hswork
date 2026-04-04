<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!in_array(Session::getUser()['role'], array('boss', 'vice_president'))) die('需要管理員權限');

set_time_limit(600);
ini_set('memory_limit', '512M');
header('Content-Type: text/html; charset=utf-8');
echo '<h2>Ragic 進項發票同步</h2><pre>';

$db = Database::getInstance();

$jsonFile = __DIR__ . '/../database/ragic_purchase_invoices_20260405.json';
if (!file_exists($jsonFile)) die('找不到 JSON');
$ragicData = json_decode(file_get_contents($jsonFile), true);
echo "Ragic: " . count($ragicData) . " 筆\n";

$userMap = array();
$uStmt = $db->query('SELECT id, real_name FROM users WHERE is_active = 1');
while ($u = $uStmt->fetch(PDO::FETCH_ASSOC)) $userMap[$u['real_name']] = $u['id'];

// 現有發票 by invoice_number
$existing = array();
$stmt = $db->query('SELECT id, invoice_number FROM purchase_invoices');
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if ($r['invoice_number']) $existing[$r['invoice_number']] = $r['id'];
}
echo "系統現有: " . count($existing) . " 筆\n\n";

$parseDate = function($v) {
    if (!$v || !trim($v)) return null;
    $v = str_replace('/', '-', trim($v));
    return preg_match('/^\d{4}-\d{2}-\d{2}/', $v) ? substr($v, 0, 10) : null;
};
$parseDateTime = function($v) {
    if (!$v || !trim($v)) return null;
    return str_replace('/', '-', trim($v));
};
$parseNum = function($v) {
    if (!$v || !trim($v)) return 0;
    $v = str_replace(',', '', trim($v));
    return is_numeric($v) ? round((float)$v, 2) : 0;
};

$insertCount = 0;
$updateCount = 0;
$deleteCount = 0;
$skipCount = 0;
$errorCount = 0;
$ragicInvoiceNumbers = array();

foreach ($ragicData as $ragicId => $r) {
    $invNumber = trim($r['發票字軌'] ?? '');
    if (!$invNumber) continue;
    $ragicInvoiceNumbers[$invNumber] = true;

    $invDate = $parseDate($r['發票日期'] ?? '');
    $vendorName = trim($r['廠商名稱'] ?? $r['抬頭1'] ?? '') ?: null;
    $vendorTaxId = trim($r['統編1'] ?? '') ?: null;
    $invoiceType = trim($r['發票種類'] ?? '') ?: null;
    $amountUntaxed = $parseNum($r['未稅金額'] ?? '');
    $taxAmount = $parseNum($r['稅額'] ?? '');
    $totalAmount = $parseNum($r['小計'] ?? '');
    if (!$totalAmount && $amountUntaxed) $totalAmount = $amountUntaxed + $taxAmount;
    $invoiceFormat = trim($r['單據類型'] ?? '') ?: null;
    $deductionCat = trim($r['屬性'] ?? '') ?: null;
    $statusRaw = trim($r['單據狀態'] ?? '');
    $statusMap = array('已確認' => 'confirmed', '待處理' => 'pending', '已作廢' => 'voided', '空白發票' => 'blank');
    $status = isset($statusMap[$statusRaw]) ? $statusMap[$statusRaw] : 'confirmed';

    $creatorName = trim($r['建立人員'] ?? '');
    $createdBy = isset($userMap[$creatorName]) ? $userMap[$creatorName] : null;
    $createdAt = $parseDateTime($r['建立日期'] ?? '');
    $modifierName = trim($r['修改人員'] ?? '');
    $updatedBy = isset($userMap[$modifierName]) ? $userMap[$modifierName] : null;
    $updatedAt = $parseDateTime($r['修改日期'] ?? '');

    if (isset($existing[$invNumber])) {
        // 更新
        try {
            $db->prepare("UPDATE purchase_invoices SET
                invoice_date=?, vendor_name=?, vendor_tax_id=?, invoice_type=?,
                amount_untaxed=?, tax_amount=?, total_amount=?,
                invoice_format=?, deduction_category=?, status=?,
                updated_by=?, updated_at=?
                WHERE id=?")->execute(array(
                $invDate, $vendorName, $vendorTaxId, $invoiceType,
                $amountUntaxed, $taxAmount, $totalAmount,
                $invoiceFormat, $deductionCat, $status,
                $updatedBy, $updatedAt,
                $existing[$invNumber],
            ));
            $updateCount++;
        } catch (Exception $e) {
            $errorCount++;
            echo "❌ 更新 {$invNumber}: " . $e->getMessage() . "\n";
        }
    } else {
        // 新增
        try {
            $db->prepare("INSERT INTO purchase_invoices
                (invoice_number, invoice_date, vendor_name, vendor_tax_id, invoice_type,
                 amount_untaxed, tax_amount, total_amount,
                 invoice_format, deduction_category, status,
                 created_by, created_at, updated_by, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")->execute(array(
                $invNumber, $invDate, $vendorName, $vendorTaxId, $invoiceType,
                $amountUntaxed, $taxAmount, $totalAmount,
                $invoiceFormat, $deductionCat, $status,
                $createdBy, $createdAt, $updatedBy, $updatedAt,
            ));
            $insertCount++;
        } catch (Exception $e) {
            $errorCount++;
            echo "❌ 新增 {$invNumber}: " . $e->getMessage() . "\n";
        }
    }
}

// 刪除 Ragic 沒有的
foreach ($existing as $num => $id) {
    if (!isset($ragicInvoiceNumbers[$num])) {
        $db->prepare("DELETE FROM purchase_invoices WHERE id = ?")->execute(array($id));
        $deleteCount++;
    }
}

echo "\n===== 同步結果 =====\n";
echo "新增: {$insertCount} 筆\n";
echo "更新: {$updateCount} 筆\n";
echo "刪除: {$deleteCount} 筆\n";
echo "錯誤: {$errorCount} 筆\n";
echo "Ragic: " . count($ragicData) . " 筆\n";
echo '</pre>';
