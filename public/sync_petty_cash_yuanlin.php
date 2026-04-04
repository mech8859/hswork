<?php
/**
 * 零用金 Ragic 三向同步
 */
require_once __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: text/plain; charset=utf-8');
set_time_limit(300);
ini_set('memory_limit', '256M');

$db = Database::getInstance();

$jsonPath = '/raid/vhost/hswork.com.tw/ragic_petty_cash_yuanlin.json';
if (!file_exists($jsonPath)) { echo "JSON not found\n"; exit; }
$ragicData = json_decode(file_get_contents($jsonPath), true);
echo "Ragic: " . count($ragicData) . " records\n";

$branchMap = array();
foreach ($db->query("SELECT id, name FROM branches")->fetchAll(PDO::FETCH_ASSOC) as $b) { $branchMap[$b['name']] = $b['id']; }

function findBranch($name, $map) {
    if (empty($name)) return null;
    if (isset($map[$name])) return $map[$name];
    foreach ($map as $n => $id) { if (strpos($name, str_replace(array('分公司','據點'), '', $n)) !== false) return $id; }
    return null;
}
function cleanDate($d) {
    if (!$d) return null;
    $d = str_replace('/', '-', trim($d));
    if (preg_match('/^\d{4}-\d{1,2}-\d{1,2}/', $d, $m)) return $m[0];
    return null;
}
function cleanAmount($v) { return (int)str_replace(array(',', ' '), '', $v ?: '0'); }

$ragicIndex = array();
foreach ($ragicData as $r) { if (!empty($r['petty_cash_number'])) $ragicIndex[$r['petty_cash_number']] = $r; }

// Only get 員林 records for comparison (don't touch other branches)
$ylBranchId = findBranch('員林分公司', $branchMap);
$sysRecords = $db->prepare("SELECT * FROM petty_cash WHERE branch_id = ? OR entry_number LIKE 'P7-%' ORDER BY id");
$sysRecords->execute(array($ylBranchId));
$sysRecords = $sysRecords->fetchAll(PDO::FETCH_ASSOC);
$sysIndex = array();
foreach ($sysRecords as $s) { if (!empty($s['entry_number'])) $sysIndex[$s['entry_number']] = $s; }
echo "System: " . count($sysRecords) . " records\n\n";

$added = 0; $updated = 0; $deleted = 0; $unchanged = 0;
$errors = array();

foreach ($ragicIndex as $pcNo => $ragic) {
    $branchId = findBranch($ragic['branch'], $branchMap);
    $type = ($ragic['type'] === '收入') ? '收入' : '支出';

    $fields = array(
        'entry_number' => $pcNo,
        'entry_date' => cleanDate($ragic['register_date']),
        'expense_date' => cleanDate($ragic['transaction_date']),
        'branch_id' => $branchId,
        'type' => $type,
        'has_invoice' => $ragic['has_invoice'] ?: '無發票',
        'invoice_info' => $ragic['invoice_info'] ?: null,
        'expense_untaxed' => cleanAmount($ragic['expense_untaxed']),
        'expense_tax' => cleanAmount($ragic['expense_tax']),
        'expense_amount' => cleanAmount($ragic['expense_amount']),
        'income_amount' => cleanAmount($ragic['income_amount']),
        'description' => $ragic['description'] ?: null,
        'registrar' => $ragic['registrar'] ?: null,
        'user_name' => $ragic['user_name'] ?: null,
        'approval_status' => $ragic['approval_status'] ?: null,
        'upload_number' => $ragic['upload_number'] ?: null,
    );

    if (!isset($sysIndex[$pcNo])) {
        try {
            $cols = array_keys($fields);
            $ph = array_fill(0, count($cols), '?');
            $db->prepare("INSERT INTO petty_cash (" . implode(',', $cols) . ") VALUES (" . implode(',', $ph) . ")")->execute(array_values($fields));
            $added++;
        } catch (Exception $e) { $errors[] = "ADD $pcNo: " . $e->getMessage(); }
    } else {
        $sys = $sysIndex[$pcNo];
        $needUpdate = false; $updateCols = array(); $updateVals = array();
        foreach ($fields as $col => $val) {
            if ($col === 'entry_number') continue;
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
                $db->prepare("UPDATE petty_cash SET " . implode(',', $updateCols) . " WHERE id = ?")->execute($updateVals);
                $updated++;
            } catch (Exception $e) { $errors[] = "UPD $pcNo: " . $e->getMessage(); }
        } else { $unchanged++; }
    }
}

foreach ($sysIndex as $pcNo => $sys) {
    if (!isset($ragicIndex[$pcNo])) {
        try {
            $db->prepare("DELETE FROM petty_cash WHERE id = ?")->execute(array($sys['id']));
            $deleted++;
        } catch (Exception $e) { $errors[] = "DEL $pcNo: " . $e->getMessage(); }
    }
}

echo "新增: $added\n更新: $updated\n不變: $unchanged\n刪除: $deleted\n\n";
if (!empty($errors)) {
    echo "=== Errors (first 10) ===\n";
    foreach (array_slice($errors, 0, 10) as $e) echo "  $e\n";
}
echo "Done.\n";
