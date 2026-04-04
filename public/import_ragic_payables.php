<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('max_execution_time', 300);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
header('Content-Type: text/html; charset=utf-8');

$db = Database::getInstance();

// 確保 ragic_id 欄位存在
foreach (array(
    "ALTER TABLE payables ADD COLUMN ragic_id INT DEFAULT NULL",
    "ALTER TABLE payables ADD COLUMN vendor_code VARCHAR(20) DEFAULT NULL",
    "ALTER TABLE payables ADD INDEX idx_ragic (ragic_id)",
) as $sql) {
    try { $db->exec($sql); } catch (Exception $e) { /* exists */ }
}

echo "<h2>同步 Ragic 應付帳款單</h2>";

// 讀取 Ragic JSON
$jsonFile = __DIR__ . '/../data/ragic_payables.json';
if (!file_exists($jsonFile)) {
    echo "<p style='color:red'>請先下載 Ragic 資料</p>";
    exit;
}
$ragicData = json_decode(file_get_contents($jsonFile), true);
echo "<p>Ragic 資料: " . count($ragicData) . " 筆</p>";

$inserted = 0;
$updated = 0;
$skipped = 0;

$checkStmt = $db->prepare("SELECT id FROM payables WHERE ragic_id = ?");

foreach ($ragicData as $ragicId => $rec) {
    $ragicId = (int)$ragicId;
    $payableNumber = trim($rec['付款單號'] ?? '');
    if (empty($payableNumber)) { $skipped++; continue; }

    // 解析日期
    $createDate = trim($rec['建立日期'] ?? '');
    if ($createDate) $createDate = str_replace('/', '-', $createDate);

    $vendorName = trim($rec['廠商名稱'] ?? '');
    $vendorCode = trim($rec['廠商編號'] ?? '');
    $paymentPeriod = trim($rec['付款期別'] ?? '');
    $paymentTerms = trim($rec['付款條件'] ?? '');
    $subtotal = (int)($rec['未稅總額'] ?? 0);
    $tax = (int)($rec['稅金'] ?? 0);
    $totalAmount = (int)($rec['總計'] ?? 0);
    $prepaid = (int)($rec['預付總額'] ?? 0);
    $payableAmount = (int)($rec['應付總額'] ?? 0);
    $note = trim($rec['備註7'] ?? '');

    // 分公司拆帳（最多 6 組）
    $branches = array();
    for ($i = 1; $i <= 6; $i++) {
        $suffix = $i === 1 ? '' : $i;
        $branchName = trim($rec['所屬分公司' . $suffix] ?? '');
        $branchAmount = trim($rec['未稅金額' . $suffix] ?? '');
        $branchNote = trim($rec['備註' . $suffix] ?? '');
        if (!empty($branchName) || !empty($branchAmount)) {
            $branches[] = array(
                'branch_name' => $branchName,
                'amount' => (int)$branchAmount,
                'note' => $branchNote,
            );
        }
    }

    // 發票明細
    $invUntaxed = (int)($rec['未稅'] ?? 0);
    $invTax = (int)($rec['稅額'] ?? 0);
    $invSubtotal = (int)($rec['小計'] ?? 0);

    // 檢查是否存在
    $checkStmt->execute(array($ragicId));
    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        // 更新
        $db->prepare("UPDATE payables SET
            payable_number=?, create_date=?, vendor_name=?, vendor_code=?, payment_period=?, payment_terms=?,
            subtotal=?, tax=?, total_amount=?, prepaid=?, payable_amount=?, note=?, updated_by=1
            WHERE ragic_id=?")->execute(array(
            $payableNumber, $createDate, $vendorName, $vendorCode, $paymentPeriod, $paymentTerms,
            $subtotal, $tax, $totalAmount, $prepaid, $payableAmount, $note ?: null,
            $ragicId
        ));
        $id = $existing['id'];
        $updated++;
    } else {
        // 新增
        $db->prepare("INSERT INTO payables
            (payable_number, create_date, vendor_name, vendor_code, payment_period, payment_terms,
             subtotal, tax, total_amount, prepaid, payable_amount, note, ragic_id, created_by, updated_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,1,1)")->execute(array(
            $payableNumber, $createDate, $vendorName, $vendorCode, $paymentPeriod, $paymentTerms,
            $subtotal, $tax, $totalAmount, $prepaid, $payableAmount, $note ?: null,
            $ragicId
        ));
        $id = $db->lastInsertId();
        $inserted++;
    }

    // 儲存分公司拆帳
    if (!empty($branches)) {
        $db->prepare("DELETE FROM payable_branches WHERE payable_id = ?")->execute(array($id));
        $brStmt = $db->prepare("INSERT INTO payable_branches (payable_id, branch_id, branch_name, amount, note) VALUES (?,NULL,?,?,?)");
        foreach ($branches as $br) {
            $brStmt->execute(array($id, $br['branch_name'], $br['amount'], $br['note'] ?: null));
        }
    }
}

// Ragic 沒有的全部刪除（包含系統手建的）
$allStmt = $db->query("SELECT id, payable_number, ragic_id FROM payables");
$ragicIds = array_keys($ragicData);
$deleted = 0;
foreach ($allStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    // 有 ragic_id 但 Ragic 已刪除 → 刪
    // 無 ragic_id（系統手建）→ 也刪
    $isInRagic = !empty($row['ragic_id']) && in_array((string)$row['ragic_id'], $ragicIds);
    if (!$isInRagic) {
        $db->prepare("DELETE FROM payable_branches WHERE payable_id = ?")->execute(array($row['id']));
        $db->prepare("DELETE FROM payable_invoices WHERE payable_id = ?")->execute(array($row['id']));
        $db->prepare("DELETE FROM payable_purchase_details WHERE payable_id = ?")->execute(array($row['id']));
        $db->prepare("DELETE FROM payable_return_details WHERE payable_id = ?")->execute(array($row['id']));
        $db->prepare("DELETE FROM payables WHERE id = ?")->execute(array($row['id']));
        $deleted++;
    }
}

echo "<p style='color:green'>✅ 同步完成</p>";
echo "<ul>";
echo "<li>新增: <strong>{$inserted}</strong> 筆</li>";
echo "<li>更新: <strong>{$updated}</strong> 筆</li>";
echo "<li>刪除（Ragic 已移除）: <strong>{$deleted}</strong> 筆</li>";
echo "<li>跳過: <strong>{$skipped}</strong> 筆</li>";
echo "</ul>";
echo "<p><a href='/payables.php'>← 回應付帳款列表</a></p>";
