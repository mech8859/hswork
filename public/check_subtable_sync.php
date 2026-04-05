<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();

$jsonFile = __DIR__ . '/../database/ragic_cases_20260405.json';
if (!file_exists($jsonFile)) die('JSON not found');
$ragicData = json_decode(file_get_contents($jsonFile), true);

echo "=== 子表格同步比對 ===\n\n";

// 統計 Ragic 子表格筆數
$ragicAttachCount = 0;
$ragicPaymentCount = 0;
$ragicWorklogCount = 0;
$ragicAttachCases = 0;
$ragicPaymentCases = 0;
$ragicWorklogCases = 0;

foreach ($ragicData as $rid => $r) {
    if (!empty($r['_subtable_1004158'])) {
        $ragicAttachCases++;
        $ragicAttachCount += count($r['_subtable_1004158']);
    }
    if (!empty($r['_subtable_1007228'])) {
        $ragicPaymentCases++;
        $ragicPaymentCount += count($r['_subtable_1007228']);
    }
    if (!empty($r['_subtable_1007229'])) {
        $ragicWorklogCases++;
        $ragicWorklogCount += count($r['_subtable_1007229']);
    }
}

echo "--- Ragic 子表格統計 ---\n";
echo "附件文件: {$ragicAttachCount} 筆 ({$ragicAttachCases} 個案件)\n";
echo "帳款交易: {$ragicPaymentCount} 筆 ({$ragicPaymentCases} 個案件)\n";
echo "施工回報: {$ragicWorklogCount} 筆 ({$ragicWorklogCases} 個案件)\n\n";

// 系統現有筆數
$sysAttach = $db->query("SELECT COUNT(*) FROM case_attachments")->fetchColumn();
$sysPayment = $db->query("SELECT COUNT(*) FROM case_payments")->fetchColumn();

// case_work_logs 可能不存在
try {
    $sysWorklog = $db->query("SELECT COUNT(*) FROM case_work_logs")->fetchColumn();
} catch (Exception $e) {
    $sysWorklog = '表不存在';
}

echo "--- 系統現有資料 ---\n";
echo "case_attachments: {$sysAttach} 筆\n";
echo "case_payments: {$sysPayment} 筆\n";
echo "case_work_logs: {$sysWorklog} 筆\n\n";

// 比對幾筆案件的細節
echo "--- 抽樣比對（前5筆有子表格的案件）---\n\n";
$sampleCount = 0;
foreach ($ragicData as $rid => $r) {
    $cn = trim(isset($r['進件編號']) ? $r['進件編號'] : '');
    if (!$cn) continue;

    $hasAny = !empty($r['_subtable_1004158']) || !empty($r['_subtable_1007228']) || !empty($r['_subtable_1007229']);
    if (!$hasAny) continue;

    $stmt = $db->prepare("SELECT id FROM cases WHERE case_number = ?");
    $stmt->execute(array($cn));
    $case = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$case) continue;
    $caseId = $case['id'];

    echo "案件: {$cn} (id={$caseId})\n";

    // 附件
    $ragicA = !empty($r['_subtable_1004158']) ? count($r['_subtable_1004158']) : 0;
    $sysA = $db->prepare("SELECT COUNT(*) FROM case_attachments WHERE case_id = ?");
    $sysA->execute(array($caseId));
    $sysACount = $sysA->fetchColumn();
    $match = ($ragicA > 0 && $sysACount > 0) ? '有資料' : ($ragicA == 0 ? '無' : '缺');
    echo "  附件: Ragic {$ragicA} rows / 系統 {$sysACount} 筆 [{$match}]\n";

    // 帳款
    $ragicP = !empty($r['_subtable_1007228']) ? count($r['_subtable_1007228']) : 0;
    $sysP = $db->prepare("SELECT COUNT(*) FROM case_payments WHERE case_id = ?");
    $sysP->execute(array($caseId));
    $sysPCount = $sysP->fetchColumn();
    $match = ($ragicP > 0 && $sysPCount > 0) ? '有資料' : ($ragicP == 0 ? '無' : '缺');
    echo "  帳款: Ragic {$ragicP} rows / 系統 {$sysPCount} 筆 [{$match}]\n";

    // 施工回報
    $ragicW = !empty($r['_subtable_1007229']) ? count($r['_subtable_1007229']) : 0;
    try {
        $sysW = $db->prepare("SELECT COUNT(*) FROM case_work_logs WHERE case_id = ?");
        $sysW->execute(array($caseId));
        $sysWCount = $sysW->fetchColumn();
    } catch (Exception $e) {
        $sysWCount = 'N/A';
    }
    $match2 = ($ragicW > 0 && $sysWCount > 0) ? '有資料' : ($ragicW == 0 ? '無' : '缺');
    echo "  施工: Ragic {$ragicW} rows / 系統 {$sysWCount} 筆 [{$match2}]\n";

    echo "\n";
    $sampleCount++;
    if ($sampleCount >= 10) break;
}

// 比對有 ragic_id 的筆數
$ragicIdAttach = $db->query("SELECT COUNT(*) FROM case_attachments WHERE note LIKE 'ragic_pending:%'")->fetchColumn();
$ragicIdPayment = $db->query("SELECT COUNT(*) FROM case_payments WHERE ragic_id LIKE 'st1007228_%'")->fetchColumn();
try {
    $ragicIdWorklog = $db->query("SELECT COUNT(*) FROM case_work_logs WHERE ragic_id LIKE 'st1007229_%'")->fetchColumn();
} catch (Exception $e) {
    $ragicIdWorklog = 'N/A';
}

echo "--- 已標記 Ragic 來源的筆數 ---\n";
echo "附件 (ragic_pending): {$ragicIdAttach} 筆\n";
echo "帳款 (st1007228_*): {$ragicIdPayment} 筆\n";
echo "施工 (st1007229_*): {$ragicIdWorklog} 筆\n";
