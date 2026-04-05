<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();
$jsonFile = __DIR__ . '/../database/ragic_cases_20260405.json';
$ragicData = json_decode(file_get_contents($jsonFile), true);

echo "=== 子表格同步驗證 ===\n\n";

$payMissing = 0; $payDiff = 0; $payOk = 0;
$wlMissing = 0; $wlDiff = 0; $wlOk = 0;
$payDetails = array();
$wlDetails = array();

foreach ($ragicData as $rid => $r) {
    $cn = trim(isset($r['進件編號']) ? $r['進件編號'] : '');
    if (!$cn) continue;

    $stmt = $db->prepare("SELECT id FROM cases WHERE case_number = ?");
    $stmt->execute(array($cn));
    $case = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$case) continue;
    $caseId = $case['id'];

    // 帳款
    if (!empty($r['_subtable_1007228'])) {
        foreach ($r['_subtable_1007228'] as $subRid => $row) {
            $ragicId = 'st1007228_' . $subRid;
            $chk = $db->prepare("SELECT id, payment_date, amount FROM case_payments WHERE case_id = ? AND ragic_id = ?");
            $chk->execute(array($caseId, $ragicId));
            $ex = $chk->fetch(PDO::FETCH_ASSOC);
            if (!$ex) {
                $payMissing++;
                $payDetails[] = "[缺] {$cn} {$ragicId}";
            } else {
                $ragicAmt = isset($row['金額']) ? (int)$row['金額'] : 0;
                if ((int)$ex['amount'] !== $ragicAmt) {
                    $payDiff++;
                    $payDetails[] = "[金額不同] {$cn} {$ragicId}: 系統={$ex['amount']} Ragic={$ragicAmt}";
                } else {
                    $payOk++;
                }
            }
        }
    }

    // 施工
    if (!empty($r['_subtable_1007229'])) {
        foreach ($r['_subtable_1007229'] as $subRid => $row) {
            $ragicId = 'st1007229_' . $subRid;
            $chk = $db->prepare("SELECT id, work_date, work_content FROM case_work_logs WHERE case_id = ? AND ragic_id = ?");
            $chk->execute(array($caseId, $ragicId));
            $ex = $chk->fetch(PDO::FETCH_ASSOC);
            if (!$ex) {
                $wlMissing++;
                $wlDetails[] = "[缺] {$cn} {$ragicId}";
            } else {
                $wlOk++;
            }
        }
    }
}

echo "--- 帳款交易 ---\n";
echo "完全一致: {$payOk} 筆\n";
echo "缺少: {$payMissing} 筆\n";
echo "金額不同: {$payDiff} 筆\n";
if (!empty($payDetails)) {
    echo "\n問題明細:\n";
    foreach ($payDetails as $d) echo "  {$d}\n";
}

echo "\n--- 施工回報 ---\n";
echo "完全一致: {$wlOk} 筆\n";
echo "缺少: {$wlMissing} 筆\n";
if (!empty($wlDetails)) {
    echo "\n問題明細:\n";
    foreach ($wlDetails as $d) echo "  {$d}\n";
}

// 總筆數
$sysPayCount = $db->query("SELECT COUNT(*) FROM case_payments")->fetchColumn();
$sysWlCount = $db->query("SELECT COUNT(*) FROM case_work_logs")->fetchColumn();
echo "\n--- 系統總筆數 ---\n";
echo "case_payments: {$sysPayCount}\n";
echo "case_work_logs: {$sysWlCount}\n";

echo "\n驗證完成。\n";
