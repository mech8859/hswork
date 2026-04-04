<?php
/**
 * 應付帳款 Ragic 三向同步
 */
require_once __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: text/plain; charset=utf-8');
set_time_limit(300);
ini_set('memory_limit', '256M');

$db = Database::getInstance();

$jsonPath = '/raid/vhost/hswork.com.tw/ragic_payables.json';
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
foreach ($ragicData as $r) { if (!empty($r['payable_number'])) $ragicIndex[$r['payable_number']] = $r; }

$sysRecords = $db->query("SELECT * FROM payables ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$sysIndex = array();
foreach ($sysRecords as $s) { $sysIndex[$s['payable_number']] = $s; }
echo "System: " . count($sysRecords) . " records\n\n";

$added = 0; $updated = 0; $deleted = 0; $unchanged = 0;
$errors = array();

foreach ($ragicIndex as $payNo => $ragic) {
    $fields = array(
        'payable_number' => $payNo,
        'create_date' => cleanDate($ragic['create_date']),
        'vendor_code' => $ragic['vendor_code'] ?: null,
        'vendor_name' => $ragic['vendor_name'] ?: null,
        'payment_period' => $ragic['payment_period'] ?: null,
        'payment_terms' => $ragic['payment_terms'] ?: null,
        'subtotal' => cleanAmount($ragic['subtotal']),
        'tax' => cleanAmount($ragic['tax']),
        'total_amount' => cleanAmount($ragic['total_amount']),
        'status' => $ragic['status'] ?: null,
        'note' => $ragic['note'] ?: null,
        'registrar' => $ragic['registrar'] ?: null,
    );

    if (!isset($sysIndex[$payNo])) {
        try {
            $cols = array_keys($fields);
            $ph = array_fill(0, count($cols), '?');
            $db->prepare("INSERT INTO payables (" . implode(',', $cols) . ") VALUES (" . implode(',', $ph) . ")")->execute(array_values($fields));
            $newId = $db->lastInsertId();
            syncPayableBranches($db, $newId, $ragic['branches'], $branchMap);
            $added++;
        } catch (Exception $e) { $errors[] = "ADD $payNo: " . $e->getMessage(); }
    } else {
        $sys = $sysIndex[$payNo];
        $needUpdate = false; $updateCols = array(); $updateVals = array();
        foreach ($fields as $col => $val) {
            if ($col === 'payable_number') continue;
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
                $db->prepare("UPDATE payables SET " . implode(',', $updateCols) . " WHERE id = ?")->execute($updateVals);
                $updated++;
            } catch (Exception $e) { $errors[] = "UPD $payNo: " . $e->getMessage(); }
        } else { $unchanged++; }
        syncPayableBranches($db, $sys['id'], $ragic['branches'], $branchMap);
    }
}

foreach ($sysIndex as $payNo => $sys) {
    if (!isset($ragicIndex[$payNo])) {
        try {
            $db->prepare("DELETE FROM payable_branches WHERE payable_id = ?")->execute(array($sys['id']));
            $db->prepare("DELETE FROM payables WHERE id = ?")->execute(array($sys['id']));
            $deleted++;
        } catch (Exception $e) { $errors[] = "DEL $payNo: " . $e->getMessage(); }
    }
}

echo "新增: $added\n更新: $updated\n不變: $unchanged\n刪除: $deleted\n\n";
if (!empty($errors)) {
    echo "=== Errors (first 10) ===\n";
    foreach (array_slice($errors, 0, 10) as $e) echo "  $e\n";
    if (count($errors) > 10) echo "  ... and " . (count($errors)-10) . " more\n";
}
echo "Done.\n";

function syncPayableBranches($db, $payableId, $branches, $branchMap) {
    try {
        $db->prepare("DELETE FROM payable_branches WHERE payable_id = ?")->execute(array($payableId));
    } catch (Exception $e) { return; }
    if (empty($branches)) return;
    try {
        $cols = $db->query("SHOW COLUMNS FROM payable_branches")->fetchAll(PDO::FETCH_COLUMN);
        $hasBranchName = in_array('branch_name', $cols);
        if ($hasBranchName) {
            $stmt = $db->prepare("INSERT INTO payable_branches (payable_id, branch_id, branch_name, amount, note) VALUES (?,?,?,?,?)");
        } else {
            $stmt = $db->prepare("INSERT INTO payable_branches (payable_id, branch_id, amount, note) VALUES (?,?,?,?)");
        }
        foreach ($branches as $b) {
            $brId = findBranch($b['branch'], $branchMap);
            if ($hasBranchName) {
                $stmt->execute(array($payableId, $brId, $b['branch'], cleanAmount($b['amount']), $b['note'] ?: null));
            } else {
                $stmt->execute(array($payableId, $brId, cleanAmount($b['amount']), $b['note'] ?: null));
            }
        }
    } catch (Exception $e) { /* skip */ }
}
