<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!in_array(Session::getUser()['role'], array('boss', 'vice_president'))) die('需要管理員權限');

set_time_limit(300);
header('Content-Type: text/html; charset=utf-8');
echo '<h2>Ragic 應付帳款 - 分公司拆帳同步</h2><pre>';

$db = Database::getInstance();

$jsonFile = __DIR__ . '/../database/ragic_payables_acc44_20260405.json';
if (!file_exists($jsonFile)) die('找不到 JSON');
$ragicData = json_decode(file_get_contents($jsonFile), true);
echo "Ragic: " . count($ragicData) . " 筆\n";

$branchMap = array();
$bStmt = $db->query('SELECT id, name FROM branches');
while ($b = $bStmt->fetch(PDO::FETCH_ASSOC)) $branchMap[$b['name']] = $b['id'];

// 系統應付帳款 by payable_number
$sysPayables = array();
$stmt = $db->query('SELECT id, payable_number FROM payables');
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $sysPayables[$r['payable_number']] = $r['id'];
}
echo "系統應付帳款: " . count($sysPayables) . " 筆\n";

$existBranches = (int)$db->query("SELECT COUNT(*) FROM payable_branches")->fetchColumn();
echo "現有分公司拆帳: {$existBranches} 筆\n\n";

$parseNum = function($v) {
    if (!$v || !trim($v)) return 0;
    $v = str_replace(',', '', trim($v));
    return is_numeric($v) ? (int)round((float)$v) : 0;
};

$matchCount = 0;
$noMatchCount = 0;
$itemCount = 0;
$errorCount = 0;

$insertStmt = $db->prepare("INSERT INTO payable_branches (payable_id, branch_id, amount, note) VALUES (?, ?, ?, ?)");

foreach ($ragicData as $ragicId => $r) {
    $payableNumber = trim($r['付款單號'] ?? '');
    if (!$payableNumber || !isset($sysPayables[$payableNumber])) {
        $noMatchCount++;
        if ($payableNumber) echo "⚠ 找不到: {$payableNumber}\n";
        continue;
    }

    $payableId = $sysPayables[$payableNumber];

    // 收集分公司拆帳（最多6組）
    $branches = array();
    $suffixes = array('', '2', '3', '4', '5', '6');
    foreach ($suffixes as $s) {
        $bn = trim($r['所屬分公司' . $s] ?? '');
        $amt = $parseNum($r['未稅金額' . $s] ?? '');
        $note = trim($r['備註' . $s] ?? '');
        if ($bn && $amt) {
            $branchId = isset($branchMap[$bn]) ? $branchMap[$bn] : null;
            $branches[] = array('branch_id' => $branchId, 'amount' => $amt, 'note' => $note ?: null);
        }
    }

    if (empty($branches)) continue;

    // 清空再匯入
    $db->prepare("DELETE FROM payable_branches WHERE payable_id = ?")->execute(array($payableId));

    foreach ($branches as $br) {
        try {
            $insertStmt->execute(array($payableId, $br['branch_id'], $br['amount'], $br['note']));
            $itemCount++;
        } catch (Exception $e) {
            $errorCount++;
            echo "❌ {$payableNumber}: " . $e->getMessage() . "\n";
        }
    }
    $matchCount++;
}

echo "\n===== 同步結果 =====\n";
echo "比對成功: {$matchCount} 筆\n";
echo "分公司拆帳匯入: {$itemCount} 筆\n";
echo "無法比對: {$noMatchCount} 筆\n";
echo "錯誤: {$errorCount} 筆\n";
echo '</pre>';
