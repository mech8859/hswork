<?php
/**
 * 依 115年1-2月份統一發票扣抵聯明細表(禾順).xls 對進項發票作比對：
 *  - 發票號碼相同 → 更正未稅/稅額/含稅金額
 *  - 發票號碼不存在 → 新增，申報期間設為 2026-01-02（YYYY-MM-MM）、period=invoice_date 對應 YYYYMM
 *
 * 兩段式：
 *  預覽（不帶 confirm）→ 列出差異、將更新/新增的筆數
 *  執行（?confirm=1）
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();
$confirm = isset($_GET['confirm']) && $_GET['confirm'] === '1';

$jsonPath = __DIR__ . '/../data/excel_purchase_2026_01_02.json';
if (!is_file($jsonPath)) {
    echo "ERROR: 找不到資料檔 {$jsonPath}\n";
    exit;
}
$records = json_decode(file_get_contents($jsonPath), true);
if (!is_array($records)) {
    echo "ERROR: JSON 解析失敗\n";
    exit;
}
echo "Excel 筆數：" . count($records) . "\n";

// 先抓 DB 所有進項發票的號碼 index
$dbIdx = array();
$q = $db->query("SELECT id, invoice_number, amount_untaxed, tax_amount, total_amount,
                        invoice_date, vendor_tax_id, report_period, period
                 FROM purchase_invoices WHERE status != 'voided' AND invoice_number IS NOT NULL");
foreach ($q as $row) {
    $dbIdx[strtoupper(trim($row['invoice_number']))] = $row;
}
echo "DB 現有進項發票數：" . count($dbIdx) . "\n";
echo str_repeat('-', 80) . "\n";

$toUpdate = array();   // 需要更新金額
$toInsert = array();   // Excel 有但 DB 無
$noChange = 0;         // 金額完全一致

foreach ($records as $r) {
    $inv = strtoupper(trim($r['invoice_number']));
    if (!$inv) continue;
    if (isset($dbIdx[$inv])) {
        $dbRow = $dbIdx[$inv];
        $needU = (int)$dbRow['amount_untaxed'] !== (int)$r['amount_untaxed']
              || (int)$dbRow['tax_amount']     !== (int)$r['tax_amount']
              || (int)$dbRow['total_amount']   !== (int)$r['total_amount'];
        if ($needU) {
            $toUpdate[] = array('db' => $dbRow, 'excel' => $r);
        } else {
            $noChange++;
        }
    } else {
        $toInsert[] = $r;
    }
}

echo "比對結果：\n";
echo "  金額已一致：{$noChange}\n";
echo "  需更新金額：" . count($toUpdate) . "\n";
echo "  需新增發票：" . count($toInsert) . "\n";
echo str_repeat('-', 80) . "\n";

// 預覽樣本
if (!empty($toUpdate)) {
    echo "\n【需更新金額】前 15 筆：\n";
    foreach (array_slice($toUpdate, 0, 15) as $u) {
        $d = $u['db']; $e = $u['excel'];
        echo sprintf("  %s  未稅 %s→%s  稅 %s→%s  含稅 %s→%s\n",
            $d['invoice_number'],
            number_format($d['amount_untaxed']), number_format($e['amount_untaxed']),
            number_format($d['tax_amount']),     number_format($e['tax_amount']),
            number_format($d['total_amount']),   number_format($e['total_amount']));
    }
    if (count($toUpdate) > 15) echo "  ... 另有 " . (count($toUpdate) - 15) . " 筆\n";
}
if (!empty($toInsert)) {
    echo "\n【需新增發票】前 15 筆：\n";
    foreach (array_slice($toInsert, 0, 15) as $i) {
        echo sprintf("  %s  %s  統編 %s  未稅 %s  稅 %s  含稅 %s\n",
            $i['invoice_number'], $i['invoice_date'], $i['tax_id'] ?: '-',
            number_format($i['amount_untaxed']),
            number_format($i['tax_amount']),
            number_format($i['total_amount']));
    }
    if (count($toInsert) > 15) echo "  ... 另有 " . (count($toInsert) - 15) . " 筆\n";
}

if (!$confirm) {
    echo "\n⚠ 預覽模式，未執行寫入。\n";
    echo "確認無誤後請加 ?confirm=1 執行：\n";
    echo "https://hswork.com.tw/run_import_excel_purchase_2026_01_02.php?confirm=1\n";
    exit;
}

echo "\n=== 開始寫入 ===\n";
$db->beginTransaction();
try {
    $updStmt = $db->prepare("UPDATE purchase_invoices
        SET amount_untaxed = ?, tax_amount = ?, total_amount = ?
        WHERE id = ?");
    $updated = 0;
    foreach ($toUpdate as $u) {
        $updStmt->execute(array(
            (int)$u['excel']['amount_untaxed'],
            (int)$u['excel']['tax_amount'],
            (int)$u['excel']['total_amount'],
            (int)$u['db']['id'],
        ));
        $updated++;
    }

    $insStmt = $db->prepare("INSERT INTO purchase_invoices
        (invoice_number, invoice_date, vendor_tax_id, amount_untaxed, tax_amount, total_amount,
         tax_rate, invoice_type, deduction_type, period, report_period, status, created_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, 5, '應稅', 'deductible', ?, '2026-01-02', 'confirmed', ?, NOW())");
    $uid = Auth::id();
    $inserted = 0;
    foreach ($toInsert as $i) {
        $period = null;
        if (!empty($i['invoice_date']) && strtotime($i['invoice_date'])) {
            $period = date('Ym', strtotime($i['invoice_date']));
        }
        $insStmt->execute(array(
            $i['invoice_number'],
            $i['invoice_date'],
            $i['tax_id'] ?: null,
            (int)$i['amount_untaxed'],
            (int)$i['tax_amount'],
            (int)$i['total_amount'],
            $period,
            $uid,
        ));
        $inserted++;
    }
    $db->commit();
    echo "✓ 更新 {$updated} 筆\n";
    echo "✓ 新增 {$inserted} 筆（申報期間 2026-01-02）\n";
    echo "Done.\n";
} catch (Exception $ex) {
    $db->rollBack();
    echo "ERROR: " . $ex->getMessage() . "\n";
}
