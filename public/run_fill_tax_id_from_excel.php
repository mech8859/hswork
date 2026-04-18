<?php
/**
 * 用 Excel 資料（發票號碼 → 統編對應）回補進項發票缺的 vendor_tax_id
 *  - 不改 invoice_date、金額、其他欄位
 *  - 發票號碼需唯一：若 DB 有重複 invoice_number 會列出警告不寫入
 *
 * 預覽：不帶 confirm
 * 執行：?confirm=1
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();
$confirm = isset($_GET['confirm']) && $_GET['confirm'] === '1';

$jsonPath = __DIR__ . '/../data/excel_purchase_2026_01_02.json';
if (!is_file($jsonPath)) {
    echo "ERROR: 找不到 {$jsonPath}\n";
    exit;
}
$records = json_decode(file_get_contents($jsonPath), true);
$excelIdx = array();
foreach ($records as $r) {
    $inv = strtoupper(trim($r['invoice_number']));
    if ($inv && !empty($r['tax_id'])) {
        $excelIdx[$inv] = trim($r['tax_id']);
    }
}
echo "Excel 可對應的 (號碼→統編) 筆數：" . count($excelIdx) . "\n";

// 找 DB 中 invoice_number 重複的（不應該有）
$dups = $db->query("
    SELECT invoice_number, COUNT(*) AS c
    FROM purchase_invoices
    WHERE invoice_number IS NOT NULL AND invoice_number != ''
    GROUP BY invoice_number HAVING c > 1
")->fetchAll(PDO::FETCH_ASSOC);
$dupSet = array();
if (!empty($dups)) {
    echo "\n⚠ DB 有重複發票號碼（" . count($dups) . " 組）：\n";
    foreach ($dups as $d) {
        echo "  {$d['invoice_number']} (x{$d['c']})\n";
        $dupSet[$d['invoice_number']] = true;
    }
    echo "  → 這些號碼的發票會跳過不寫入\n";
}

// 找 DB 中缺 vendor_tax_id 的發票
$stmt = $db->query("
    SELECT id, invoice_number, invoice_date, vendor_tax_id, vendor_name
    FROM purchase_invoices
    WHERE status != 'voided'
      AND (vendor_tax_id IS NULL OR vendor_tax_id = '')
      AND invoice_number IS NOT NULL AND invoice_number != ''
");
$targets = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "\nDB 中缺 vendor_tax_id 的發票：" . count($targets) . " 筆\n";
echo str_repeat('-', 80) . "\n";

$toFill = array();
$noMatch = array();
$skipDup = array();
foreach ($targets as $r) {
    $inv = strtoupper(trim($r['invoice_number']));
    if (isset($dupSet[$inv])) {
        $skipDup[] = $r;
        continue;
    }
    if (isset($excelIdx[$inv])) {
        $toFill[] = array('row' => $r, 'tax_id' => $excelIdx[$inv]);
    } else {
        $noMatch[] = $r;
    }
}

echo "  可回補（Excel 有對應）：" . count($toFill) . "\n";
echo "  查無對應（Excel 沒此號碼）：" . count($noMatch) . "\n";
echo "  因號碼重複跳過：" . count($skipDup) . "\n";
echo str_repeat('-', 80) . "\n";

if (!empty($toFill)) {
    echo "\n【可回補】前 15 筆：\n";
    foreach (array_slice($toFill, 0, 15) as $f) {
        echo sprintf("  %s  %s  → tax_id=%s\n",
            $f['row']['invoice_number'], $f['row']['invoice_date'], $f['tax_id']);
    }
    if (count($toFill) > 15) echo "  ... 另有 " . (count($toFill) - 15) . " 筆\n";
}

if (!$confirm) {
    echo "\n⚠ 預覽模式。確認後加 ?confirm=1 執行。\n";
    echo "https://hswork.com.tw/run_fill_tax_id_from_excel.php?confirm=1\n";
    exit;
}

echo "\n=== 開始寫入 ===\n";
$db->beginTransaction();
try {
    $upd = $db->prepare("UPDATE purchase_invoices SET vendor_tax_id = ? WHERE id = ?");
    $cnt = 0;
    foreach ($toFill as $f) {
        $upd->execute(array($f['tax_id'], (int)$f['row']['id']));
        $cnt++;
    }
    $db->commit();
    echo "✓ 已回補 {$cnt} 筆 vendor_tax_id\n";
    echo "\n接著可再跑一次：\n";
    echo "  https://hswork.com.tw/run_fill_purchase_by_vendor.php?confirm=1\n";
    echo "  用新填的 tax_id 找模板補 vendor_name / invoice_format / deduction_category\n";
} catch (Exception $ex) {
    $db->rollBack();
    echo "ERROR: " . $ex->getMessage() . "\n";
}
