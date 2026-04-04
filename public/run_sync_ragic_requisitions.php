<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!in_array(Session::getUser()['role'], array('boss', 'vice_president'))) die('需要管理員權限');

set_time_limit(300);
header('Content-Type: text/html; charset=utf-8');
echo '<h2>Ragic 請購單同步</h2><pre>';

$db = Database::getInstance();

$jsonFile = __DIR__ . '/../database/ragic_requisitions_20260405.json';
if (!file_exists($jsonFile)) die('找不到 JSON');
$ragicData = json_decode(file_get_contents($jsonFile), true);
echo "Ragic: " . count($ragicData) . " 筆\n";

$branchMap = array();
$bStmt = $db->query('SELECT id, name FROM branches');
while ($b = $bStmt->fetch(PDO::FETCH_ASSOC)) $branchMap[$b['name']] = $b['id'];

$userMap = array();
$uStmt = $db->query('SELECT id, real_name FROM users WHERE is_active = 1');
while ($u = $uStmt->fetch(PDO::FETCH_ASSOC)) $userMap[$u['real_name']] = $u['id'];

$productMap = array();
$pStmt = $db->query('SELECT id, model, source_id FROM products');
while ($p = $pStmt->fetch(PDO::FETCH_ASSOC)) {
    if ($p['model']) $productMap[strtoupper(trim($p['model']))] = (int)$p['id'];
    if ($p['source_id']) $productMap[strtoupper(trim($p['source_id']))] = (int)$p['id'];
}

$existCount = (int)$db->query("SELECT COUNT(*) FROM requisitions")->fetchColumn();
echo "系統現有: {$existCount} 筆\n";

$parseDate = function($v) {
    if (!$v || !trim($v)) return null;
    $v = str_replace('/', '-', trim($v));
    return preg_match('/^\d{4}-\d{2}-\d{2}/', $v) ? substr($v, 0, 10) : null;
};
$parseNum = function($v) {
    if (!$v || !trim($v)) return 0;
    $v = str_replace(',', '', trim($v));
    return is_numeric($v) ? round((float)$v, 2) : 0;
};

echo "\n清空...\n";
$db->exec("SET FOREIGN_KEY_CHECKS = 0");
$db->exec("DELETE FROM requisition_items");
$db->exec("DELETE FROM requisitions");
$db->exec("SET FOREIGN_KEY_CHECKS = 1");
echo "✓ 已清空\n\n";

$insertR = $db->prepare("
    INSERT INTO requisitions
        (requisition_number, requisition_date, requester_name, branch_id,
         sales_name, urgency, case_name, vendor_name, expected_date,
         status, created_by, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
");

$insertItem = $db->prepare("
    INSERT INTO requisition_items
        (requisition_id, product_id, model, product_name, quantity, approved_qty, purpose, sort_order)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");

$insertCount = 0;
$itemCount = 0;
$errorCount = 0;

foreach ($ragicData as $ragicId => $r) {
    $reqNumber = trim($r['請購單號'] ?? '');
    if (!$reqNumber) { $reqNumber = 'PR-RAGIC-' . $ragicId; }

    $branchName = trim($r['所屬分公司'] ?? '');
    $branchId = isset($branchMap[$branchName]) ? $branchMap[$branchName] : null;

    $requesterName = trim($r['請購人員'] ?? '');
    $createdBy = isset($userMap[$requesterName]) ? $userMap[$requesterName] : Auth::id();

    $salesName = trim($r['負責業務'] ?? '');
    $urgency = trim($r['緊急程度'] ?? '一般件');
    $caseName = trim($r['案名'] ?? '') ?: null;
    $vendorName = trim($r['廠商'] ?? '') ?: null;
    $expectedDate = $parseDate($r['期望到貨日'] ?? '');
    $reqDate = $parseDate($r['請購日期'] ?? '') ?: date('Y-m-d');

    $approvalStatus = trim($r['簽核狀態'] ?? '');
    $statusMap = array('簽核完成' => '已核准', '退回' => '已退回');
    $status = isset($statusMap[$approvalStatus]) ? $statusMap[$approvalStatus] : '待簽核';

    try {
        $insertR->execute(array(
            $reqNumber, $reqDate, $requesterName ?: null, $branchId,
            $salesName ?: null, $urgency, $caseName, $vendorName, $expectedDate,
            $status, $createdBy,
        ));
        $rId = (int)$db->lastInsertId();
        $insertCount++;

        $sub = $r['_subtable_1008794'] ?? array();
        foreach ($sub as $sItem) {
            $model = trim($sItem['商品型號'] ?? '');
            $productName = trim($sItem['商品名稱'] ?? '');

            $productId = null;
            if ($model && isset($productMap[strtoupper($model)])) $productId = $productMap[strtoupper($model)];

            $qty = $parseNum($sItem['請購數量'] ?? '');
            $approvedQty = $parseNum($sItem['覆核數量'] ?? '');
            $purpose = trim($sItem['用途說明'] ?? '') ?: null;
            $sortOrder = (int)($sItem['項次'] ?? 0);

            $insertItem->execute(array(
                $rId, $productId, $model ?: null, $productName ?: null,
                $qty, $approvedQty ?: null, $purpose, $sortOrder,
            ));
            $itemCount++;
        }
        echo "匯入 {$reqNumber}\n";
    } catch (Exception $e) {
        $errorCount++;
        echo "❌ {$reqNumber}: " . $e->getMessage() . "\n";
    }
}

echo "\n===== 同步結果 =====\n";
echo "請購單匯入: {$insertCount} 筆\n";
echo "明細匯入: {$itemCount} 筆\n";
echo "錯誤: {$errorCount} 筆\n";
echo '</pre>';
