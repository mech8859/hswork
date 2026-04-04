<?php
/**
 * 銀行帳務明細 - Ragic JSON 同步
 * JSON 來源：瀏覽器抓取 bank-statement/1~4，上傳為 ragic_bank_transactions.json
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Session::getUser()['role'] !== 'boss') {
    die('需要管理員權限');
}
header('Content-Type: text/html; charset=utf-8');
set_time_limit(600);
ini_set('memory_limit', '512M');
echo '<h2>銀行帳務明細 - Ragic 同步</h2><pre>';

$db = Database::getInstance();
$execute = isset($_GET['execute']) && $_GET['execute'] == '1';
echo $execute ? "=== 執行模式 ===\n\n" : "=== 預覽模式 === (加 ?execute=1 執行)\n\n";

// 確保 ragic_id 欄位存在
try {
    $db->exec("ALTER TABLE bank_transactions ADD COLUMN IF NOT EXISTS ragic_id INT UNSIGNED DEFAULT NULL");
} catch (PDOException $e) {}
try {
    $db->exec("ALTER TABLE bank_transactions ADD INDEX IF NOT EXISTS idx_ragic_id (ragic_id)");
} catch (PDOException $e) {}

// 讀取 JSON
$jsonPath = __DIR__ . '/../ragic_bank_transactions.json';
if (!file_exists($jsonPath)) {
    die("ragic_bank_transactions.json 不存在\n請先從 Ragic 瀏覽器抓取資料並上傳\n");
}
$records = json_decode(file_get_contents($jsonPath), true);
if (!$records) {
    die("JSON 解析失敗\n");
}
echo "Ragic JSON 共 " . count($records) . " 筆\n";

function parseDate($v) {
    if (empty($v)) return null;
    $v = str_replace('/', '-', trim($v));
    $ts = strtotime($v);
    return $ts ? date('Y-m-d', $ts) : null;
}

function parseAmount($v) {
    if (empty($v) || $v === '0') return 0;
    return (int)preg_replace('/[^0-9\-]/', '', $v);
}

function cleanStr($v) {
    if (empty($v)) return null;
    $v = trim($v);
    return $v === '' ? null : $v;
}

// 取得 hswork 現有資料（用 upload_number 比對）
$existingStmt = $db->query("SELECT id, upload_number, ragic_id FROM bank_transactions");
$existingMap = array();
$existingRagicMap = array();
foreach ($existingStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if ($row['upload_number']) {
        $existingMap[$row['upload_number']] = $row['id'];
    }
    if ($row['ragic_id']) {
        $existingRagicMap[$row['ragic_id']] = $row['id'];
    }
}
echo "hswork 現有 " . count($existingMap) . " 筆\n\n";

// 按銀行分組統計
$sheetCounts = array();
foreach ($records as $r) {
    $sheet = isset($r['_sheet']) ? $r['_sheet'] : '未知';
    if (!isset($sheetCounts[$sheet])) $sheetCounts[$sheet] = 0;
    $sheetCounts[$sheet]++;
}
foreach ($sheetCounts as $name => $cnt) {
    echo "  {$name}: {$cnt} 筆\n";
}
echo "\n";

$imported = 0;
$updated = 0;
$skipped = 0;
$errors = 0;
$allRagicUploadNumbers = array();

foreach ($records as $idx => $r) {
    $num = $idx + 1;
    $uploadNumber = cleanStr(isset($r['上傳編號']) ? $r['上傳編號'] : '');
    $ragicId = isset($r['_ragicId']) ? $r['_ragicId'] : null;

    if (empty($uploadNumber)) {
        $skipped++;
        continue;
    }

    $allRagicUploadNumbers[$uploadNumber] = true;

    $sysNumber     = cleanStr(isset($r['系統編號']) ? $r['系統編號'] : '');
    $bankAccount   = cleanStr(isset($r['銀行帳戶']) ? $r['銀行帳戶'] : '');
    $txDate        = parseDate(isset($r['交易日期']) ? $r['交易日期'] : '');
    $postDate      = parseDate(isset($r['記帳日']) ? $r['記帳日'] : '');
    $txTime        = cleanStr(isset($r['交易時間']) ? $r['交易時間'] : '');
    $cashTransfer  = cleanStr(isset($r['現轉別']) ? $r['現轉別'] : '');
    $summary       = cleanStr(isset($r['摘要']) ? $r['摘要'] : '');
    $currency      = cleanStr(isset($r['幣別']) ? $r['幣別'] : '');
    $debitAmount   = parseAmount(isset($r['支出金額']) ? $r['支出金額'] : '0');
    $creditAmount  = parseAmount(isset($r['存入金額']) ? $r['存入金額'] : '0');
    $balance       = parseAmount(isset($r['餘額(原始)']) ? $r['餘額(原始)'] : '0');
    $note          = cleanStr(isset($r['備註']) ? $r['備註'] : '');
    $transferAcct  = cleanStr(isset($r['轉出入帳號']) ? $r['轉出入帳號'] : '');
    $bankCode      = cleanStr(isset($r['存匯代號']) ? $r['存匯代號'] : '');
    $counterAcct   = cleanStr(isset($r['對方帳號']) ? $r['對方帳號'] : '');
    $remark        = cleanStr(isset($r['註記']) ? $r['註記'] : '');
    $description   = cleanStr(isset($r['對象說明']) ? $r['對象說明'] : '');

    // 比對是否已存在
    $existId = null;
    if (isset($existingMap[$uploadNumber])) {
        $existId = $existingMap[$uploadNumber];
    } elseif ($ragicId && isset($existingRagicMap[$ragicId])) {
        $existId = $existingRagicMap[$ragicId];
    }

    if ($existId) {
        // 更新
        if ($num <= 3 || $num % 200 == 0) echo "[{$num}] {$uploadNumber} → 更新\n";
        if ($execute) {
            try {
                $db->prepare("
                    UPDATE bank_transactions SET
                        sys_number=?, upload_number=?, bank_account=?,
                        transaction_date=?, posting_date=?, transaction_time=?,
                        cash_transfer=?, summary=?, currency=?,
                        debit_amount=?, credit_amount=?, balance=?,
                        note=?, transfer_account=?, bank_code=?,
                        counter_account=?, remark=?, description=?, ragic_id=?
                    WHERE id=?
                ")->execute(array(
                    $sysNumber, $uploadNumber, $bankAccount,
                    $txDate, $postDate, $txTime,
                    $cashTransfer, $summary, $currency,
                    $debitAmount, $creditAmount, $balance,
                    $note, $transferAcct, $bankCode,
                    $counterAcct, $remark, $description, $ragicId,
                    $existId
                ));
                $updated++;
            } catch (PDOException $e) {
                echo "  [錯誤] " . $e->getMessage() . "\n";
                $errors++;
            }
        } else {
            $updated++;
        }
    } else {
        // 新增
        if ($num <= 5 || $num % 200 == 0) echo "[{$num}] {$uploadNumber} {$bankAccount} \${$creditAmount} → 新增\n";
        if ($execute) {
            try {
                $db->prepare("
                    INSERT INTO bank_transactions (
                        sys_number, upload_number, bank_account,
                        transaction_date, posting_date, transaction_time,
                        cash_transfer, summary, currency,
                        debit_amount, credit_amount, balance,
                        note, transfer_account, bank_code,
                        counter_account, remark, description, ragic_id
                    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                ")->execute(array(
                    $sysNumber, $uploadNumber, $bankAccount,
                    $txDate, $postDate, $txTime,
                    $cashTransfer, $summary, $currency,
                    $debitAmount, $creditAmount, $balance,
                    $note, $transferAcct, $bankCode,
                    $counterAcct, $remark, $description, $ragicId
                ));
                $imported++;
            } catch (PDOException $e) {
                echo "  [錯誤] " . $e->getMessage() . "\n";
                $errors++;
            }
        } else {
            $imported++;
        }
    }
}

// 刪除 hswork 有但 Ragic 沒有的
$deleted = 0;
foreach ($existingMap as $existNo => $existId) {
    if (!isset($allRagicUploadNumbers[$existNo]) && !empty($existNo)) {
        if ($deleted < 20) echo "[刪除] {$existNo} (ID:{$existId})\n";
        if ($execute) {
            try {
                $db->prepare("DELETE FROM bank_transactions WHERE id = ?")->execute(array($existId));
                $deleted++;
            } catch (PDOException $e) {
                echo "  [刪除錯誤] " . $e->getMessage() . "\n";
            }
        } else {
            $deleted++;
        }
    }
}

echo "\n==============================\n";
echo "完成！\n";
echo "  新增: {$imported} 筆\n";
echo "  更新: {$updated} 筆\n";
echo "  刪除: {$deleted} 筆\n";
echo "  跳過: {$skipped} 筆\n";
echo "  錯誤: {$errors} 筆\n";
echo '</pre>';
echo '<p><a href="/bank_transactions.php">返回銀行帳戶明細</a></p>';
