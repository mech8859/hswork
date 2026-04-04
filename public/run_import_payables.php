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

$jsonPath = __DIR__ . '/../ragic_payables.json';
$records = json_decode(file_get_contents($jsonPath), true);
echo "Ragic 共 " . count($records) . " 筆應付帳款\n\n";

$branches = $db->query("SELECT id, name FROM branches")->fetchAll(PDO::FETCH_ASSOC);
$branchMap = array();
foreach ($branches as $br) { $branchMap[$br['name']] = $br['id']; }

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

// 現有
$existingStmt = $db->query("SELECT id, payable_number, ragic_id FROM payables");
$existingMap = array();
$existingRagicMap = array();
foreach ($existingStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $existingMap[$row['payable_number']] = $row['id'];
    if ($row['ragic_id']) $existingRagicMap[$row['ragic_id']] = $row['id'];
}

$imported = 0; $updated = 0; $skipped = 0; $errors = 0;
$ragicNumbers = array();

foreach ($records as $idx => $r) {
    $num = $idx + 1;
    $payableNo = trim($r['payable_number']);
    $ragicId = trim($r['ragic_id']);
    $createDate = parseDate($r['create_date']);
    $vendorName = trim($r['vendor_name']);
    $vendorCode = trim($r['vendor_code']);
    $voucherNo = trim($r['voucher_number']);
    $period = trim($r['payment_period']);
    $terms = trim($r['payment_terms']);
    $subtotal = parseAmount($r['subtotal']);
    $tax = parseAmount($r['tax']);
    $total = parseAmount($r['total']);
    $prepaid = parseAmount($r['prepaid']);
    $payableAmt = parseAmount($r['payable_amount']);
    $note = trim($r['note']);

    if (empty($payableNo) && empty($createDate)) { $skipped++; continue; }
    $ragicNumbers[$payableNo] = true;

    $existId = null;
    if (isset($existingMap[$payableNo])) $existId = $existingMap[$payableNo];
    elseif ($ragicId && isset($existingRagicMap[$ragicId])) $existId = $existingRagicMap[$ragicId];

    if ($existId) {
        if ($num <= 5 || $num % 50 == 0) echo "[{$num}] {$payableNo} {$vendorName} → 更新\n";
        if ($execute) {
            try {
                $db->prepare("
                    UPDATE payables SET
                        payable_number=?, voucher_number=?, create_date=?,
                        vendor_name=?, vendor_code=?,
                        payment_period=?, payment_terms=?,
                        subtotal=?, tax=?, total_amount=?,
                        prepaid=?, payable_amount=?,
                        note=?, ragic_id=?
                    WHERE id=?
                ")->execute(array(
                    $payableNo, $voucherNo ?: null, $createDate,
                    $vendorName, $vendorCode ?: null,
                    $period ?: null, $terms ?: null,
                    $subtotal, $tax, $total ?: ($subtotal + $tax),
                    $prepaid, $payableAmt ?: ($total ?: ($subtotal + $tax)),
                    $note ?: null, $ragicId,
                    $existId
                ));
                // 發票明細
                if (!empty($r['invoices'])) {
                    $db->prepare("DELETE FROM payable_invoices WHERE payable_id = ?")->execute(array($existId));
                    $invStmt = $db->prepare("INSERT INTO payable_invoices (payable_id, invoice_date, invoice_number, tax_id, amount_untaxed, tax, subtotal) VALUES (?,?,?,?,?,?,?)");
                    foreach ($r['invoices'] as $inv) {
                        $invDate = !empty($inv['date']) ? date('Y-m-d', strtotime(str_replace('/', '-', $inv['date']))) : null;
                        $invStmt->execute(array($existId, $invDate, $inv['invoice_number'] ?: null, $inv['tax_id'] ?: null, (int)$inv['amount_untaxed'], (int)$inv['tax'], (int)$inv['subtotal']));
                    }
                }
                $updated++;
            } catch (PDOException $e) {
                echo "  [錯誤] " . $e->getMessage() . "\n"; $errors++;
            }
        } else { $updated++; }
    } else {
        if ($num <= 10 || $num % 50 == 0) echo "[{$num}] {$payableNo} {$vendorName} \${$total} → 新增\n";
        if ($execute) {
            try {
                $db->prepare("
                    INSERT INTO payables (payable_number, voucher_number, create_date,
                        vendor_name, vendor_code,
                        payment_period, payment_terms,
                        subtotal, tax, total_amount,
                        prepaid, payable_amount,
                        note, ragic_id, created_by, updated_by)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                ")->execute(array(
                    $payableNo ?: 'RAGIC-' . $ragicId, $voucherNo ?: null, $createDate ?: date('Y-m-d'),
                    $vendorName, $vendorCode ?: null,
                    $period ?: null, $terms ?: null,
                    $subtotal, $tax, $total ?: ($subtotal + $tax),
                    $prepaid, $payableAmt ?: ($total ?: ($subtotal + $tax)),
                    $note ?: null, $ragicId, 1, 1
                ));
                $newId = $db->lastInsertId();

                // 分公司拆帳
                for ($i = 1; $i <= 6; $i++) {
                    $brName = trim($r['branch' . $i]);
                    $brAmt = parseAmount($r['amount' . $i]);
                    $brNote = trim($r['note' . $i]);
                    if (empty($brName) && $brAmt == 0) continue;
                    $brId = findBranch($brName, $branchMap);
                    $db->prepare("INSERT INTO payable_branches (payable_id, branch_id, amount, note) VALUES (?,?,?,?)")
                       ->execute(array($newId, $brId, $brAmt, $brNote));
                }
                // 發票明細
                if (!empty($r['invoices'])) {
                    $invStmt2 = $db->prepare("INSERT INTO payable_invoices (payable_id, invoice_date, invoice_number, tax_id, amount_untaxed, tax, subtotal) VALUES (?,?,?,?,?,?,?)");
                    foreach ($r['invoices'] as $inv) {
                        $invDate = !empty($inv['date']) ? date('Y-m-d', strtotime(str_replace('/', '-', $inv['date']))) : null;
                        $invStmt2->execute(array($newId, $invDate, $inv['invoice_number'] ?: null, $inv['tax_id'] ?: null, (int)$inv['amount_untaxed'], (int)$inv['tax'], (int)$inv['subtotal']));
                    }
                }
                $imported++;
            } catch (PDOException $e) {
                echo "  [錯誤] " . $e->getMessage() . "\n"; $errors++;
            }
        } else { $imported++; }
    }
}

// 刪除
$deleted = 0;
foreach ($existingMap as $existNo => $existId) {
    if (!isset($ragicNumbers[$existNo]) && !empty($existNo)) {
        if ($deleted < 10) echo "[刪除] {$existNo} (ID:{$existId})\n";
        if ($execute) {
            $db->prepare("DELETE FROM payable_branches WHERE payable_id = ?")->execute(array($existId));
            $db->prepare("DELETE FROM payable_invoices WHERE payable_id = ?")->execute(array($existId));
            $db->prepare("DELETE FROM payables WHERE id = ?")->execute(array($existId));
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
