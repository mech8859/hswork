<?php
/**
 * 回填批次匯入的銀行交易編號（BT-YYYY-NNNNNN）
 * 依 transaction_date 年份分組，每年從 000001 開始，依 transaction_date, id 順序編號
 *
 * 使用方式：
 *   https://hswork.com.tw/run_fix_bank_transaction_numbers.php         (預覽)
 *   https://hswork.com.tw/run_fix_bank_transaction_numbers.php?go=1    (實際執行)
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');
header('Content-Type: text/plain; charset=utf-8');
set_time_limit(600);
ini_set('memory_limit', '512M');

$db = Database::getInstance();
$execute = isset($_GET['go']) && $_GET['go'] === '1';

echo "=== 銀行交易編號回填 ===\n";
echo $execute ? "模式：實際執行\n" : "模式：預覽（加 ?go=1 實際執行）\n";
echo "\n";

// 統計 NULL 筆數
$nullCount = (int)$db->query("SELECT COUNT(*) FROM bank_transactions WHERE transaction_number IS NULL OR transaction_number = ''")->fetchColumn();
$totalCount = (int)$db->query("SELECT COUNT(*) FROM bank_transactions")->fetchColumn();
echo "總筆數：{$totalCount}\n";
echo "待回填：{$nullCount}\n\n";

if ($nullCount === 0) {
    echo "無需回填，結束。\n";
    exit;
}

// 依年份取得現有最大序號（跳過已占用的號碼）
$maxByYear = array();
$maxStmt = $db->query("
    SELECT
      SUBSTRING_INDEX(SUBSTRING_INDEX(transaction_number, '-', 2), '-', -1) AS y,
      MAX(CAST(SUBSTRING_INDEX(transaction_number, '-', -1) AS UNSIGNED)) AS mx
    FROM bank_transactions
    WHERE transaction_number LIKE 'BT-____-%'
    GROUP BY y
");
foreach ($maxStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $maxByYear[$row['y']] = (int)$row['mx'];
}
echo "現有年度最大序號：\n";
foreach ($maxByYear as $y => $mx) {
    echo "  {$y} → " . str_pad($mx, 6, '0', STR_PAD_LEFT) . "\n";
}
echo "\n";

// 取 NULL 記錄（排序：先依 transaction_date，再依 id）
$stmt = $db->query("
    SELECT id, transaction_date
    FROM bank_transactions
    WHERE transaction_number IS NULL OR transaction_number = ''
    ORDER BY transaction_date ASC, id ASC
");
$targets = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "開始回填...\n";
$fixed = 0;
$failed = 0;
$skippedNoDate = 0;
$samples = array();

if ($execute) $db->beginTransaction();
try {
    $upd = $db->prepare("UPDATE bank_transactions SET transaction_number = ? WHERE id = ? AND (transaction_number IS NULL OR transaction_number = '')");
    foreach ($targets as $t) {
        $id = (int)$t['id'];
        $date = $t['transaction_date'];

        // 若無日期，使用該筆 id 對應 created_at 年份，再不行就今年
        if (empty($date)) {
            $year = date('Y');
            $skippedNoDate++;
        } else {
            $year = date('Y', strtotime($date));
        }

        if (!isset($maxByYear[$year])) $maxByYear[$year] = 0;
        $maxByYear[$year]++;
        $newNum = 'BT-' . $year . '-' . str_pad($maxByYear[$year], 6, '0', STR_PAD_LEFT);

        if ($execute) {
            $upd->execute(array($newNum, $id));
            if ($upd->rowCount() > 0) {
                $fixed++;
            } else {
                $failed++;
            }
        } else {
            $fixed++;
        }

        if (count($samples) < 10) {
            $samples[] = "  id={$id}  {$date}  →  {$newNum}";
        }
    }
    if ($execute) $db->commit();
} catch (Exception $e) {
    if ($execute && $db->inTransaction()) $db->rollBack();
    echo "ERROR: " . $e->getMessage() . "\n";
    exit;
}

echo "\n=== 範例（前 10 筆）===\n";
foreach ($samples as $s) echo $s . "\n";

echo "\n=== 結果 ===\n";
echo "回填成功：{$fixed}\n";
if ($failed > 0) echo "失敗：{$failed}\n";
if ($skippedNoDate > 0) echo "無日期改用今年：{$skippedNoDate}\n";

// 同步 number_sequences 的 last_sequence（以最大年份為準）
if ($execute && !empty($maxByYear)) {
    ksort($maxByYear);
    // PHP 7.2 相容：不使用 array_key_last
    $keys = array_keys($maxByYear);
    $lastYear = end($keys);
    $lastSeq = $maxByYear[$lastYear];
    try {
        $db->prepare("UPDATE number_sequences SET last_sequence = ?, last_reset_key = ?, updated_at = NOW() WHERE module = ?")
           ->execute(array($lastSeq, $lastYear, 'bank_transactions'));
        echo "\n已同步 number_sequences: bank_transactions last_sequence={$lastSeq}, last_reset_key={$lastYear}\n";
    } catch (Exception $e) {
        echo "同步 sequences 失敗: " . $e->getMessage() . "\n";
    }
}

echo "\nDone.\n";
