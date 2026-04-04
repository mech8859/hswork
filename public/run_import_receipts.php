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

// 讀取 JSON
$jsonPath = __DIR__ . '/../ragic_receipts.json';
if (!file_exists($jsonPath)) { die("ragic_receipts.json 不存在\n"); }
$records = json_decode(file_get_contents($jsonPath), true);
if (!$records) { die("JSON 解析失敗\n"); }
echo "Ragic 共 " . count($records) . " 筆收款單\n\n";

// 載入分公司對照
$branches = $db->query("SELECT id, name FROM branches")->fetchAll(PDO::FETCH_ASSOC);
$branchMap = array();
foreach ($branches as $br) { $branchMap[$br['name']] = $br['id']; }

// 載入業務人員對照
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

function findSales($name, $map) {
    if (empty($name)) return null;
    if (isset($map[$name])) return $map[$name];
    return null;
}

function parseDate($v) {
    if (empty($v)) return null;
    $v = str_replace('/', '-', trim($v));
    if (preg_match('/^0\d{3}-/', $v)) return null;
    $ts = strtotime($v);
    return $ts ? date('Y-m-d', $ts) : null;
}

function parseAmount($v) {
    if (empty($v)) return 0;
    return (int)preg_replace('/[^0-9\-]/', '', $v);
}

function mapStatus($v) {
    // 直接保留 Ragic 原始狀態值
    $v = trim($v);
    if (empty($v)) return '待收款';
    return $v;
}

// 取得 hswork 現有收款單（用 receipt_number 比對）
$existingStmt = $db->query("SELECT id, receipt_number, ragic_id FROM receipts");
$existingMap = array(); // receipt_number => id
$existingRagicMap = array(); // ragic_id => id
foreach ($existingStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $existingMap[$row['receipt_number']] = $row['id'];
    if ($row['ragic_id']) $existingRagicMap[$row['ragic_id']] = $row['id'];
}

$imported = 0; $updated = 0; $skipped = 0; $errors = 0;
$ragicNumbers = array(); // 追蹤 Ragic 有的編號

foreach ($records as $idx => $r) {
    $num = $idx + 1;
    $receiptNo = trim($r['receipt_number']);
    $ragicId = trim($r['ragic_id']);
    $customerName = trim($r['customer_name_old'] ?: $r['customer_name_new']);
    $registerDate = parseDate($r['register_date']);
    $depositDate = parseDate($r['deposit_date']);
    $subtotal = parseAmount($r['subtotal']);
    $tax = parseAmount($r['tax']);
    $discount = parseAmount($r['discount']);
    $total = parseAmount($r['total']);
    $method = trim($r['receipt_method']);
    $category = trim($r['invoice_category']);
    $status = mapStatus($r['status']);
    $bankRef = trim($r['bank_ref']);
    $sales = trim($r['sales']);
    $branch = trim($r['branch']);
    $note = trim($r['note']);
    $voucherNo = trim($r['voucher_no']);
    $registrar = trim($r['registrar']);
    $caseNumber = trim($r['case_number']);

    if (empty($receiptNo) && empty($registerDate)) { $skipped++; continue; }
    $ragicNumbers[$receiptNo] = true;

    $branchId = findBranch($branch, $branchMap);
    $salesId = findSales($sales, $salesMap);

    // 查案件 ID
    $caseId = null;
    if ($caseNumber) {
        $caseStmt = $db->prepare("SELECT id FROM cases WHERE case_number = ? LIMIT 1");
        $caseStmt->execute(array($caseNumber));
        $caseId = $caseStmt->fetchColumn() ?: null;
    }

    // 比對是否已存在
    $existId = null;
    if (isset($existingMap[$receiptNo])) {
        $existId = $existingMap[$receiptNo];
    } elseif ($ragicId && isset($existingRagicMap[$ragicId])) {
        $existId = $existingRagicMap[$ragicId];
    }

    if ($existId) {
        // 更新
        if ($num <= 5 || $num % 100 == 0) echo "[{$num}] {$receiptNo} {$customerName} → 更新\n";
        if ($execute) {
            try {
                $db->prepare("
                    UPDATE receipts SET
                        receipt_number=?, voucher_number=?, billing_number=?,
                        register_date=?, deposit_date=?, customer_name=?,
                        case_id=?, case_number=?, sales_id=?, branch_id=?,
                        subtotal=?, tax=?, discount=?, total_amount=?,
                        receipt_method=?, invoice_category=?, status=?, bank_ref=?,
                        note=?, ragic_id=?, registrar=?
                    WHERE id=?
                ")->execute(array(
                    $receiptNo, $voucherNo ?: null, null,
                    $registerDate, $depositDate, $customerName,
                    $caseId, $caseNumber ?: null, $salesId, $branchId,
                    $subtotal, $tax, $discount, $total ?: $subtotal,
                    $method ?: null, $category ?: null, $status, $bankRef ?: null,
                    $note ?: null, $ragicId, $registrar ?: null,
                    $existId
                ));
                $updated++;
            } catch (PDOException $e) {
                echo "  [錯誤] " . $e->getMessage() . "\n"; $errors++;
            }
        } else { $updated++; }
    } else {
        // 新增
        if ($num <= 10 || $num % 100 == 0) echo "[{$num}] {$receiptNo} {$customerName} \${$total} → 新增\n";
        if ($execute) {
            try {
                $db->prepare("
                    INSERT INTO receipts (receipt_number, voucher_number, billing_number,
                        register_date, deposit_date, customer_name,
                        case_id, case_number, sales_id, branch_id,
                        subtotal, tax, discount, total_amount,
                        receipt_method, invoice_category, status, bank_ref,
                        note, ragic_id, registrar, created_by, updated_by)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                ")->execute(array(
                    $receiptNo ?: 'RAGIC-' . $ragicId, $voucherNo ?: null, null,
                    $registerDate ?: date('Y-m-d'), $depositDate, $customerName,
                    $caseId, $caseNumber ?: null, $salesId, $branchId,
                    $subtotal, $tax, $discount, $total ?: $subtotal,
                    $method ?: null, $category ?: null, $status, $bankRef ?: null,
                    $note ?: null, $ragicId, $registrar ?: null,
                    1, 1
                ));
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
            $db->prepare("DELETE FROM receipt_items WHERE receipt_id = ?")->execute(array($existId));
            $db->prepare("DELETE FROM receipts WHERE id = ?")->execute(array($existId));
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
