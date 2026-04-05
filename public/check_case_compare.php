<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();
$cn = isset($_GET['case']) ? trim($_GET['case']) : '202512-057';

$jsonFile = __DIR__ . '/../database/ragic_cases_20260405.json';
$ragicData = json_decode(file_get_contents($jsonFile), true);

// 找 Ragic 資料
$ragicCase = null;
foreach ($ragicData as $rid => $r) {
    if (isset($r['進件編號']) && trim($r['進件編號']) === $cn) {
        $ragicCase = $r;
        break;
    }
}

if (!$ragicCase) { echo "Ragic 找不到案件 {$cn}\n"; exit; }

// 找系統資料
$stmt = $db->prepare("SELECT id FROM cases WHERE case_number = ?");
$stmt->execute(array($cn));
$case = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$case) { echo "系統找不到案件 {$cn}\n"; exit; }
$caseId = $case['id'];

echo "=== 案件 {$cn} (id={$caseId}) 比對 ===\n\n";

// 帳款
echo "--- 帳款交易 ---\n";
$sysPayments = $db->prepare("SELECT * FROM case_payments WHERE case_id = ? ORDER BY payment_date");
$sysPayments->execute(array($caseId));
$sysPayments = $sysPayments->fetchAll(PDO::FETCH_ASSOC);
echo "系統 " . count($sysPayments) . " 筆:\n";
foreach ($sysPayments as $p) {
    echo "  [{$p['ragic_id']}] {$p['payment_date']} | 類別:{$p['payment_type']} | 交易:{$p['transaction_type']} | \${$p['amount']} | 備註:{$p['note']}\n";
}

$ragicPayments = isset($ragicCase['_subtable_1007228']) ? $ragicCase['_subtable_1007228'] : array();
echo "\nRagic " . count($ragicPayments) . " 筆:\n";
foreach ($ragicPayments as $rid => $row) {
    $date = isset($row['帳款交易日期']) ? $row['帳款交易日期'] : '';
    $type = isset($row['帳款類別']) ? $row['帳款類別'] : '';
    $trans = isset($row['交易內容']) ? $row['交易內容'] : '';
    $amt = isset($row['金額']) ? $row['金額'] : '';
    $note = isset($row['備註']) ? $row['備註'] : '';
    echo "  [st1007228_{$rid}] {$date} | 類別:{$type} | 交易:{$trans} | \${$amt} | 備註:{$note}\n";
}

// 施工回報
echo "\n--- 施工回報 ---\n";
$sysWl = $db->prepare("SELECT * FROM case_work_logs WHERE case_id = ? ORDER BY work_date");
$sysWl->execute(array($caseId));
$sysWl = $sysWl->fetchAll(PDO::FETCH_ASSOC);
echo "系統 " . count($sysWl) . " 筆:\n";
foreach ($sysWl as $w) {
    echo "  [{$w['ragic_id']}] {$w['work_date']} | " . mb_substr($w['work_content'], 0, 40) . " | 照片:" . ($w['photo_paths'] ?: '無') . "\n";
}

$ragicWl = isset($ragicCase['_subtable_1007229']) ? $ragicCase['_subtable_1007229'] : array();
echo "\nRagic " . count($ragicWl) . " 筆:\n";
foreach ($ragicWl as $rid => $row) {
    $date = isset($row['施工日期']) ? $row['施工日期'] : '';
    $content = isset($row['施工內容']) ? mb_substr($row['施工內容'], 0, 40) : '';
    $photos = isset($row['施工照片']) ? (is_array($row['施工照片']) ? count($row['施工照片']) . '張' : ($row['施工照片'] ? '1張' : '無')) : '無';
    echo "  [st1007229_{$rid}] {$date} | {$content} | 照片:{$photos}\n";
}

// 附件
echo "\n--- 附件 ---\n";
$sysAtt = $db->prepare("SELECT * FROM case_attachments WHERE case_id = ? ORDER BY created_at");
$sysAtt->execute(array($caseId));
$sysAtt = $sysAtt->fetchAll(PDO::FETCH_ASSOC);
echo "系統 " . count($sysAtt) . " 筆:\n";
foreach ($sysAtt as $a) {
    echo "  [{$a['file_type']}] {$a['file_name']} | note:{$a['note']}\n";
}

$ragicAtt = isset($ragicCase['_subtable_1004158']) ? $ragicCase['_subtable_1004158'] : array();
echo "\nRagic " . count($ragicAtt) . " rows:\n";
$fields = array('報價單','報價單(檔案)','圖面','保固書','檔案','檔案2','相片');
foreach ($ragicAtt as $rid => $row) {
    foreach ($fields as $f) {
        $v = isset($row[$f]) ? $row[$f] : '';
        if (empty($v) || $v === '-') continue;
        if (is_array($v)) {
            foreach ($v as $item) echo "  [{$f}] {$item}\n";
        } else {
            echo "  [{$f}] {$v}\n";
        }
    }
}
