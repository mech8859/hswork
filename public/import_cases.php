<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('max_execution_time', 120);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();

$step = isset($_GET['step']) ? (int)$_GET['step'] : -1;
$batch = 200;
$jsonFile = __DIR__ . '/cases_import.json';

if (!file_exists($jsonFile)) {
    echo "ERROR: cases_import.json not found. Upload it first.";
    exit;
}

// Delete action - must check BEFORE preview
if (isset($_GET['action']) && $_GET['action'] === 'delete_imported') {
    echo "<h3>刪除匯入案件</h3>";
    $before = $db->query("SELECT COUNT(*) FROM cases WHERE ragic_id IS NOT NULL AND ragic_id != ''")->fetchColumn();
    echo "準備刪除 {$before} 筆...<br>";
    ob_flush(); flush();
    $deleted = 0;
    while (true) {
        $del = $db->exec("DELETE FROM cases WHERE ragic_id IS NOT NULL AND ragic_id != '' LIMIT 1000");
        if ($del == 0) break;
        $deleted += $del;
        echo "已刪除 {$deleted} 筆...<br>";
        ob_flush(); flush();
    }
    echo "<br><b>完成！共刪除 {$deleted} 筆匯入案件</b><br><br>";
    echo "<a href='?' style='background:#2196F3;color:#fff;padding:8px 16px;border-radius:6px;text-decoration:none'>返回預覽</a>";
    exit;
}

$all = json_decode(file_get_contents($jsonFile), true);
$total = count($all);
$totalSteps = ceil($total / $batch);

echo "<h3>案件資料匯入（Ragic）</h3>";
echo "總筆數: {$total} | 每批: {$batch} | 總步數: {$totalSteps}<br><br>";

// Build lookup maps
$branchMap = array();
$brStmt = $db->query("SELECT id, code FROM branches");
while ($br = $brStmt->fetch(PDO::FETCH_ASSOC)) {
    $branchMap[$br['code']] = $br['id'];
}

$userMap = array();
$uStmt = $db->query("SELECT id, real_name FROM users WHERE is_active = 1");
while ($u = $uStmt->fetch(PDO::FETCH_ASSOC)) {
    $userMap[$u['real_name']] = $u['id'];
}

$customerMap = array();
$cStmt = $db->query("SELECT id, name FROM customers WHERE is_active = 1");
while ($c = $cStmt->fetch(PDO::FETCH_ASSOC)) {
    $customerMap[$c['name']] = $c['id'];
}

if ($step < 0) {
    // Preview mode
    echo "<b>預覽模式</b> - 不會寫入資料<br><br>";

    // Stage stats
    $stages = array(0 => 0, 1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0, 7 => 0);
    $stageLabels = array(0 => '失敗/取消', 1 => '進件', 2 => '場勘', 3 => '報價', 4 => '成交待排工', 5 => '派工/尚未進場', 6 => '施工中/未完工', 7 => '已完工結案');
    foreach ($all as $r) {
        $s = isset($r['stage']) ? (int)$r['stage'] : 1;
        if (isset($stages[$s])) $stages[$s]++;
    }
    echo "<b>Stage 分布：</b><br>";
    foreach ($stages as $s => $cnt) {
        if ($cnt > 0) echo "&nbsp;&nbsp;{$s} ({$stageLabels[$s]}): {$cnt} 筆<br>";
    }

    // Customer matching preview
    $matched = 0;
    $unmatched = 0;
    foreach ($all as $r) {
        $name = isset($r['customer_name']) ? $r['customer_name'] : '';
        if ($name && isset($customerMap[$name])) {
            $matched++;
        } else {
            $unmatched++;
        }
    }
    echo "<br><b>客戶比對：</b> 已匹配 {$matched} 筆，未匹配 {$unmatched} 筆<br>";

    // Sales matching
    $salesMatched = 0;
    $salesUnmatched = array();
    foreach ($all as $r) {
        $name = isset($r['sales_name']) ? $r['sales_name'] : '';
        if ($name && isset($userMap[$name])) {
            $salesMatched++;
        } elseif ($name) {
            if (!isset($salesUnmatched[$name])) $salesUnmatched[$name] = 0;
            $salesUnmatched[$name]++;
        }
    }
    echo "<b>業務比對：</b> 已匹配 {$salesMatched} 筆<br>";
    if (!empty($salesUnmatched)) {
        echo "<b style='color:#e65100'>未匹配業務：</b>";
        arsort($salesUnmatched);
        $parts = array();
        foreach ($salesUnmatched as $n => $c) $parts[] = htmlspecialchars($n) . "({$c})";
        echo implode(', ', $parts) . "<br>";
    }
    echo "<br><b>系統現有人員：</b> ";
    $uNames = array();
    foreach ($userMap as $n => $uid) $uNames[] = htmlspecialchars($n);
    echo implode(', ', $uNames) . "<br>";

    // Branch matching
    $branchMatched = 0;
    foreach ($all as $r) {
        $code = isset($r['branch_code']) ? $r['branch_code'] : '';
        if ($code && isset($branchMap[$code])) $branchMatched++;
    }
    echo "<b>分公司比對：</b> 已匹配 {$branchMatched} 筆<br>";

    // Existing cases
    $existing = $db->query("SELECT COUNT(*) FROM cases")->fetchColumn();
    $imported = $db->query("SELECT COUNT(*) FROM cases WHERE ragic_id IS NOT NULL AND ragic_id != ''")->fetchColumn();
    echo "<br>目前資料庫案件數: {$existing}<br>";
    echo "已匯入(有ragic_id): {$imported}<br><br>";

    echo "<a href='?step=0' style='background:#4CAF50;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;font-size:1.1em'>開始匯入 (Step 0)</a><br><br>";
    echo "<a href='?action=delete_imported' style='background:#f44336;color:#fff;padding:8px 16px;border-radius:6px;text-decoration:none'>清除所有匯入案件</a>";
    exit;
}

