<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('max_execution_time', 300);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
header('Content-Type: text/html; charset=utf-8');

$db = Database::getInstance();

// 確保欄位存在
foreach (array(
    "ALTER TABLE sales_invoices ADD COLUMN seller_tax_id VARCHAR(20) DEFAULT NULL",
    "ALTER TABLE sales_invoices ADD COLUMN seller_name VARCHAR(200) DEFAULT NULL",
) as $sql) {
    try { $db->exec($sql); } catch (Exception $e) { /* already exists */ }
}

echo "<h2>匯入銷項發票</h2>";

$jsonFile = __DIR__ . '/../data/sales_invoices_import.json';
if (!file_exists($jsonFile)) {
    echo "<p style='color:red'>JSON 檔案不存在</p>";
    exit;
}

$data = json_decode(file_get_contents($jsonFile), true);
echo "<p>讀取: " . count($data) . " 筆</p>";

$statusMap = array(
    '開立已確認' => 'confirmed',
    '作廢已確認' => 'voided',
    '空白發票'   => 'blank',
);

$inserted = 0;
$updated = 0;
$skipped = 0;

$checkStmt = $db->prepare("SELECT id FROM sales_invoices WHERE invoice_number = ?");
$insertStmt = $db->prepare("
    INSERT INTO sales_invoices (invoice_number, invoice_date, customer_tax_id, customer_name,
        seller_tax_id, seller_name, invoice_format, invoice_type, status,
        amount_untaxed, tax_amount, total_amount, tax_rate, report_period, note, period, created_by, created_at)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,5,?,?,?,1,NOW())
");
$updateStmt = $db->prepare("
    UPDATE sales_invoices SET invoice_date=?, customer_tax_id=?, customer_name=?,
        seller_tax_id=?, seller_name=?, invoice_format=?, invoice_type=?, status=?,
        amount_untaxed=?, tax_amount=?, total_amount=?, report_period=?, period=?, updated_at=NOW()
    WHERE invoice_number=?
");

foreach ($data as $row) {
    $invNumber = trim($row['發票號碼']);
    if (empty($invNumber)) { $skipped++; continue; }

    $dateStr = trim($row['發票日期']);
    $invDate = $dateStr ? substr($dateStr, 0, 10) : date('Y-m-d');
    $reportPeriod = $invDate ? substr($invDate, 0, 7) : null;
    // period 格式 YYYYMM
    $period = $invDate ? str_replace('-', '', substr($invDate, 0, 7)) : null;

    $customerTaxId = trim(str_replace('.0', '', strval($row['買方統一編號'])));
    $customerName = trim(strval($row['買方名稱']));
    $sellerTaxId = trim(str_replace('.0', '', strval($row['賣方統一編號'])));
    $sellerName = trim(strval($row['賣方名稱']));
    $invoiceFormat = trim(strval($row['格式代號']));
    $invoiceType = trim(strval($row['課稅別'])) ?: '應稅';
    $rawStatus = trim(strval($row['發票狀態']));
    $status = isset($statusMap[$rawStatus]) ? $statusMap[$rawStatus] : 'pending';

    $amountUntaxed = (int)(is_numeric($row['銷售額合計']) ? $row['銷售額合計'] : 0);
    $taxAmount = (int)(is_numeric($row['營業稅']) ? $row['營業稅'] : 0);
    $totalAmount = (int)(is_numeric($row['總計']) ? $row['總計'] : 0);
    $note = trim(strval($row['總備註'])) ?: null;

    $checkStmt->execute(array($invNumber));
    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $updateStmt->execute(array(
            $invDate, $customerTaxId, $customerName,
            $sellerTaxId, $sellerName, $invoiceFormat, $invoiceType, $status,
            $amountUntaxed, $taxAmount, $totalAmount, $reportPeriod, $period,
            $invNumber
        ));
        $updated++;
    } else {
        $insertStmt->execute(array(
            $invNumber, $invDate, $customerTaxId, $customerName,
            $sellerTaxId, $sellerName, $invoiceFormat, $invoiceType, $status,
            $amountUntaxed, $taxAmount, $totalAmount, $reportPeriod, $note, $period
        ));
        $inserted++;
    }
}

echo "<p style='color:green'>✅ 完成</p>";
echo "<ul>";
echo "<li>新增: <strong>{$inserted}</strong> 筆</li>";
echo "<li>更新: <strong>{$updated}</strong> 筆</li>";
echo "<li>跳過: <strong>{$skipped}</strong> 筆</li>";
echo "</ul>";
echo "<p><a href='/sales_invoices.php'>← 回銷項發票列表</a></p>";
