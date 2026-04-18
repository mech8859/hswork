<?php
/**
 * 方案 A：依賣方名稱(vendor_name) 對應 vendors 表，回補 tax_id / vendor_id
 * 然後用同統編族群的模板補 invoice_format / deduction_type / deduction_category
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

// 1) vendors 表：name → {id, tax_id}
$nameIdx = array();        // 完全相符
$nameNormIdx = array();    // 去掉 公司/有限/股份/空白 後的比對索引
$vstmt = $db->query("SELECT id, TRIM(name) AS name, tax_id FROM vendors WHERE is_active = 1 AND name IS NOT NULL AND name != ''");
foreach ($vstmt as $v) {
    $n = trim($v['name']);
    if (!$n) continue;
    $nameIdx[$n] = array('id' => (int)$v['id'], 'tax_id' => trim($v['tax_id']));
    $norm = preg_replace('/[\s　]|股份有限公司|有限公司|股份公司|公司|分公司|企業社|工作室|工程行/u', '', $n);
    if ($norm && $norm !== $n && !isset($nameNormIdx[$norm])) {
        $nameNormIdx[$norm] = array('id' => (int)$v['id'], 'tax_id' => trim($v['tax_id']));
    }
}

// 2) 找無統編但有賣方名稱的發票
$targets = $db->query("
    SELECT id, invoice_number, vendor_name, vendor_tax_id, vendor_id
    FROM purchase_invoices
    WHERE status != 'voided'
      AND (vendor_tax_id IS NULL OR vendor_tax_id = '')
      AND vendor_name IS NOT NULL AND vendor_name != ''
")->fetchAll(PDO::FETCH_ASSOC);

$matched = array();
$unmatched = array();
foreach ($targets as $r) {
    $name = trim($r['vendor_name']);
    $hit = null;
    if (isset($nameIdx[$name])) $hit = $nameIdx[$name];
    else {
        $norm = preg_replace('/[\s　]|股份有限公司|有限公司|股份公司|公司|分公司|企業社|工作室|工程行/u', '', $name);
        if ($norm && isset($nameNormIdx[$norm])) $hit = $nameNormIdx[$norm];
    }
    if ($hit && !empty($hit['tax_id'])) {
        $matched[] = array('row' => $r, 'hit' => $hit);
    } else {
        $unmatched[] = $r;
    }
}

echo "需回補（無統編有名稱）：" . count($targets) . " 筆\n";
echo "  對應到 vendors：" . count($matched) . "\n";
echo "  查無對應：" . count($unmatched) . "\n";
echo str_repeat('-', 90) . "\n";

if (!empty($matched)) {
    echo "【可回補】前 20 筆：\n";
    foreach (array_slice($matched, 0, 20) as $m) {
        echo sprintf("  %s  %-25s → vendor_id=%d, tax_id=%s\n",
            $m['row']['invoice_number'], $m['row']['vendor_name'],
            $m['hit']['id'], $m['hit']['tax_id']);
    }
    if (count($matched) > 20) echo "  ... 另有 " . (count($matched) - 20) . " 筆\n";
}
if (!empty($unmatched)) {
    echo "\n【查無對應】前 15 筆（vendors 表無此名稱）：\n";
    foreach (array_slice($unmatched, 0, 15) as $u) {
        echo sprintf("  %s  %s\n", $u['invoice_number'], $u['vendor_name']);
    }
    if (count($unmatched) > 15) echo "  ... 另有 " . (count($unmatched) - 15) . " 筆\n";
}
echo str_repeat('-', 90) . "\n";

if (!$confirm) {
    echo "\n⚠ 預覽模式。確認後加 ?confirm=1 執行。\n";
    echo "https://hswork.com.tw/run_fill_purchase_by_name.php?confirm=1\n";
    exit;
}

echo "\n=== 開始寫入 ===\n";
$db->beginTransaction();
try {
    $upd = $db->prepare("UPDATE purchase_invoices SET vendor_tax_id = ?, vendor_id = ? WHERE id = ?");
    $cnt = 0;
    foreach ($matched as $m) {
        $upd->execute(array($m['hit']['tax_id'], $m['hit']['id'], (int)$m['row']['id']));
        $cnt++;
    }
    $db->commit();
    echo "✓ 已回補 {$cnt} 筆 tax_id / vendor_id\n";
    echo "\n建議接著再跑一次：\n";
    echo "  https://hswork.com.tw/run_fill_purchase_by_vendor.php?confirm=1\n";
    echo "  （用新填的 tax_id 找同群模板補 invoice_format / deduction_category）\n";
} catch (Exception $ex) {
    $db->rollBack();
    echo "ERROR: " . $ex->getMessage() . "\n";
}