// Import batch
$start = $step * $batch;
$subset = array_slice($all, $start, $batch);

if (empty($subset)) {
    echo "ALL DONE! 所有步驟完成。<br>";
    $total_imported = $db->query("SELECT COUNT(*) FROM cases WHERE ragic_id IS NOT NULL AND ragic_id != ''")->fetchColumn();
    echo "匯入總數: {$total_imported}<br>";
    echo "<a href='/business_tracking.php'>業務追蹤表</a> | <a href='/cases.php'>案件管理</a>";
    exit;
}

echo "Step {$step}/{$totalSteps} (筆 " . ($start+1) . " ~ " . min($start+$batch, $total) . ")<br>";

$inserted = 0;
$updated = 0;
$skipped = 0;

$findByNumber = $db->prepare("SELECT id FROM cases WHERE case_number = ? LIMIT 1");

$insertSql = "INSERT INTO cases (case_number, title, case_type, case_source, status, stage, sub_status,
    branch_id, company, customer_name, customer_id, contact_person, customer_phone, customer_mobile,
    address, city, district, sales_id, sales_note, description,
    system_type, quote_amount,
    deal_date, deal_amount, tax_included, tax_amount, total_amount,
    deposit_amount, deposit_date, deposit_method, balance_amount,
    completed_date, invoice_title, tax_id_number,
    settlement_date, settlement_method, settlement_confirmed,
    lost_reason, ragic_id, est_start_date, est_end_date, est_workers, est_days,
    created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?,
    ?, ?, ?, ?, ?, ?, ?,
    ?, ?, ?, ?, ?, ?,
    ?, ?,
    ?, ?, ?, ?, ?,
    ?, ?, ?, ?,
    ?, ?, ?,
    ?, ?, ?,
    ?, ?, ?, ?, ?, ?,
    ?, NOW())";
$insertStmt = $db->prepare($insertSql);

$updateSql = "UPDATE cases SET title=?, case_type=?, case_source=?, status=?, stage=?, sub_status=?,
    branch_id=?, company=?, customer_name=?, customer_id=?, contact_person=?, customer_phone=?, customer_mobile=?,
    address=?, city=?, district=?, sales_id=?, sales_note=?, description=?,
    system_type=?, quote_amount=?,
    deal_date=?, deal_amount=?, tax_included=?, tax_amount=?, total_amount=?,
    deposit_amount=?, deposit_date=?, deposit_method=?, balance_amount=?,
    completed_date=?, invoice_title=?, tax_id_number=?,
    settlement_date=?, settlement_method=?, settlement_confirmed=?,
    lost_reason=?, ragic_id=?, est_start_date=?, est_end_date=?, est_workers=?, est_days=?,
    updated_at=NOW() WHERE id=?";
$updateStmt = $db->prepare($updateSql);

