<?php
/**
 * Excel vs DB 對帳（2026/1-2 進項發票）
 *  - DB 來源：purchase_invoices WHERE period IN (202601,202602) AND deduction_type='deductible' AND status != 'voided'
 *  - Excel 來源：data/excel_purchase_2026_01_02.json（402 筆）
 *  - 三類差異：DB 多 / Excel 多 / 金額不符
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();
$jsonPath = __DIR__ . '/../data/excel_purchase_2026_01_02.json';
$excel = json_decode(file_get_contents($jsonPath), true);

$excelIdx = array();
foreach ($excel as $r) {
    $inv = strtoupper(trim($r['invoice_number']));
    if ($inv) $excelIdx[$inv] = $r;
}

$dbStmt = $db->query("
    SELECT id, invoice_number, invoice_date, vendor_tax_id, vendor_name,
           amount_untaxed, tax_amount, total_amount, invoice_format, deduction_category, period
    FROM purchase_invoices
    WHERE period IN ('202601','202602') AND deduction_type = 'deductible' AND status != 'voided'
");
$dbRows = $dbStmt->fetchAll(PDO::FETCH_ASSOC);

$dbIdx = array();
foreach ($dbRows as $r) {
    $inv = strtoupper(trim($r['invoice_number']));
    if ($inv) $dbIdx[$inv] = $r;
}

echo "【對帳範圍】\n";
echo "  DB 可扣抵進項（period 202601/202602）：" . count($dbIdx) . " 筆\n";
echo "  Excel：" . count($excelIdx) . " 筆\n";

$dbSumTax = array_sum(array_column($dbRows, 'tax_amount'));
$dbSumU = array_sum(array_column($dbRows, 'amount_untaxed'));
$exSumTax = 0; $exSumU = 0;
foreach ($excelIdx as $e) { $exSumTax += (int)$e['tax_amount']; $exSumU += (int)$e['amount_untaxed']; }

echo sprintf("  DB 未稅合計 $%s  稅額合計 $%s\n", number_format($dbSumU), number_format($dbSumTax));
echo sprintf("  Excel 未稅合計 $%s  稅額合計 $%s\n", number_format($exSumU), number_format($exSumTax));
echo sprintf("  差額：未稅 $%s  稅額 $%s\n",
    number_format($dbSumU - $exSumU), number_format($dbSumTax - $exSumTax));
echo str_repeat('=', 90) . "\n";

// 1. DB 有但 Excel 沒的
$onlyDb = array();
foreach ($dbIdx as $inv => $r) {
    if (!isset($excelIdx[$inv])) $onlyDb[] = $r;
}
echo "\n【DB 多出的發票】" . count($onlyDb) . " 筆（Excel 中無此號碼）\n";
$onlyDbTax = 0;
foreach ($onlyDb as $r) $onlyDbTax += (int)$r['tax_amount'];
echo sprintf("  稅額合計：$%s\n", number_format($onlyDbTax));
echo str_repeat('-', 90) . "\n";
foreach (array_slice($onlyDb, 0, 30) as $r) {
    echo sprintf("  %s  %s  %-20s  未 %s  稅 %s\n",
        $r['invoice_number'], $r['invoice_date'], mb_substr((string)$r['vendor_name'], 0, 18),
        number_format($r['amount_untaxed']), number_format($r['tax_amount']));
}
if (count($onlyDb) > 30) echo "  ... 另有 " . (count($onlyDb) - 30) . " 筆\n";

// 2. Excel 有但 DB 沒的
$onlyEx = array();
foreach ($excelIdx as $inv => $r) {
    if (!isset($dbIdx[$inv])) $onlyEx[] = $r;
}
echo "\n【Excel 有但 DB 沒的】" . count($onlyEx) . " 筆\n";
$onlyExTax = 0;
foreach ($onlyEx as $r) $onlyExTax += (int)$r['tax_amount'];
echo sprintf("  稅額合計：$%s\n", number_format($onlyExTax));
echo str_repeat('-', 90) . "\n";
foreach (array_slice($onlyEx, 0, 20) as $r) {
    echo sprintf("  %s  %s  統編 %-10s  未 %s  稅 %s\n",
        $r['invoice_number'], $r['invoice_date'], $r['tax_id'] ?: '-',
        number_format($r['amount_untaxed']), number_format($r['tax_amount']));
}
if (count($onlyEx) > 20) echo "  ... 另有 " . (count($onlyEx) - 20) . " 筆\n";

// 3. 雙方都有但金額不符
$mismatch = array();
foreach ($dbIdx as $inv => $d) {
    if (!isset($excelIdx[$inv])) continue;
    $e = $excelIdx[$inv];
    if ((int)$d['amount_untaxed'] !== (int)$e['amount_untaxed']
        || (int)$d['tax_amount'] !== (int)$e['tax_amount']
        || (int)$d['total_amount'] !== (int)$e['total_amount']) {
        $mismatch[] = array('db' => $d, 'ex' => $e);
    }
}
echo "\n【金額不符】" . count($mismatch) . " 筆\n";
echo str_repeat('-', 90) . "\n";
foreach (array_slice($mismatch, 0, 20) as $m) {
    $d = $m['db']; $e = $m['ex'];
    echo sprintf("  %s  DB: 未稅 %s 稅 %s 含稅 %s  ↔  Excel: 未稅 %s 稅 %s 含稅 %s\n",
        $d['invoice_number'],
        number_format($d['amount_untaxed']), number_format($d['tax_amount']), number_format($d['total_amount']),
        number_format($e['amount_untaxed']), number_format($e['tax_amount']), number_format($e['total_amount']));
}
if (count($mismatch) > 20) echo "  ... 另有 " . (count($mismatch) - 20) . " 筆\n";

echo "\n=== 對帳總結 ===\n";
echo "- DB 比 Excel 多出的稅額：$" . number_format($onlyDbTax) . "\n";
echo "- Excel 比 DB 多出的稅額：$" . number_format($onlyExTax) . "\n";
echo "- 預期差額（DB-Excel）：$" . number_format($onlyDbTax - $onlyExTax) . "\n";
echo "- 實際差額（DB-Excel）：$" . number_format($dbSumTax - $exSumTax) . "\n";
