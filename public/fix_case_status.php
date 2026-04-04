<?php
/**
 * 修復案件 status 欄位 — 根據 Ragic 原始資料對應
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

$db = Database::getInstance();

echo "<h2>修復案件 status 欄位（依 Ragic 原始資料）</h2>";

// Step 1: 目前 status 分布
echo "<h3>Step 1: 修正前 status 分布</h3>";
$stmt = $db->query("SELECT status, COUNT(*) as cnt FROM cases GROUP BY status ORDER BY cnt DESC");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<table border='1' cellpadding='5'><tr><th>status</th><th>筆數</th></tr>";
foreach ($rows as $r) {
    echo "<tr><td>" . htmlspecialchars($r['status'] ?: '(NULL)') . "</td><td>" . $r['cnt'] . "</td></tr>";
}
echo "</table>";

// Step 2: Ragic 原始資料 → 進件編號 對應 status
echo "<h3>Step 2: 依 Ragic 原始「案件進度」修正</h3>";

$ragicMap = array(
    '2026-1395' => 'incomplete',
    '2026-1353' => 'incomplete',
    '2026-1347' => 'tracking',
    '2026-1343' => 'unpaid',
    '2026-1336' => 'incomplete',
    '2026-1304' => 'incomplete',
    '2026-1262' => 'completed',
    '2026-1158' => 'unpaid',
    '2026-1154' => 'incomplete',
    '2026-1149' => 'completed',
    '2026-1145' => 'customer_cancel',
    '2026-1143' => 'incomplete',
    '2026-1096' => 'incomplete',
    '2026-1092' => 'incomplete',
    '2026-1091' => 'unpaid',
    '2026-1090' => 'incomplete',
    '2026-1087' => 'completed',
    '2026-1040' => 'completed',
    '2026-1034' => 'incomplete',
    '2026-1031' => 'unpaid',
    '2026-1025' => 'incomplete',
    '2026-1008' => 'tracking',
    '2026-0905' => 'incomplete',
    '2026-0871' => 'completed',
    '2026-0845' => 'customer_cancel',
    '2026-0843' => 'unpaid',
    '2026-0817' => 'tracking',
    '2026-0811' => 'incomplete',
    '2026-0730' => 'unpaid',
    '2026-0725' => 'unpaid',
    '202601-2-088' => 'completed',
    '2026-0676' => 'tracking',
    '202601-109' => 'completed',
    '202601-117' => 'customer_cancel',
    '2026-0011' => 'completed',
    '2026-0084' => 'tracking',
    '2026-0089' => 'incomplete',
    '202512-281' => 'incomplete',
    '202512-282' => 'incomplete',
    '202512-283' => 'incomplete',
    '202512-284' => 'breach',
    '202512-285' => 'incomplete',
    '202512-286' => 'incomplete',
    '202512-288' => 'incomplete',
    '202512-289' => 'incomplete',
    '202512-291' => 'incomplete',
    '202512-292' => 'incomplete',
    '202512-293' => 'incomplete',
    '202512-294' => 'incomplete',
    '202512-295' => 'completed',
    '202512-296' => 'incomplete',
    '2026-0643' => 'incomplete',
    '2026-0642' => 'completed',
    '2026-0641' => 'incomplete',
    '2026-0640' => 'incomplete',
    '2026-0639' => 'unpaid',
    '2026-0638' => 'unpaid',
    '2026-0637' => 'unpaid',
    '2026-0636' => 'incomplete',
    '2026-0635' => 'completed',
    '2026-0634' => 'incomplete',
    '2026-0633' => 'incomplete',
    '2026-0632' => 'incomplete',
    '2026-0631' => 'incomplete',
    '2026-0630' => 'incomplete',
    '2026-0629' => 'incomplete',
    '2026-0628' => 'incomplete',
    '2026-0627' => 'incomplete',
    '2026-0626' => 'incomplete',
    '2026-0625' => 'incomplete',
    '2026-0624' => 'incomplete',
    '2026-0623' => 'incomplete',
    '202512-5-4' => 'incomplete',
    '2026-0622' => 'incomplete',
    '2026-0621' => 'completed',
    '2026-0620' => 'completed',
    '2026-0618' => 'completed',
    '2026-0617' => 'completed',
    '2026-0616' => 'completed',
    '2026-0615' => 'completed',
    '2026-0614' => 'completed',
    '2026-0613' => 'completed',
    '2026-0612' => 'incomplete',
    '2026-0611' => 'completed',
    '2026-0610' => 'completed',
    '2026-0609' => 'unpaid',
    '2026-0608' => 'unpaid',
    '2026-0607' => 'completed',
    '2026-0606' => 'completed',
    '2026-0605' => 'completed',
    '2026-0604' => 'completed',
    '2026-0603' => 'completed',
    '202512-5-2' => 'unpaid',
    '202512-5-1' => 'completed',
    '2026-0602' => 'completed',
    '2026-0601' => 'unpaid',
    '2026-0123' => 'lost',
    '2026-0155' => 'incomplete',
    '2026-0176' => 'incomplete',
    '2026-0177' => 'incomplete',
    '2026-0209' => 'unpaid',
    '2026-0222' => 'tracking',
    '2026-0257' => 'incomplete',
    '2026-0262' => 'tracking',
    '2026-0305' => 'unpaid',
    '2026-0598' => 'completed',
    '2026-0597' => 'completed',
    '2026-0596' => 'completed',
    '2026-0595' => 'unpaid',
    '202601-2-002' => 'completed',
    '202601-2-001' => 'completed',
    '2026-0381' => 'completed',
    '2026-0454' => 'completed',
    '2026-0481' => 'incomplete',
);

require_once __DIR__ . '/../modules/cases/CaseModel.php';
$progressLabels = CaseModel::progressOptions();

$updated = 0;
$notFound = 0;
$same = 0;
echo "<table border='1' cellpadding='5'><tr><th>進件編號</th><th>原 status</th><th>→ 新 status</th><th>結果</th></tr>";

foreach ($ragicMap as $caseNumber => $newStatus) {
    $stmt = $db->prepare("SELECT id, status FROM cases WHERE case_number = ?");
    $stmt->execute(array($caseNumber));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $notFound++;
        echo "<tr style='color:gray'><td>" . htmlspecialchars($caseNumber) . "</td><td>-</td><td>" . htmlspecialchars($newStatus) . "</td><td>找不到</td></tr>";
        continue;
    }

    if ($row['status'] === $newStatus) {
        $same++;
        continue;
    }

    $label = isset($progressLabels[$newStatus]) ? $progressLabels[$newStatus] : $newStatus;
    $oldLabel = isset($progressLabels[$row['status']]) ? $progressLabels[$row['status']] : $row['status'];

    $db->prepare("UPDATE cases SET status = ? WHERE id = ?")->execute(array($newStatus, $row['id']));
    $updated++;
    echo "<tr style='color:green'><td>" . htmlspecialchars($caseNumber) . "</td><td>" . htmlspecialchars($oldLabel) . "</td><td>" . htmlspecialchars($label) . "</td><td>✓ 已修正</td></tr>";
}
echo "</table>";
echo "<p>已修正: <strong>{$updated}</strong> 筆 | 原本就正確: {$same} 筆 | 找不到: {$notFound} 筆</p>";

// Step 3: 修正後分布
echo "<h3>Step 3: 修正後 status 分布</h3>";
$stmt = $db->query("SELECT status, COUNT(*) as cnt FROM cases GROUP BY status ORDER BY cnt DESC");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<table border='1' cellpadding='5'><tr><th>status</th><th>顯示名稱</th><th>筆數</th></tr>";
foreach ($rows as $r) {
    $label = isset($progressLabels[$r['status']]) ? $progressLabels[$r['status']] : $r['status'];
    echo "<tr><td>" . htmlspecialchars($r['status']) . "</td><td>" . htmlspecialchars($label) . "</td><td>" . $r['cnt'] . "</td></tr>";
}
echo "</table>";

echo "<hr><p><a href='/cases.php'>返回案件管理</a> | <a href='/business_tracking.php'>業務追蹤表</a></p>";
