<?php
/**
 * 重算所有進項發票的申報期間（report_period）
 *  - 依 invoice_date 對應的 401 兩月一期規則填入
 *  - 格式：YYYY-MM-MM（例：2025-11-12）
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();

// 取得所有有 invoice_date 的進項發票
$stmt = $db->query("SELECT id, invoice_number, invoice_date, report_period FROM purchase_invoices WHERE invoice_date IS NOT NULL");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = count($rows);
$updated = 0;
$skipped = 0;
$invalid = 0;

$upd = $db->prepare("UPDATE purchase_invoices SET report_period = ? WHERE id = ?");

foreach ($rows as $r) {
    $ts = strtotime($r['invoice_date']);
    if (!$ts) {
        $invalid++;
        continue;
    }
    $y = (int)date('Y', $ts);
    $m = (int)date('n', $ts);
    // 兩月一期：奇數月 = m,m+1；偶數月 = m-1,m
    if ($m % 2 === 1) {
        $p1 = $m; $p2 = $m + 1;
    } else {
        $p1 = $m - 1; $p2 = $m;
    }
    $newPeriod = sprintf('%04d-%02d-%02d', $y, $p1, $p2);

    if ($r['report_period'] === $newPeriod) {
        $skipped++;
        continue;
    }
    $upd->execute(array($newPeriod, $r['id']));
    $updated++;
}

echo "共 {$total} 筆\n";
echo "更新: {$updated}\n";
echo "無需變更: {$skipped}\n";
echo "日期無效: {$invalid}\n";
echo "Done.\n";
