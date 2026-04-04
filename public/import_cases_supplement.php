<?php
/**
 * 補匯入 system_type, quote_amount 等缺失欄位
 * 使用 cases_import.json 依 case_number 比對更新
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('max_execution_time', 120);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();

$jsonFile = __DIR__ . '/cases_import.json';
if (!file_exists($jsonFile)) {
    echo "ERROR: cases_import.json not found.";
    exit;
}

$all = json_decode(file_get_contents($jsonFile), true);
$total = count($all);

$step = isset($_GET['step']) ? (int)$_GET['step'] : -1;

if ($step < 0) {
    // Preview
    echo "<h3>補匯入 system_type / quote_amount</h3>";
    echo "JSON 筆數: {$total}<br>";

    $st = 0; $qa = 0;
    foreach ($all as $r) {
        if (!empty($r['system_type'])) $st++;
        if (!empty($r['quote_amount'])) $qa++;
    }
    echo "有 system_type: {$st}<br>";
    echo "有 quote_amount: {$qa}<br><br>";

    // Check current DB
    $dbSt = $db->query("SELECT COUNT(*) FROM cases WHERE system_type IS NOT NULL AND system_type != ''")->fetchColumn();
    $dbQa = $db->query("SELECT COUNT(*) FROM cases WHERE quote_amount IS NOT NULL AND quote_amount > 0")->fetchColumn();
    echo "DB 現有 system_type: {$dbSt}<br>";
    echo "DB 現有 quote_amount: {$dbQa}<br><br>";

    echo "<a href='?step=0' style='background:#4CAF50;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none'>開始補匯入</a>";
    exit;
}

// Run update
$updateStmt = $db->prepare("UPDATE cases SET system_type = ?, quote_amount = ? WHERE case_number = ?");

$updated = 0;
$skipped = 0;

foreach ($all as $r) {
    $caseNo = isset($r['case_number']) ? $r['case_number'] : '';
    if (empty($caseNo)) { $skipped++; continue; }

    $systemType = !empty($r['system_type']) ? $r['system_type'] : null;
    $quoteAmount = !empty($r['quote_amount']) ? $r['quote_amount'] : null;

    if ($systemType === null && $quoteAmount === null) {
        $skipped++;
        continue;
    }

    try {
        $updateStmt->execute(array($systemType, $quoteAmount, $caseNo));
        if ($updateStmt->rowCount() > 0) {
            $updated++;
        } else {
            $skipped++;
        }
    } catch (Exception $e) {
        echo "<small style='color:red'>失敗 {$caseNo}: " . htmlspecialchars($e->getMessage()) . "</small><br>";
        $skipped++;
    }
}

echo "<h3>補匯入完成</h3>";
echo "更新: {$updated} | 跳過: {$skipped}<br><br>";

$dbSt = $db->query("SELECT COUNT(*) FROM cases WHERE system_type IS NOT NULL AND system_type != ''")->fetchColumn();
$dbQa = $db->query("SELECT COUNT(*) FROM cases WHERE quote_amount IS NOT NULL AND quote_amount > 0")->fetchColumn();
echo "DB system_type: {$dbSt}<br>";
echo "DB quote_amount: {$dbQa}<br><br>";
echo "<a href='/cases.php'>案件管理</a>";
