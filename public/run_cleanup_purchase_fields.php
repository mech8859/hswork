<?php
/**
 * 批次清理進項發票資料：
 *  R1. deduction_type='deductible' + invoice_format IN (21,22,25) + deduction_category 空/非標準 → 填 'deductible_purchase'
 *  R2. invoice_format = '發票' → 改為 '25'
 *  R3. deduction_category 為「物料」等中文 → 改為 'deductible_purchase'
 *  R4. deduction_type='deductible' + invoice_format IN (23,24) + deduction_category 非標準 → 'deductible_purchase'
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

$stdFormats = array('21','22','23','24','25');
$stdDeductions = array('deductible_purchase','deductible_asset','non_deductible');

$rows = $db->query("
    SELECT id, invoice_number, deduction_type, deduction_category, invoice_format
    FROM purchase_invoices
    WHERE status != 'voided'
")->fetchAll(PDO::FETCH_ASSOC);

$r1 = array(); $r2 = array(); $r3 = array(); $r4 = array();
foreach ($rows as $r) {
    $dt = $r['deduction_type'] ?? '';
    $dc = $r['deduction_category'] ?? '';
    $fmt = $r['invoice_format'] ?? '';
    $id = (int)$r['id'];

    // R2: invoice_format = '發票' 或其他非標準
    $r2Need = ($fmt !== '' && !in_array($fmt, $stdFormats, true));
    // R1 + R4: deductible + 標準聯式 + 缺類別或非標準
    $r1Need = ($dt === 'deductible' && in_array($fmt, array('21','22','25'), true)
               && ($dc === '' || !in_array($dc, $stdDeductions, true)));
    $r4Need = ($dt === 'deductible' && in_array($fmt, array('23','24'), true)
               && ($dc === '' || !in_array($dc, $stdDeductions, true)));
    // R3: 非標準中文扣抵類別
    $r3Need = ($dc !== '' && !in_array($dc, $stdDeductions, true));

    if ($r2Need) $r2[] = array('id' => $id, 'invoice_number' => $r['invoice_number'], 'old' => $fmt);
    if ($r1Need) $r1[] = array('id' => $id, 'invoice_number' => $r['invoice_number'], 'old' => $dc);
    if ($r4Need) $r4[] = array('id' => $id, 'invoice_number' => $r['invoice_number'], 'old' => $dc);
    if ($r3Need) $r3[] = array('id' => $id, 'invoice_number' => $r['invoice_number'], 'old' => $dc);
}

echo "規則預估：\n";
echo "  R1（缺類別的標準聯式 21/22/25）：" . count($r1) . " 筆 → deductible_purchase\n";
echo "  R2（invoice_format 非標準）：" . count($r2) . " 筆 → 25\n";
echo "  R3（deduction_category 非標準值）：" . count($r3) . " 筆 → deductible_purchase\n";
echo "  R4（缺類別的退出折讓 23/24）：" . count($r4) . " 筆 → deductible_purchase\n";
echo str_repeat('-', 80) . "\n";

if (!empty($r2)) {
    echo "\n【R2 聯式非標準值樣本】：\n";
    $samples = array();
    foreach ($r2 as $r) $samples[$r['old']] = true;
    echo "  出現的非標準值：" . implode(', ', array_map(function($v) { return '"' . $v . '"'; }, array_keys($samples))) . "\n";
    foreach (array_slice($r2, 0, 10) as $r) echo sprintf("  %s  %s → 25\n", $r['invoice_number'], $r['old']);
    if (count($r2) > 10) echo "  ... 另有 " . (count($r2) - 10) . " 筆\n";
}
if (!empty($r3)) {
    echo "\n【R3 扣抵類別非標準值樣本】：\n";
    $samples = array();
    foreach ($r3 as $r) $samples[$r['old']] = true;
    echo "  出現的非標準值：" . implode(', ', array_map(function($v) { return '"' . $v . '"'; }, array_keys($samples))) . "\n";
    foreach (array_slice($r3, 0, 10) as $r) echo sprintf("  %s  %s → deductible_purchase\n", $r['invoice_number'], $r['old']);
    if (count($r3) > 10) echo "  ... 另有 " . (count($r3) - 10) . " 筆\n";
}

if (!$confirm) {
    echo "\n⚠ 預覽模式。確認後加 ?confirm=1 執行。\n";
    exit;
}

echo "\n=== 開始寫入 ===\n";
$db->beginTransaction();
try {
    $stmtFmt = $db->prepare("UPDATE purchase_invoices SET invoice_format = '25' WHERE id = ?");
    $stmtDc = $db->prepare("UPDATE purchase_invoices SET deduction_category = 'deductible_purchase' WHERE id = ?");
    $cntR1 = $cntR2 = $cntR3 = $cntR4 = 0;
    foreach ($r2 as $r) { $stmtFmt->execute(array($r['id'])); $cntR2++; }
    // 去重：R1/R3/R4 可能重疊，同一 id 只更新一次 deduction_category
    $dcIds = array();
    foreach (array_merge($r1, $r3, $r4) as $r) $dcIds[$r['id']] = true;
    foreach (array_keys($dcIds) as $id) { $stmtDc->execute(array($id)); }
    $cntR1 = count($r1); $cntR3 = count($r3); $cntR4 = count($r4);
    $db->commit();
    echo "✓ R1 (聯式 21/22/25 缺類別) 範圍 {$cntR1} 筆\n";
    echo "✓ R2 (聯式非標準→25) {$cntR2} 筆\n";
    echo "✓ R3 (類別非標準→deductible_purchase) {$cntR3} 筆\n";
    echo "✓ R4 (聯式 23/24 缺類別) 範圍 {$cntR4} 筆\n";
    echo "實際更新扣抵類別獨立筆數：" . count($dcIds) . "\n";
    echo "Done.\n";
} catch (Exception $ex) {
    $db->rollBack();
    echo "ERROR: " . $ex->getMessage() . "\n";
}
