<?php
/**
 * 備用金 Ragic 同步（只新增/更新，不刪除其他）
 */
require_once __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: text/plain; charset=utf-8');
set_time_limit(300);
ini_set('memory_limit', '256M');

$db = Database::getInstance();

$jsonPath = '/raid/vhost/hswork.com.tw/ragic_reserve_fund.json';
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
foreach ($ragicData as $r) { if (!empty($r['entry_number'])) $ragicIndex[$r['entry_number']] = $r; }

$sysRecords = $db->query("SELECT * FROM reserve_fund ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$sysIndex = array();
foreach ($sysRecords as $s) { if (!empty($s['entry_number'])) $sysIndex[$s['entry_number']] = $s; }
echo "System: " . count($sysRecords) . " records\n\n";

$added = 0; $updated = 0; $unchanged = 0;
$errors = array();

foreach ($ragicIndex as $no => $ragic) {
    $branchId = findBranch($ragic['branch'], $branchMap);
    $type = ($ragic['type'] === '收入') ? '收入' : '支出';

    $fields = array(
        'entry_number' => $no,
        'entry_date' => cleanDate($ragic['register_date']),
        'expense_date' => cleanDate($ragic['expense_date']),
        'branch_id' => $branchId,
        'type' => $type,
        'expense_amount' => cleanAmount($ragic['expense_amount']),
        'income_amount' => cleanAmount($ragic['income_amount']),
        'description' => $ragic['description'] ?: null,
        'invoice_info' => $ragic['invoice_info'] ?: null,
        'registrar' => $ragic['registrar'] ?: null,
        'approval_status' => $ragic['approval_status'] ?: null,
        'user_name' => $ragic['user_name'] ?: null,
        'upload_number' => $ragic['upload_number'] ?: null,
    );

    if (!isset($sysIndex[$no])) {
        try {
            $cols = array_keys($fields);
            $ph = array_fill(0, count($cols), '?');
            $db->prepare("INSERT INTO reserve_fund (" . implode(',', $cols) . ") VALUES (" . implode(',', $ph) . ")")->execute(array_values($fields));
            $added++;
        } catch (Exception $e) { $errors[] = "ADD $no: " . $e->getMessage(); }
    } else {
        $sys = $sysIndex[$no];
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
                $db->prepare("UPDATE reserve_fund SET " . implode(',', $updateCols) . " WHERE id = ?")->execute($updateVals);
                $updated++;
            } catch (Exception $e) { $errors[] = "UPD $no: " . $e->getMessage(); }
        } else { $unchanged++; }
    }
}

echo "新增: $added\n更新: $updated\n不變: $unchanged\n\n";
if (!empty($errors)) {
    echo "=== Errors (first 10) ===\n";
    foreach (array_slice($errors, 0, 10) as $e) echo "  $e\n";
}
echo "Done.\n";
