<?php
/**
 * 銀行帳戶明細 Ragic 三向同步（政遠銀行）
 */
require_once __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: text/plain; charset=utf-8');
set_time_limit(300);
ini_set('memory_limit', '256M');

$db = Database::getInstance();

$jsonPath = '/raid/vhost/hswork.com.tw/ragic_bank_zhengyuan.json';
if (!file_exists($jsonPath)) { echo "JSON not found\n"; exit; }
$ragicData = json_decode(file_get_contents($jsonPath), true);
echo "Ragic: " . count($ragicData) . " records\n";

function cleanDate($d) {
    if (!$d) return null;
    $d = str_replace('/', '-', trim($d));
    if (preg_match('/^\d{4}-\d{1,2}-\d{1,2}/', $d, $m)) return $m[0];
    return null;
}
function cleanAmount($v) { return (int)str_replace(array(',', ' '), '', $v ?: '0'); }

$ragicIndex = array();
foreach ($ragicData as $r) { if (!empty($r['upload_number'])) $ragicIndex[$r['upload_number']] = $r; }

// Only get existing records with changhua bank upload numbers (TC-23- prefix or 政遠)
$sysRecords = $db->query("SELECT * FROM bank_transactions WHERE bank_account LIKE '%政遠%' OR upload_number LIKE 'TC-23-%' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$sysIndex = array();
foreach ($sysRecords as $s) { if (!empty($s['upload_number'])) $sysIndex[$s['upload_number']] = $s; }
echo "System (政遠): " . count($sysRecords) . " records\n\n";

$added = 0; $updated = 0; $deleted = 0; $unchanged = 0;
$errors = array();

foreach ($ragicIndex as $upNo => $ragic) {
    $fields = array(
        'sys_number' => $ragic['sys_number'] ?: null,
        'upload_number' => $upNo,
        'bank_account' => $ragic['bank_account'] ?: '',
        'transaction_date' => cleanDate($ragic['transaction_date']),
        'summary' => $ragic['summary'] ?: null,
        'currency' => $ragic['currency'] ?: 'TWD',
        'debit_amount' => cleanAmount($ragic['debit_amount']),
        'credit_amount' => cleanAmount($ragic['credit_amount']),
        'balance' => cleanAmount($ragic['balance']),
        'note' => $ragic['note'] ?: null,
        'transfer_account' => $ragic['transfer_account'] ?: null,
        'remark' => $ragic['remark'] ?: null,
        'description' => $ragic['description'] ?: null,
    );

    if (!isset($sysIndex[$upNo])) {
        try {
            $cols = array_keys($fields);
            $ph = array_fill(0, count($cols), '?');
            $db->prepare("INSERT INTO bank_transactions (" . implode(',', $cols) . ") VALUES (" . implode(',', $ph) . ")")->execute(array_values($fields));
            $added++;
        } catch (Exception $e) { $errors[] = "ADD $upNo: " . $e->getMessage(); }
    } else {
        $sys = $sysIndex[$upNo];
        $needUpdate = false; $updateCols = array(); $updateVals = array();
        foreach ($fields as $col => $val) {
            if ($col === 'upload_number') continue;
            $sysVal = isset($sys[$col]) ? $sys[$col] : null;
            if (is_numeric($val) && is_numeric($sysVal)) {
                if ((int)$val != (int)$sysVal) { $needUpdate = true; $updateCols[] = "$col = ?"; $updateVals[] = $val; }
            } else {
                if ((string)$val !== (string)$sysVal) { $needUpdate = true; $updateCols[] = "$col = ?"; $updateVals[] = $val; }
            }
        }
        if ($needUpdate) {
            try {
                $updateVals[] = $sys['id'];
                $db->prepare("UPDATE bank_transactions SET " . implode(',', $updateCols) . " WHERE id = ?")->execute($updateVals);
                $updated++;
            } catch (Exception $e) { $errors[] = "UPD $upNo: " . $e->getMessage(); }
        } else { $unchanged++; }
    }
}

foreach ($sysIndex as $upNo => $sys) {
    if (!isset($ragicIndex[$upNo])) {
        try {
            $db->prepare("DELETE FROM bank_transactions WHERE id = ?")->execute(array($sys['id']));
            $deleted++;
        } catch (Exception $e) { $errors[] = "DEL $upNo: " . $e->getMessage(); }
    }
}

echo "新增: $added\n更新: $updated\n不變: $unchanged\n刪除: $deleted\n\n";
if (!empty($errors)) {
    echo "=== Errors (first 10) ===\n";
    foreach (array_slice($errors, 0, 10) as $e) echo "  $e\n";
}
echo "Done.\n";
