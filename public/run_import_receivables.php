<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('admin')) { die('需要管理員權限'); }
header('Content-Type: text/html; charset=utf-8');
set_time_limit(300);
echo '<pre>';

$db = Database::getInstance();
$execute = isset($_GET['execute']) && $_GET['execute'] == '1';
echo $execute ? "=== 執行模式 ===\n\n" : "=== 預覽模式 === (加 ?execute=1 執行)\n\n";

$jsonPath = __DIR__ . '/../ragic_receivables.json';
$records = json_decode(file_get_contents($jsonPath), true);
echo "Ragic 共 " . count($records) . " 筆應收帳款\n\n";

// 對照表
$branches = $db->query("SELECT id, name FROM branches")->fetchAll(PDO::FETCH_ASSOC);
$branchMap = array();
foreach ($branches as $br) { $branchMap[$br['name']] = $br['id']; }

$salesStmt = $db->query("SELECT id, real_name FROM users WHERE real_name IS NOT NULL AND real_name != ''");
$salesMap = array();
foreach ($salesStmt->fetchAll(PDO::FETCH_ASSOC) as $u) { $salesMap[$u['real_name']] = $u['id']; }

function findBranch($name, $map) {
    if (empty($name)) return null;
    if (isset($map[$name])) return $map[$name];
    foreach ($map as $n => $id) {
        if (strpos($name, str_replace(array('分公司','據點'), '', $n)) !== false) return $id;
        if (strpos($n, str_replace(array('分公司','據點'), '', $name)) !== false) return $id;
    }
    return null;
}
function parseDate($v) {
    if (empty($v)) return null;
    $v = str_replace('/', '-', trim($v));
    $ts = strtotime($v);
    return $ts ? date('Y-m-d', $ts) : null;
}
function parseAmount($v) {
    if (empty($v)) return 0;
    return (int)preg_replace('/[^0-9\-]/', '', $v);
}

// 現有應收帳款
$existingStmt = $db->query("SELECT id, invoice_number, receivable_number, ragic_id FROM receivables");
$existingMap = array();
$existingRagicMap = array();
$existingRecvMap = array();
foreach ($existingStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if ($row['invoice_number']) $existingMap[$row['invoice_number']] = $row['id'];
    if ($row['receivable_number']) $existingRecvMap[$row['receivable_number']] = $row['id'];
    if ($row['ragic_id']) $existingRagicMap[$row['ragic_id']] = $row['id'];
}

$imported = 0; $updated = 0; $skipped = 0; $errors = 0;
$ragicNumbers = array();

