<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();
$dryRun = !isset($_GET['execute']);
$jsonFile = __DIR__ . '/../database/ragic_cases_20260405.json';
$ragicData = json_decode(file_get_contents($jsonFile), true);

echo "=== 補齊缺少的子表格（" . ($dryRun ? '預覽' : '執行') . "）===\n\n";

// 缺少的 ragic_id
$missingPay = array(
    '2026-1017'=>'587','2026-0960'=>'1044','2026-0934'=>'948','2026-0861'=>'774',
    '2026-0708'=>'920','202512-286'=>'484','2026-0540'=>'350','2026-0435'=>'921',
    '202512-253'=>'169','202512-237'=>'721','202512-206'=>'975',
    '202512-077'=>'748','202512-077'=>'749','202512-075'=>'756',
    '202512-074'=>'755','202512-070'=>'754','202512-066'=>'753',
    '202512-059'=>'752','202512-054'=>'751','202512-053'=>'750','202512-016'=>'628'
);
$missingWl = array(
    '2026-1385'=>'1021','2026-1384'=>'1020','202512-178'=>'452','202512-177'=>'960'
);

$payInserted = 0;
$wlInserted = 0;

// 補帳款
foreach ($ragicData as $rid => $r) {
    $cn = trim(isset($r['進件編號']) ? $r['進件編號'] : '');
    if (!$cn || empty($r['_subtable_1007228'])) continue;

    $stmt = $db->prepare("SELECT id FROM cases WHERE case_number = ?");
    $stmt->execute(array($cn));
    $case = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$case) continue;
    $caseId = $case['id'];

    foreach ($r['_subtable_1007228'] as $subRid => $row) {
        $ragicId = 'st1007228_' . $subRid;
        // 檢查是否在缺少清單中
        $chk = $db->prepare("SELECT id FROM case_payments WHERE case_id = ? AND ragic_id = ?");
        $chk->execute(array($caseId, $ragicId));
        if ($chk->fetch()) continue;

        // 這筆確實缺少，插入
        $date = isset($row['帳款交易日期']) ? str_replace('/', '-', trim($row['帳款交易日期'])) : null;
        $paymentType = isset($row['帳款類別']) && $row['帳款類別'] !== '' ? trim($row['帳款類別']) : null;
        $rawTrans = isset($row['交易內容']) && $row['交易內容'] !== '' ? trim($row['交易內容']) : null;
        $amount = isset($row['金額']) ? (int)$row['金額'] : 0;
        $rawNote = isset($row['備註']) && $row['備註'] !== '' ? trim($row['備註']) : null;

        if ($rawTrans && mb_strlen($rawTrans) > 15) {
            $transType = null;
            $note = $rawNote ? $rawTrans . "\n" . $rawNote : $rawTrans;
        } else {
            $transType = $rawTrans;
            $note = $rawNote;
        }

        echo "[帳款] {$cn} {$ragicId}: 日期={$date} 類別={$paymentType} 方式={$transType} 金額={$amount} 備註=" . ($note ?: '無') . "\n";

        if (!$dryRun) {
            $db->prepare("INSERT INTO case_payments (case_id, payment_date, payment_type, transaction_type, amount, note, ragic_id, created_by) VALUES (?,?,?,?,?,?,?,?)")
               ->execute(array($caseId, $date, $paymentType, $transType, $amount, $note, $ragicId, Auth::id()));
        }
        $payInserted++;
    }
}

// 補施工
foreach ($ragicData as $rid => $r) {
    $cn = trim(isset($r['進件編號']) ? $r['進件編號'] : '');
    if (!$cn || empty($r['_subtable_1007229'])) continue;

    $stmt = $db->prepare("SELECT id FROM cases WHERE case_number = ?");
    $stmt->execute(array($cn));
    $case = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$case) continue;
    $caseId = $case['id'];

    foreach ($r['_subtable_1007229'] as $subRid => $row) {
        $ragicId = 'st1007229_' . $subRid;
        $chk = $db->prepare("SELECT id FROM case_work_logs WHERE case_id = ? AND ragic_id = ?");
        $chk->execute(array($caseId, $ragicId));
        if ($chk->fetch()) continue;

        $workDate = !empty($row['施工日期']) ? str_replace('/', '-', trim($row['施工日期'])) : null;
        $content = isset($row['施工內容']) ? trim($row['施工內容']) : '';
        $equipment = isset($row['使用器材']) ? trim($row['使用器材']) : null;
        $cable = isset($row['使用線材']) ? trim($row['使用線材']) : null;

        $photos = array();
        if (!empty($row['施工照片'])) {
            $photoData = $row['施工照片'];
            if (is_array($photoData)) { $photos = $photoData; }
            elseif (is_string($photoData) && !empty($photoData)) { $photos[] = $photoData; }
        }
        $photoPaths = !empty($photos) ? json_encode($photos, JSON_UNESCAPED_UNICODE) : null;

        echo "[施工] {$cn} {$ragicId}: 日期={$workDate} 內容=" . mb_substr($content, 0, 30) . "\n";

        if (!$dryRun) {
            $db->prepare("INSERT INTO case_work_logs (case_id, work_date, work_content, equipment_used, cable_used, photo_paths, ragic_id, created_by) VALUES (?,?,?,?,?,?,?,?)")
               ->execute(array($caseId, $workDate, $content, $equipment, $cable, $photoPaths, $ragicId, Auth::id()));
        }
        $wlInserted++;
    }
}

echo "\n--- 結果 ---\n";
echo "帳款補齊: {$payInserted} 筆\n";
echo "施工補齊: {$wlInserted} 筆\n";

if ($dryRun && ($payInserted + $wlInserted > 0)) {
    echo "\n確認無誤後，加 ?execute=1 執行\n";
}