foreach ($subset as $r) {
    $caseNo = isset($r['case_number']) ? $r['case_number'] : null;
    if (empty($caseNo)) { $skipped++; continue; }

    // Resolve foreign keys
    $branchId = isset($branchMap['TZ']) ? $branchMap['TZ'] : 1; // default to 潭子
    if (!empty($r['branch_code']) && isset($branchMap[$r['branch_code']])) {
        $branchId = $branchMap[$r['branch_code']];
    }

    $salesId = null;
    if (!empty($r['sales_name']) && isset($userMap[$r['sales_name']])) {
        $salesId = $userMap[$r['sales_name']];
    }

    $customerId = null;
    if (!empty($r['customer_name']) && isset($customerMap[$r['customer_name']])) {
        $customerId = $customerMap[$r['customer_name']];
    }

    // 案件進度對應
    $progressMap = array(
        '未完工' => 'incomplete',
        '已完工結案' => 'closed',
        '完工未收款' => 'unpaid',
        '已完工未收款' => 'unpaid',
        '待追蹤' => 'tracking',
        '客戶取消' => 'customer_cancel',
        '毀約' => 'breach',
        '未成交' => 'lost',
        '保養案件' => 'maint_case',
        '待安排派工查修' => 'awaiting_dispatch',
    );
    $rawStatus = isset($r['status']) ? $r['status'] : '';
    if (isset($progressMap[$rawStatus])) {
        $status = $progressMap[$rawStatus];
    } elseif ($rawStatus && in_array($rawStatus, array('tracking','incomplete','closed','unpaid','lost','maint_case','breach','awaiting_dispatch','customer_cancel'))) {
        $status = $rawStatus;
    } elseif ($rawStatus === 'completed') {
        $status = 'closed';
    } else {
        $status = 'tracking';
    }

    $vals = array(
        $r['title'],
        $r['case_type'] ?: 'other',
        $r['case_source'] ?: null,
        $status,
        isset($r['stage']) ? (int)$r['stage'] : 1,
        isset($r['sub_status']) ? $r['sub_status'] : null,
        $branchId,
        $r['company'] ?: null,
        $r['customer_name'] ?: null,
        $customerId,
        $r['contact_person'] ?: null,
        $r['customer_phone'] ?: null,
        $r['customer_mobile'] ?: null,
        $r['address'] ?: null,
        $r['city'] ?: null,
        $r['district'] ?: null,
        $salesId,
        null, // sales_note
        $r['description'] ?: null,
        $r['system_type'] ?: null,
        $r['quote_amount'] ?: null,
        $r['deal_date'] ?: null,
        $r['deal_amount'] ?: null,
        isset($r['tax_included']) ? (int)$r['tax_included'] : 0,
        $r['tax_amount'] ?: null,
        $r['total_amount'] ?: null,
        $r['deposit_amount'] ?: null,
        $r['deposit_date'] ?: null,
        $r['deposit_method'] ?: null,
        $r['balance_amount'] ?: null,
        $r['completed_date'] ?: null,
        $r['invoice_title'] ?: null,
        $r['tax_id_number'] ?: null,
        $r['settlement_date'] ?: null,
        $r['settlement_method'] ?: null,
        isset($r['settlement_confirmed']) ? (int)$r['settlement_confirmed'] : 0,
        $r['lost_reason'] ?: null,
        $caseNo, // ragic_id = case_number as identifier
        $r['est_start_date'] ?: null,
        $r['est_end_date'] ?: null,
        $r['est_workers'] ?: null,
        $r['est_days'] ?: null,
    );

    $findByNumber->execute(array($caseNo));
    $exists = $findByNumber->fetch(PDO::FETCH_ASSOC);

    if ($exists) {
        // Update
        $updateVals = $vals;
        $updateVals[] = $exists['id'];
        try {
            $updateStmt->execute($updateVals);
            $updated++;
        } catch (Exception $e) {
            echo "<small style='color:red'>更新失敗 {$caseNo}: " . htmlspecialchars($e->getMessage()) . "</small><br>";
            $skipped++;
        }
    } else {
        // Insert
        $insertVals = array($caseNo);
        $insertVals = array_merge($insertVals, $vals);
        $insertVals[] = $r['created_at'] ?: date('Y-m-d'); // created_at
        try {
            $insertStmt->execute($insertVals);
            $inserted++;
        } catch (Exception $e) {
            echo "<small style='color:red'>新增失敗 {$caseNo}: " . htmlspecialchars($e->getMessage()) . "</small><br>";
            $skipped++;
        }
    }
}

echo "新增: {$inserted} | 更新: {$updated} | 跳過: {$skipped}<br><br>";

$nextStep = $step + 1;
if ($nextStep < $totalSteps) {
    echo "<a href='?step={$nextStep}' style='background:#2196F3;color:#fff;padding:8px 16px;border-radius:6px;text-decoration:none'>下一步 Step {$nextStep} →</a>";
    echo "<script>setTimeout(function(){ window.location='?step={$nextStep}'; }, 1000);</script>";
} else {
    echo "<b>ALL DONE!</b><br>";
    $total_imported = $db->query("SELECT COUNT(*) FROM cases WHERE ragic_id IS NOT NULL AND ragic_id != ''")->fetchColumn();
    echo "匯入總數: {$total_imported}<br>";
    echo "<a href='/business_tracking.php'>業務追蹤表</a> | <a href='/cases.php'>案件管理</a>";
}
