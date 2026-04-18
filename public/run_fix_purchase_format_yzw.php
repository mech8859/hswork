<?php
/**
 * 批次更正：進項發票號碼開頭為 Y/Z/W 的，聯式改為 25
 *  預覽：先不帶 confirm 參數
 *  執行：加 ?confirm=1
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');
$db = Database::getInstance();
header('Content-Type: text/plain; charset=utf-8');

$confirm = isset($_GET['confirm']) && $_GET['confirm'] === '1';

// 先抓符合條件且聯式不為 25 的發票
$stmt = $db->query("
    SELECT id, invoice_number, invoice_date, vendor_name, invoice_format
    FROM purchase_invoices
    WHERE status != 'voided'
      AND invoice_number REGEXP '^[YZWyzw]'
      AND (invoice_format IS NULL OR invoice_format != '25')
    ORDER BY invoice_date ASC
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total = count($rows);

echo "符合條件（發票號碼開頭為 Y/Z/W 且聯式非 25）共 {$total} 筆\n";
echo str_repeat('-', 80) . "\n";

// 顯示前 20 筆供檢視
$preview = array_slice($rows, 0, 20);
foreach ($preview as $r) {
    $cur = $r['invoice_format'] ?: '(空)';
    echo sprintf("  %s  %s  %s  目前聯式=%s → 25\n",
        $r['invoice_number'], $r['invoice_date'], $r['vendor_name'], $cur);
}
if ($total > 20) echo "  ... 另有 " . ($total - 20) . " 筆未列出\n";
echo str_repeat('-', 80) . "\n";

if (!$confirm) {
    echo "\n⚠ 目前為預覽模式，未執行更新。\n";
    echo "確認無誤後請在網址加 ?confirm=1 再執行：\n";
    echo "https://hswork.com.tw/run_fix_purchase_format_yzw.php?confirm=1\n";
    exit;
}

// 執行更新
$upd = $db->prepare("UPDATE purchase_invoices SET invoice_format = '25' WHERE id = ?");
$updated = 0;
foreach ($rows as $r) {
    $upd->execute(array($r['id']));
    $updated++;
}

echo "\n✓ 已更新 {$updated} 筆進項發票的聯式為 25\n";
echo "Done.\n";
