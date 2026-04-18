<?php
/**
 * 第三輪清理：用 SQL 直接找聯式已標但扣抵類別/扣抵別不對的所有殘留
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();
$confirm = isset($_GET['confirm']) && $_GET['confirm'] === '1';

// 先列出目前還有問題的（聯式在 21-25 但類別/扣抵別有異狀）
$stmt = $db->query("
    SELECT id, invoice_number, invoice_format, deduction_type, deduction_category
    FROM purchase_invoices
    WHERE status != 'voided'
      AND invoice_format IN ('21','22','23','24','25')
      AND (
            deduction_category IS NULL
         OR TRIM(deduction_category) = ''
         OR TRIM(deduction_category) NOT IN ('deductible_purchase','deductible_asset','non_deductible')
         OR deduction_type IS NULL
         OR TRIM(deduction_type) = ''
         OR TRIM(deduction_type) NOT IN ('deductible','non_deductible')
      )
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "聯式 21-25 但扣抵別/類別有異狀：" . count($rows) . " 筆\n";
echo str_repeat('-', 80) . "\n";

foreach (array_slice($rows, 0, 20) as $r) {
    echo sprintf("  %s  聯式=%s  扣抵別=\"%s\"  類別=\"%s\"\n",
        $r['invoice_number'], $r['invoice_format'],
        $r['deduction_type'] ?? 'NULL', $r['deduction_category'] ?? 'NULL');
}
if (count($rows) > 20) echo "  ... 另有 " . (count($rows) - 20) . " 筆\n";

if (!$confirm) {
    echo "\n⚠ 預覽模式。確認後加 ?confirm=1 執行。\n";
    exit;
}

echo "\n=== 開始寫入 ===\n";
// 直接用 SQL 強制規一所有聯式 21-25 的資料
// 23/24 是退出折讓，其他也視為 deductible_purchase（可扣抵進貨費用）
$sql1 = "UPDATE purchase_invoices
         SET deduction_type = 'deductible'
         WHERE status != 'voided'
           AND invoice_format IN ('21','22','23','24','25')
           AND (deduction_type IS NULL OR TRIM(deduction_type) = ''
                OR TRIM(deduction_type) NOT IN ('deductible','non_deductible'))";
$n1 = $db->exec($sql1);

$sql2 = "UPDATE purchase_invoices
         SET deduction_category = 'deductible_purchase'
         WHERE status != 'voided'
           AND invoice_format IN ('21','22','23','24','25')
           AND deduction_type = 'deductible'
           AND (deduction_category IS NULL OR TRIM(deduction_category) = ''
                OR TRIM(deduction_category) NOT IN ('deductible_purchase','deductible_asset','non_deductible'))";
$n2 = $db->exec($sql2);

echo "✓ 補 deduction_type：{$n1} 筆\n";
echo "✓ 補 deduction_category：{$n2} 筆\n";
echo "Done.\n";
