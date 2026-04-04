<?php
/**
 * 付款單 Ragic 三向同步
 */
require_once __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: text/plain; charset=utf-8');
set_time_limit(300);
ini_set('memory_limit', '256M');

$db = Database::getInstance();

$jsonPath = '/raid/vhost/hswork.com.tw/ragic_payments.json';
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
foreach ($ragicData as $r) { if (!empty($r['payment_number'])) $ragicIndex[$r['payment_number']] = $r; }

$sysRecords = $db->query("SELECT * FROM payments_out ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$sysIndex = array();
foreach ($sysRecords as $s) { $sysIndex[$s['payment_number']] = $s; }
echo "System: " . count($sysRecords) . " records\n\n";

// Check payment_out_branches columns
$hasBranchName = false;
try {
    $cols = $db->query("SHOW COLUMNS FROM payment_out_branches")->fetchAll(PDO::FETCH_COLUMN);
    $hasBranchName = in_array('branch_name', $cols);
} catch (Exception $e) {}

$added = 0; $updated = 0; $deleted = 0; $unchanged = 0;
$errors = array();

foreach ($ragicIndex as $payNo => $ragic) {
    $fields = array(
        'payment_number' => $payNo,
        'create_date' => cleanDate($ragic['create_date']),
        'payment_date' => cleanDate($ragic['payment_date']),
        'vendor_code' => $ragic['vendor_code'] ?: null,
        'vendor_name' => $ragic['vendor_name'] ?: null,
        'payment_method' => $ragic['payment_method'] ?: null,
        'payment_type' => $ragic['payment_type'] ?: null,
        'payment_terms' => $ragic['payment_terms'] ?: null,
        'status' => $ragic['status'] ?: null,
        'subtotal' => cleanAmount($ragic['subtotal']),
        'tax' => cleanAmount($ragic['tax']),
        'remittance_fee' => cleanAmount($ragic['remittance_fee']),
        'total_amount' => cleanAmount($ragic['total_amount']),
        'main_category' => $ragic['main_category'] ?: null,
        'sub_category' => $ragic['sub_category'] ?: null,
        'note' => $ragic['note'] ?: null,
        'registrar' => $ragic['registrar'] ?: null,
    );

    if (!isset($sysIndex[$payNo])) {
        try {
            $cols = array_keys($fields);
            $ph = array_fill(0, count($cols), '?');
            $db->prepare("INSERT INTO payments_out (" . implode(',', $cols) . ") VALUES (" . implode(',', $ph) . ")")->execute(array_values($fields));
            $newId = $db->lastInsertId();
            syncBranches($db, $newId, $ragic['branches'], $branchMap, $hasBranchName);
            $added++;
        } catch (Exception $e) { $errors[] = "ADD $payNo: " . $e->getMessage(); }
    } else {
        $sys = $sysIndex[$payNo];
        $needUpdate = false; $updateCols = array(); $updateVals = array();
        foreach ($fields as $col => $val) {
            if ($col === 'payment_number') continue;
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
                $db->prepare("UPDATE payments_out SET " . implode(',', $updateCols) . " WHERE id = ?")->execute($updateVals);
                $updated++;
            } catch (Exception $e) { $errors[] = "UPD $payNo: " . $e->getMessage(); }
        } else { $unchanged++; }
        syncBranches($db, $sys['id'], $ragic['branches'], $branchMap, $hasBranchName);
    }
}

foreach ($sysIndex as $payNo => $sys) {
    if (!isset($ragicIndex[$payNo])) {
        try {
            try { $db->prepare("DELETE FROM payment_out_branches WHERE payment_out_id = ?")->execute(array($sys['id'])); } catch(Exception $e2){}
            $db->prepare("DELETE FROM payments_out WHERE id = ?")->execute(array($sys['id']));
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

function syncBranches($db, $paymentId, $branches, $branchMap, $hasBranchName) {
    try { $db->prepare("DELETE FROM payment_out_branches WHERE payment_out_id = ?")->execute(array($paymentId)); } catch(Exception $e){ return; }
    if (empty($branches)) return;
    try {
        if ($hasBranchName) {
            $stmt = $db->prepare("INSERT INTO payment_out_branches (payment_out_id, branch_id, branch_name, amount, note) VALUES (?,?,?,?,?)");
        } else {
            $stmt = $db->prepare("INSERT INTO payment_out_branches (payment_out_id, branch_id, amount, note) VALUES (?,?,?,?)");
        }
        foreach ($branches as $b) {
            $brId = findBranch($b['branch'], $branchMap);
            if ($hasBranchName) {
                $stmt->execute(array($paymentId, $brId, $b['branch'], cleanAmount($b['amount']), $b['note'] ?: null));
            } else {
                $stmt->execute(array($paymentId, $brId, cleanAmount($b['amount']), $b['note'] ?: null));
            }
        }
    } catch (Exception $e) {}
}
