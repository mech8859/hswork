<?php
/**
 * 第二輪清理：補 deduction_type 空值
 *  R5. deduction_type 空 + invoice_format IN (21,22,25) → deduction_type='deductible'
 *      若同時 deduction_category 也空/非標準 → 補 'deductible_purchase'
 *  R6. deduction_type 空 + invoice_format IN (23,24) → deduction_type='deductible' + deduction_category='deductible_purchase'
 *
 * 預覽：不帶 confirm；執行：?confirm=1
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();
$confirm = isset($_GET['confirm']) && $_GET['confirm'] === '1';

$stdDeductions = array('deductible_purchase','deductible_asset','non_deductible');

$rows = $db->query("
    SELECT id, invoice_number, deduction_type, deduction_category, invoice_format
    FROM purchase_invoices
    WHERE status != 'voided'
      AND (deduction_type IS NULL OR deduction_type = '')
      AND invoice_format IN ('21','22','23','24','25')
")->fetchAll(PDO::FETCH_ASSOC);

$r5 = array(); $r6 = array();
foreach ($rows as $r) {
    $dc = $r['deduction_category'] ?? '';
    $fmt = $r['invoice_format'];
    $needDc = ($dc === '' || !in_array($dc, $stdDeductions, true));
    if (in_array($fmt, array('21','22','25'), true)) {
        $r5[] = array('id' => (int)$r['id'], 'invoice_number' => $r['invoice_number'],
                      'fmt' => $fmt, 'need_dc' => $needDc);
    } elseif (in_array($fmt, array('23','24'), true)) {
        $r6[] = array('id' => (int)$r['id'], 'invoice_number' => $r['invoice_number'],
                      'fmt' => $fmt, 'need_dc' => $needDc);
    }
}

echo "R5（聯式 21/22/25 缺扣抵別）：" . count($r5) . " 筆 → deduction_type='deductible'\n";
echo "R6（聯式 23/24 缺扣抵別）：" . count($r6) . " 筆\n";
$needDcCount = 0;
foreach (array_merge($r5, $r6) as $r) if ($r['need_dc']) $needDcCount++;
echo "其中同時缺扣抵類別需補 deductible_purchase：{$needDcCount} 筆\n";
echo str_repeat('-', 80) . "\n";

foreach (array_slice(array_merge($r5, $r6), 0, 20) as $r) {
    echo sprintf("  %s  聯式 %s%s\n",
        $r['invoice_number'], $r['fmt'],
        $r['need_dc'] ? '  + 補扣抵類別' : '');
}
if (count($r5) + count($r6) > 20) echo "  ... 另有 " . (count($r5) + count($r6) - 20) . " 筆\n";

if (!$confirm) {
    echo "\n⚠ 預覽模式。確認後加 ?confirm=1 執行。\n";
    exit;
}

echo "\n=== 開始寫入 ===\n";
$db->beginTransaction();
try {
    $stmtBoth = $db->prepare("UPDATE purchase_invoices SET deduction_type = 'deductible', deduction_category = 'deductible_purchase' WHERE id = ?");
    $stmtType = $db->prepare("UPDATE purchase_invoices SET deduction_type = 'deductible' WHERE id = ?");
    $cntBoth = 0; $cntType = 0;
    foreach (array_merge($r5, $r6) as $r) {
        if ($r['need_dc']) { $stmtBoth->execute(array($r['id'])); $cntBoth++; }
        else { $stmtType->execute(array($r['id'])); $cntType++; }
    }
    $db->commit();
    echo "✓ 補 deduction_type + category：{$cntBoth} 筆\n";
    echo "✓ 僅補 deduction_type：{$cntType} 筆\n";
    echo "Done.\n";
} catch (Exception $ex) {
    $db->rollBack();
    echo "ERROR: " . $ex->getMessage() . "\n";
}
