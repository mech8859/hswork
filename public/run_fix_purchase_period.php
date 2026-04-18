<?php
/**
 * 修正進項發票 period 欄位（應為 YYYYMM 6 碼）
 *  - 依 invoice_date 重新計算 period
 *  - 僅更新與正確值不符的記錄
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

$stmt = $db->query("
    SELECT id, invoice_number, invoice_date, period
    FROM purchase_invoices
    WHERE status != 'voided' AND invoice_date IS NOT NULL
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$toFix = array();
foreach ($rows as $r) {
    $ts = strtotime($r['invoice_date']);
    if (!$ts) continue;
    $expected = date('Ym', $ts);
    $current = (string)($r['period'] ?? '');
    if ($current !== $expected) {
        $toFix[] = array(
            'id' => (int)$r['id'],
            'invoice_number' => $r['invoice_number'],
            'invoice_date' => $r['invoice_date'],
            'old' => $current === '' ? '(空)' : $current,
            'new' => $expected,
        );
    }
}

echo "進項發票總數：" . count($rows) . "\n";
echo "需修正 period 的筆數：" . count($toFix) . "\n";
echo str_repeat('-', 80) . "\n";

foreach (array_slice($toFix, 0, 20) as $u) {
    echo sprintf("  %s  發票日期 %s  period %s → %s\n",
        $u['invoice_number'], $u['invoice_date'], $u['old'], $u['new']);
}
if (count($toFix) > 20) echo "  ... 另有 " . (count($toFix) - 20) . " 筆\n";
echo str_repeat('-', 80) . "\n";

// 也檢查銷項發票
echo "\n--- 銷項發票 ---\n";
$sStmt = $db->query("
    SELECT id, invoice_number, invoice_date, period
    FROM sales_invoices
    WHERE status != 'voided' AND invoice_date IS NOT NULL
");
$sRows = $sStmt->fetchAll(PDO::FETCH_ASSOC);
$sToFix = array();
foreach ($sRows as $r) {
    $ts = strtotime($r['invoice_date']);
    if (!$ts) continue;
    $expected = date('Ym', $ts);
    $current = (string)($r['period'] ?? '');
    if ($current !== $expected) {
        $sToFix[] = array('id' => (int)$r['id'], 'old' => $current, 'new' => $expected);
    }
}
echo "銷項發票總數：" . count($sRows) . "\n";
echo "需修正 period 的筆數：" . count($sToFix) . "\n";

if (!$confirm) {
    echo "\n⚠ 預覽模式。確認後加 ?confirm=1 執行。\n";
    echo "https://hswork.com.tw/run_fix_purchase_period.php?confirm=1\n";
    exit;
}

echo "\n=== 開始寫入 ===\n";
$db->beginTransaction();
try {
    $pStmt = $db->prepare("UPDATE purchase_invoices SET period = ? WHERE id = ?");
    foreach ($toFix as $u) {
        $pStmt->execute(array($u['new'], $u['id']));
    }
    $sStmt = $db->prepare("UPDATE sales_invoices SET period = ? WHERE id = ?");
    foreach ($sToFix as $u) {
        $sStmt->execute(array($u['new'], $u['id']));
    }
    $db->commit();
    echo "✓ 進項修正 " . count($toFix) . " 筆\n";
    echo "✓ 銷項修正 " . count($sToFix) . " 筆\n";
    echo "Done.\n";
} catch (Exception $ex) {
    $db->rollBack();
    echo "ERROR: " . $ex->getMessage() . "\n";
}
