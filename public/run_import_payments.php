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

$jsonPath = __DIR__ . '/../ragic_payments.json';
$records = json_decode(file_get_contents($jsonPath), true);
echo "Ragic 共 " . count($records) . " 筆付款單\n\n";

// 分公司對照
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

// 現有付款單
$existingStmt = $db->query("SELECT id, payment_number, ragic_id FROM payments_out");
$existingMap = array();
$existingRagicMap = array();
foreach ($existingStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $existingMap[$row['payment_number']] = $row['id'];
    if ($row['ragic_id']) $existingRagicMap[$row['ragic_id']] = $row['id'];
}

$imported = 0; $updated = 0; $skipped = 0; $errors = 0;
$ragicNumbers = array();

foreach ($records as $idx => $r) {
    $num = $idx + 1;
    $paymentNo = trim($r['payment_number']);
    $ragicId = trim($r['ragic_id']);
    $createDate = parseDate($r['create_date']);
    $paymentDate = parseDate($r['payment_date']);
    $vendorName = trim($r['vendor_name']);
    $vendorCode = trim($r['vendor_code']);
    $method = trim($r['payment_method']);
    $category = trim($r['payment_category']);
    $terms = trim($r['payment_terms']);
    $status = trim($r['status']) ?: '草稿';
    $subtotal = parseAmount($r['subtotal']);
    $tax = parseAmount($r['tax']);
    $wireFee = parseAmount($r['wire_fee']);
    $total = parseAmount($r['total']);
    $mainCat = trim($r['main_category']);
    $subCat = trim($r['sub_category']);
    $note = trim($r['note']);
    $registrar = trim($r['registrar']);
    $payableNo = trim($r['payable_number']);

    if (empty($paymentNo) && empty($createDate)) { $skipped++; continue; }
    $ragicNumbers[$paymentNo] = true;

    // 比對
    $existId = null;
    if (isset($existingMap[$paymentNo])) $existId = $existingMap[$paymentNo];
    elseif ($ragicId && isset($existingRagicMap[$ragicId])) $existId = $existingRagicMap[$ragicId];

    if ($existId) {
        if ($num <= 5 || $num % 100 == 0) echo "[{$num}] {$paymentNo} {$vendorName} → 更新\n";
        if ($execute) {
            try {
                $db->prepare("
                    UPDATE payments_out SET
                        payment_number=?, create_date=?, payment_date=?, vendor_name=?, vendor_code=?,
                        payment_method=?, payment_type=?, payment_terms=?, status=?,
                        subtotal=?, tax=?, remittance_fee=?, total_amount=?,
                        main_category=?, sub_category=?, note=?, registrar=?, ragic_id=?
                    WHERE id=?
                ")->execute(array(
                    $paymentNo, $createDate, $paymentDate, $vendorName, $vendorCode,
                    $method, $category, $terms, $status,
                    $subtotal, $tax, $wireFee, $total ?: ($subtotal + $tax + $wireFee),
                    $mainCat, $subCat, $note, $registrar, $ragicId,
                    $existId
                ));
                $updated++;
            } catch (PDOException $e) {
                echo "  [錯誤] " . $e->getMessage() . "\n"; $errors++;
            }
        } else { $updated++; }

    } else {
        if ($num <= 10 || $num % 100 == 0) echo "[{$num}] {$paymentNo} {$vendorName} \${$total} → 新增\n";
        if ($execute) {
            try {
                $db->prepare("
                    INSERT INTO payments_out (payment_number, create_date, payment_date, vendor_name, vendor_code,
                        payment_method, payment_type, payment_terms, status,
                        subtotal, tax, remittance_fee, total_amount,
                        main_category, sub_category, note, registrar, ragic_id, created_by, updated_by)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                ")->execute(array(
                    $paymentNo ?: 'RAGIC-' . $ragicId, $createDate ?: date('Y-m-d'), $paymentDate,
                    $vendorName, $vendorCode,
                    $method, $category, $terms, $status,
                    $subtotal, $tax, $wireFee, $total ?: ($subtotal + $tax + $wireFee),
                    $mainCat, $subCat, $note, $registrar, $ragicId, 1, 1
                ));
                $newId = $db->lastInsertId();

                // 分公司拆帳明細
                for ($i = 1; $i <= 6; $i++) {
                    $brName = trim($r['branch' . $i]);
                    $brAmt = parseAmount($r['amount' . $i]);
                    $brNote = trim($r['note' . $i]);
                    if (empty($brName) && $brAmt == 0) continue;
                    $brId = findBranch($brName, $branchMap);
                    $db->prepare("INSERT INTO payment_out_branches (payment_out_id, branch_id, amount, note) VALUES (?,?,?,?)")
                       ->execute(array($newId, $brId, $brAmt, $brNote));
                }
                $imported++;
            } catch (PDOException $e) {
                echo "  [錯誤] " . $e->getMessage() . "\n"; $errors++;
            }
        } else { $imported++; }
    }
}

// 刪除 hswork 有但 Ragic 沒有的
$deleted = 0;
foreach ($existingMap as $existNo => $existId) {
    if (!isset($ragicNumbers[$existNo]) && !empty($existNo)) {
        if ($deleted < 10) echo "[刪除] {$existNo} (ID:{$existId})\n";
        if ($execute) {
            $db->prepare("DELETE FROM payment_out_branches WHERE payment_out_id = ?")->execute(array($existId));
            $db->prepare("DELETE FROM payment_out_vouchers WHERE payment_out_id = ?")->execute(array($existId));
            $db->prepare("DELETE FROM payments_out WHERE id = ?")->execute(array($existId));
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
