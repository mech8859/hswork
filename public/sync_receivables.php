<?php
/**
 * 應收帳款 Ragic 三向同步
 * - Ragic有系統沒有 → 新增
 * - Ragic有系統有 → 比對更新
 * - Ragic沒有系統有 → 刪除
 */
require_once __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: text/plain; charset=utf-8');
set_time_limit(300);
ini_set('memory_limit', '256M');

$db = Database::getInstance();

$jsonPath = '/raid/vhost/hswork.com.tw/ragic_receivables.json';
if (!file_exists($jsonPath)) { echo "JSON not found\n"; exit; }
$ragicData = json_decode(file_get_contents($jsonPath), true);
echo "Ragic: " . count($ragicData) . " records\n";

// Lookup maps
$branchMap = array();
foreach ($db->query("SELECT id, name FROM branches")->fetchAll(PDO::FETCH_ASSOC) as $b) {
    $branchMap[$b['name']] = $b['id'];
}
$salesMap = array();
foreach ($db->query("SELECT id, real_name FROM users WHERE real_name IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC) as $u) {
    $salesMap[$u['real_name']] = $u['id'];
}

function findBranch($name, $map) {
    if (empty($name)) return null;
    if (isset($map[$name])) return $map[$name];
    foreach ($map as $n => $id) {
        if (strpos($name, str_replace(array('分公司','據點'), '', $n)) !== false) return $id;
    }
    return null;
}
function cleanDate($d) {
    if (!$d) return null;
    $d = str_replace('/', '-', trim($d));
    if (preg_match('/^\d{4}-\d{1,2}-\d{1,2}/', $d, $m)) return $m[0];
    return null;
}
function cleanAmount($v) {
    return (int)str_replace(array(',', ' '), '', $v ?: '0');
}
function findCase($caseNum, $db) {
    if (empty($caseNum)) return null;
    $stmt = $db->prepare("SELECT id FROM cases WHERE case_number = ? LIMIT 1");
    $stmt->execute(array($caseNum));
    return $stmt->fetchColumn() ?: null;
}

// Build Ragic index by receivable_number
$ragicIndex = array();
foreach ($ragicData as $r) {
    if (!empty($r['receivable_number'])) {
        $ragicIndex[$r['receivable_number']] = $r;
    }
}

// Get system data
$sysRecords = $db->query("SELECT * FROM receivables ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$sysIndex = array();
foreach ($sysRecords as $s) {
    $sysIndex[$s['receivable_number']] = $s;
}
echo "System: " . count($sysRecords) . " records\n\n";

$added = 0; $updated = 0; $deleted = 0; $unchanged = 0;
$errors = array();

foreach ($ragicIndex as $recvNo => $ragic) {
    $branchId = findBranch($ragic['branch'], $branchMap);
    $salesId = isset($salesMap[$ragic['sales_name']]) ? $salesMap[$ragic['sales_name']] : null;
    $caseId = findCase($ragic['case_number'], $db);
    $invoiceDate = cleanDate($ragic['invoice_date']);
    $realInvoiceDate = cleanDate($ragic['real_invoice_date']);

    $fields = array(
        'receivable_number' => $recvNo,
        'voucher_number' => $ragic['voucher_number'] ?: null,
        'invoice_date' => $invoiceDate,
        'case_number' => $ragic['case_number'] ?: null,
        'case_id' => $caseId,
        'customer_name' => $ragic['customer_name'] ?: null,
        'sales_id' => $salesId,
        'branch_id' => $branchId,
        'invoice_category' => $ragic['invoice_category'] ?: null,
        'status' => $ragic['status'] ?: null,
        'invoice_title' => $ragic['invoice_title'] ?: null,
        'tax_id' => $ragic['tax_id'] ?: null,
        'phone' => $ragic['phone'] ?: null,
        'mobile' => $ragic['mobile'] ?: null,
        'payment_method' => $ragic['payment_method'] ?: null,
        'payment_terms' => $ragic['payment_terms'] ?: null,
        'real_invoice_number' => $ragic['real_invoice_number'] ?: null,
        'voucher_type' => $ragic['voucher_type'] ?: null,
        'tax_rate' => $ragic['tax_rate'] ?: null,
        'deposit' => cleanAmount($ragic['deposit']),
        'discount' => cleanAmount($ragic['discount']),
        'subtotal' => cleanAmount($ragic['subtotal']),
        'tax' => cleanAmount($ragic['tax']),
        'shipping' => cleanAmount($ragic['shipping']),
        'total_amount' => cleanAmount($ragic['total_amount']),
        'note' => $ragic['note'] ?: null,
        'registrar' => $ragic['registrar'] ?: null,
    );

    // Items from Ragic
    $ragicItems = isset($ragic['items']) ? $ragic['items'] : array();

    if (!isset($sysIndex[$recvNo])) {
        // 新增
        try {
            $cols = array_keys($fields);
            $placeholders = array_fill(0, count($cols), '?');
            $sql = "INSERT INTO receivables (" . implode(',', $cols) . ") VALUES (" . implode(',', $placeholders) . ")";
            $db->prepare($sql)->execute(array_values($fields));
            $newId = $db->lastInsertId();
            // Save items
            syncItems($db, $newId, $ragicItems);
            $added++;
        } catch (Exception $e) {
            $errors[] = "ADD $recvNo: " . $e->getMessage();
        }
    } else {
        // 比對更新
        $sys = $sysIndex[$recvNo];
        $needUpdate = false;
        $updateCols = array();
        $updateVals = array();

        foreach ($fields as $col => $val) {
            if ($col === 'receivable_number') continue;
            $sysVal = isset($sys[$col]) ? $sys[$col] : null;
            // Normalize comparison
            if (is_numeric($val) && is_numeric($sysVal)) {
                if ((int)$val != (int)$sysVal) { $needUpdate = true; $updateCols[] = "$col = ?"; $updateVals[] = $val; }
            } else {
                if ((string)$val !== (string)$sysVal) { $needUpdate = true; $updateCols[] = "$col = ?"; $updateVals[] = $val; }
            }
        }

        if ($needUpdate) {
            try {
                $updateVals[] = $sys['id'];
                $db->prepare("UPDATE receivables SET " . implode(',', $updateCols) . " WHERE id = ?")->execute($updateVals);
                $updated++;
            } catch (Exception $e) {
                $errors[] = "UPD $recvNo: " . $e->getMessage();
            }
        } else {
            $unchanged++;
        }
        // Always sync items
        syncItems($db, $sys['id'], $ragicItems);
    }
}

// 刪除：Ragic沒有系統有
foreach ($sysIndex as $recvNo => $sys) {
    if (!isset($ragicIndex[$recvNo])) {
        try {
            $db->prepare("DELETE FROM receivables WHERE id = ?")->execute(array($sys['id']));
            $deleted++;
        } catch (Exception $e) {
            $errors[] = "DEL $recvNo: " . $e->getMessage();
        }
    }
}

echo "新增: $added\n更新: $updated\n不變: $unchanged\n刪除: $deleted\n\n";
if (!empty($errors)) {
    echo "=== Errors (first 10) ===\n";
    foreach (array_slice($errors, 0, 10) as $e) echo "  $e\n";
    if (count($errors) > 10) echo "  ... and " . (count($errors)-10) . " more\n";
}
echo "Done.\n";

function syncItems($db, $receivableId, $ragicItems) {
    // Delete existing and re-insert
    $db->prepare("DELETE FROM receivable_items WHERE receivable_id = ?")->execute(array($receivableId));
    if (empty($ragicItems)) return;
    $stmt = $db->prepare("INSERT INTO receivable_items (receivable_id, item_name, unit_price, quantity, amount, note, sort_order) VALUES (?,?,?,?,?,?,?)");
    $sort = 0;
    foreach ($ragicItems as $item) {
        $itemName = isset($item['item_name']) ? $item['item_name'] : '';
        if (empty($itemName) && empty($item['amount'])) continue;
        $unitPrice = (int)str_replace(',', '', $item['unit_price'] ?: '0');
        $qty = (float)($item['quantity'] ?: 1);
        $amount = (int)str_replace(',', '', $item['amount'] ?: '0');
        if (!$amount && $unitPrice) $amount = $unitPrice * $qty;
        $stmt->execute(array(
            $receivableId,
            $itemName ?: null,
            $unitPrice,
            $qty,
            $amount,
            isset($item['note']) ? $item['note'] : null,
            $sort++
        ));
    }
}