foreach ($records as $idx => $r) {
    $num = $idx + 1;
    $recvNo = trim($r['receivable_number']); // S1-xxx
    $ragicId = trim($r['ragic_id']);
    $customerName = trim($r['customer_name_old'] ?: $r['customer_name_new']);
    $requestDate = parseDate($r['request_date']);
    $invoiceDate = parseDate($r['invoice_date']);
    $branchId = findBranch($r['branch'], $branchMap);
    $salesId = isset($salesMap[trim($r['sales'])]) ? $salesMap[trim($r['sales'])] : null;
    $caseNumber = trim($r['case_number']);
    $caseId = null;
    if ($caseNumber) {
        $cs = $db->prepare("SELECT id FROM cases WHERE case_number = ? LIMIT 1");
        $cs->execute(array($caseNumber));
        $caseId = $cs->fetchColumn() ?: null;
    }

    $subtotal = parseAmount($r['subtotal']);
    $tax = parseAmount($r['tax']);
    $total = parseAmount($r['total']);
    $deposit = parseAmount($r['deposit']);
    $discount = parseAmount($r['discount']);
    $shipping = parseAmount($r['shipping']);
    $status = trim($r['status']) ?: '待請款';
    $voucherNo = trim($r['voucher_number']);
    $registrar = trim($r['registrar']);

    if (empty($recvNo) && empty($requestDate)) { $skipped++; continue; }
    $ragicNumbers[$recvNo] = true;

    // 比對
    $existId = null;
    if (isset($existingRecvMap[$recvNo])) $existId = $existingRecvMap[$recvNo];
    elseif (isset($existingMap[$recvNo])) $existId = $existingMap[$recvNo];
    elseif ($ragicId && isset($existingRagicMap[$ragicId])) $existId = $existingRagicMap[$ragicId];

    if ($existId) {
        if ($num <= 5 || $num % 100 == 0) echo "[{$num}] {$recvNo} {$customerName} → 更新\n";
        if ($execute) {
            try {
                $db->prepare("
                    UPDATE receivables SET
                        receivable_number=?, voucher_number=?, invoice_number=?,
                        invoice_date=?, case_id=?, case_number=?, customer_name=?,
                        branch_id=?, sales_id=?, invoice_category=?, status=?,
                        invoice_title=?, tax_id=?, phone=?, mobile=?,
                        invoice_email=?, invoice_address=?, payment_method=?, payment_terms=?,
                        voucher_type=?, tax_rate=?, deposit=?, discount=?,
                        subtotal=?, tax=?, shipping=?, total_amount=?,
                        real_invoice_number=?, note=?, registrar=?, ragic_id=?
                    WHERE id=?
                ")->execute(array(
                    $recvNo, $voucherNo, $recvNo,
                    $requestDate ?: $invoiceDate, $caseId, $caseNumber ?: null, $customerName,
                    $branchId, $salesId, trim($r['invoice_category']), $status,
                    trim($r['invoice_title']), trim($r['tax_id']), trim($r['phone']), trim($r['mobile']),
                    trim($r['invoice_email']), trim($r['invoice_address']), trim($r['payment_method']), trim($r['payment_terms']),
                    trim($r['voucher_type']), trim($r['tax_rate']), $deposit, $discount,
                    $subtotal, $tax, $shipping, $total ?: ($subtotal + $tax),
                    trim($r['invoice_number']), trim($r['note']), $registrar, $ragicId,
                    $existId
                ));
                $updated++;
            } catch (PDOException $e) {
                echo "  [錯誤] " . $e->getMessage() . "\n"; $errors++;
            }
        } else { $updated++; }
    } else {
        if ($num <= 10 || $num % 100 == 0) echo "[{$num}] {$recvNo} {$customerName} \${$total} → 新增\n";
        if ($execute) {
            try {
                $db->prepare("
                    INSERT INTO receivables (receivable_number, voucher_number, invoice_number,
                        invoice_date, case_id, case_number, customer_name,
                        branch_id, sales_id, invoice_category, status,
                        invoice_title, tax_id, phone, mobile,
                        invoice_email, invoice_address, payment_method, payment_terms,
                        voucher_type, tax_rate, deposit, discount,
                        subtotal, tax, shipping, total_amount,
                        real_invoice_number, note, registrar, ragic_id, created_by, updated_by)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                ")->execute(array(
                    $recvNo, $voucherNo, $recvNo,
                    $requestDate ?: $invoiceDate ?: date('Y-m-d'), $caseId, $customerName,
                    $branchId, $salesId, trim($r['invoice_category']), $status,
                    trim($r['invoice_title']), trim($r['tax_id']), trim($r['phone']), trim($r['mobile']),
                    trim($r['invoice_email']), trim($r['invoice_address']), trim($r['payment_method']), trim($r['payment_terms']),
                    trim($r['voucher_type']), trim($r['tax_rate']), $deposit, $discount,
                    $subtotal, $tax, $shipping, $total ?: ($subtotal + $tax),
                    trim($r['invoice_number']), trim($r['note']), $registrar, $ragicId, 1, 1
                ));
                $imported++;
            } catch (PDOException $e) {
                echo "  [錯誤] " . $e->getMessage() . "\n"; $errors++;
            }
        } else { $imported++; }
    }
}

// 刪除 hswork 有但 Ragic 沒有
$deleted = 0;
foreach ($existingRecvMap as $existNo => $existId) {
    if (!isset($ragicNumbers[$existNo]) && !empty($existNo)) {
        if ($deleted < 10) echo "[刪除] {$existNo} (ID:{$existId})\n";
        if ($execute) {
            $db->prepare("DELETE FROM receivable_items WHERE receivable_id = ?")->execute(array($existId));
            $db->prepare("DELETE FROM receivables WHERE id = ?")->execute(array($existId));
        }
        $deleted++;
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
