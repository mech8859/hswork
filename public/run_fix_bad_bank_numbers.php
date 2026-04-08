<?php
/**
 * 清理 fallback 格式的銀行交易編號
 * 目標：transaction_number LIKE 'BANK_TRANSACTIONS-%' 或其他非 BT- 開頭的格式
 * 重新編號為正確的 BT-YYYY-NNNNNN 格式（接續現有最大序號）
 *
 * 使用方式：
 *   https://hswork.com.tw/run_fix_bad_bank_numbers.php         (預覽)
 *   https://hswork.com.tw/run_fix_bad_bank_numbers.php?go=1    (實際執行)
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');
header('Content-Type: text/plain; charset=utf-8');
set_time_limit(300);

$db = Database::getInstance();
$execute = isset($_GET['go']) && $_GET['go'] === '1';

echo "=== 清理 fallback 格式銀行交易編號 ===\n";
echo $execute ? "模式：實際執行\n\n" : "模式：預覽（加 ?go=1 實際執行）\n\n";

// 找出所有非 BT-YYYY-NNNNNN 格式的記錄
$badRows = $db->query("
    SELECT id, transaction_number, transaction_date
    FROM bank_transactions
    WHERE transaction_number IS NOT NULL
      AND transaction_number != ''
      AND transaction_number NOT REGEXP '^BT-[0-9]{4}-[0-9]{6}$'
    ORDER BY transaction_date ASC, id ASC
")->fetchAll(PDO::FETCH_ASSOC);

echo "異常格式筆數：" . count($badRows) . "\n\n";

if (empty($badRows)) {
    echo "無需清理，結束。\n";
    exit;
}

// 顯示異常範例
echo "=== 異常記錄 ===\n";
foreach ($badRows as $r) {
    echo "  id={$r['id']}  date={$r['transaction_date']}  舊編號={$r['transaction_number']}\n";
}
echo "\n";

// 依年份取得現有最大序號
$maxByYear = array();
$maxStmt = $db->query("
    SELECT
      SUBSTRING_INDEX(SUBSTRING_INDEX(transaction_number, '-', 2), '-', -1) AS y,
      MAX(CAST(SUBSTRING_INDEX(transaction_number, '-', -1) AS UNSIGNED)) AS mx
    FROM bank_transactions
    WHERE transaction_number REGEXP '^BT-[0-9]{4}-[0-9]{6}$'
    GROUP BY y
");
foreach ($maxStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $maxByYear[$row['y']] = (int)$row['mx'];
}

echo "=== 現有年度最大序號 ===\n";
foreach ($maxByYear as $y => $mx) {
    echo "  {$y} → " . str_pad($mx, 6, '0', STR_PAD_LEFT) . "\n";
}
echo "\n";

// 重新編號
echo "=== 重新編號 ===\n";
$fixed = 0;
if ($execute) $db->beginTransaction();
try {
    $upd = $db->prepare("UPDATE bank_transactions SET transaction_number = ? WHERE id = ?");
    foreach ($badRows as $r) {
        $id = (int)$r['id'];
        $date = $r['transaction_date'];
        $year = !empty($date) ? date('Y', strtotime($date)) : date('Y');

        if (!isset($maxByYear[$year])) $maxByYear[$year] = 0;
        $maxByYear[$year]++;
        $newNum = 'BT-' . $year . '-' . str_pad($maxByYear[$year], 6, '0', STR_PAD_LEFT);

        if ($execute) {
            $upd->execute(array($newNum, $id));
        }
        echo "  id={$id}  {$r['transaction_number']}  →  {$newNum}\n";
        $fixed++;
    }
    if ($execute) $db->commit();
} catch (Exception $e) {
    if ($execute && $db->inTransaction()) $db->rollBack();
    echo "ERROR: " . $e->getMessage() . "\n";
    exit;
}

echo "\n=== 結果 ===\n";
echo "修正成功：{$fixed}\n";

// 同步 number_sequences
if ($execute && !empty($maxByYear)) {
    ksort($maxByYear);
    $keys = array_keys($maxByYear);
    $lastYear = end($keys);
    $lastSeq = $maxByYear[$lastYear];
    try {
        $db->prepare("UPDATE number_sequences SET last_sequence = ?, last_reset_key = ?, updated_at = NOW() WHERE module = ?")
           ->execute(array($lastSeq, $lastYear, 'bank_transactions'));
        echo "已同步 number_sequences: last_sequence={$lastSeq}, last_reset_key={$lastYear}\n";
    } catch (Exception $e) {
        echo "同步 sequences 失敗: " . $e->getMessage() . "\n";
    }
}

echo "\nDone.\n";
