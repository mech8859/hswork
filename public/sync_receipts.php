<?php
/**
 * 收款單 Ragic 三向同步
 */
require_once __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: text/plain; charset=utf-8');
set_time_limit(300);
ini_set('memory_limit', '256M');

$db = Database::getInstance();

$jsonPath = '/raid/vhost/hswork.com.tw/ragic_receipts.json';
if (!file_exists($jsonPath)) { echo "JSON not found\n"; exit; }
$ragicData = json_decode(file_get_contents($jsonPath), true);
echo "Ragic: " . count($ragicData) . " records\n";

$branchMap = array();
foreach ($db->query("SELECT id, name FROM branches")->fetchAll(PDO::FETCH_ASSOC) as $b) { $branchMap[$b['name']] = $b['id']; }
$salesMap = array();
foreach ($db->query("SELECT id, real_name FROM users WHERE real_name IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC) as $u) { $salesMap[$u['real_name']] = $u['id']; }

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
function findCase($cn, $db) {
    if (empty($cn)) return null;
    $s = $db->prepare("SELECT id FROM cases WHERE case_number = ? LIMIT 1");
    $s->execute(array($cn)); return $s->fetchColumn() ?: null;
}

$ragicIndex = array();
foreach ($ragicData as $r) { if (!empty($r['receipt_number'])) $ragicIndex[$r['receipt_number']] = $r; }

$sysRecords = $db->query("SELECT * FROM receipts ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$sysIndex = array();
foreach ($sysRecords as $s) { $sysIndex[$s['receipt_number']] = $s; }
echo "System: " . count($sysRecords) . " records\n\n";

$added = 0; $updated = 0; $deleted = 0; $unchanged = 0;
$errors = array();

foreach ($ragicIndex as $recNo => $ragic) {
    $branchId = findBranch($ragic['branch'], $branchMap);
    $salesId = isset($salesMap[$ragic['sales_name']]) ? $salesMap[$ragic['sales_name']] : null;
    $caseId = findCase($ragic['case_number'], $db);

    $fields = array(
        'receipt_number' => $recNo,
        'voucher_number' => $ragic['voucher_number'] ?: null,
        'register_date' => cleanDate($ragic['register_date']),
        'deposit_date' => cleanDate($ragic['deposit_date']),
        'customer_name' => $ragic['customer_name'] ?: null,
        'case_number' => $ragic['case_number'] ?: null,
        'case_id' => $caseId,
        'billing_number' => $ragic['billing_number'] ?: null,
        'sales_id' => $salesId,
        'branch_id' => $branchId,
        'subtotal' => cleanAmount($ragic['subtotal']),
        'tax' => cleanAmount($ragic['tax']),
        'discount' => cleanAmount($ragic['discount']),
        'total_amount' => cleanAmount($ragic['total_amount']),
        'receipt_method' => $ragic['receipt_method'] ?: null,
        'invoice_category' => $ragic['invoice_category'] ?: null,
        'status' => $ragic['status'] ?: null,
        'bank_ref' => $ragic['bank_ref'] ?: null,
        'note' => $ragic['note'] ?: null,
        'registrar' => $ragic['registrar'] ?: null,
    );

    if (!isset($sysIndex[$recNo])) {
        try {
            $cols = array_keys($fields);
            $ph = array_fill(0, count($cols), '?');
            $db->prepare("INSERT INTO receipts (" . implode(',', $cols) . ") VALUES (" . implode(',', $ph) . ")")->execute(array_values($fields));
            $added++;
        } catch (Exception $e) { $errors[] = "ADD $recNo: " . $e->getMessage(); }
    } else {
        $sys = $sysIndex[$recNo];
        $needUpdate = false; $updateCols = array(); $updateVals = array();
        foreach ($fields as $col => $val) {
            if ($col === 'receipt_number') continue;
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
                $db->prepare("UPDATE receipts SET " . implode(',', $updateCols) . " WHERE id = ?")->execute($updateVals);
                $updated++;
            } catch (Exception $e) { $errors[] = "UPD $recNo: " . $e->getMessage(); }
        } else { $unchanged++; }
    }
}

foreach ($sysIndex as $recNo => $sys) {
    if (!isset($ragicIndex[$recNo])) {
        try {
            $db->prepare("DELETE FROM receipts WHERE id = ?")->execute(array($sys['id']));
            $deleted++;
        } catch (Exception $e) { $errors[] = "DEL $recNo: " . $e->getMessage(); }
    }
}

echo "新增: $added\n更新: $updated\n不變: $unchanged\n刪除: $deleted\n\n";
if (!empty($errors)) {
    echo "=== Errors (first 10) ===\n";
    foreach (array_slice($errors, 0, 10) as $e) echo "  $e\n";
    if (count($errors) > 10) echo "  ... and " . (count($errors)-10) . " more\n";
}
echo "Done.\n";
