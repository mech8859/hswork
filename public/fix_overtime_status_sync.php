<?php
/**
 * 回填：已簽核完成但 overtimes.status 還停在 pending 的加班單
 *
 * 原因：approvals.php 的 overtime 完成分支漏寫 UPDATE overtimes SET status='approved'
 *       導致所有已簽核的加班單仍顯示「待核准」
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('system.manage') && !Auth::hasPermission('all')) {
    die('No permission');
}

$db = Database::getInstance();
header('Content-Type: text/html; charset=utf-8');

$dryRun = !isset($_GET['go']) || $_GET['go'] !== '1';

echo "<h3>回填加班單簽核狀態</h3>";
echo "<p>模式：" . ($dryRun ? '<b style="color:#c62828">Dry-run</b>' : '<b style="color:#2e7d32">實際執行</b>') . "</p>";

// 取狀態為 pending 的加班單，檢查 approval_flows 是否已經全部 approved
$stmt = $db->query("
    SELECT o.id, o.user_id, o.overtime_date, o.status,
           u.real_name,
           (SELECT COUNT(*) FROM approval_flows af WHERE af.module='overtime' AND af.target_id=o.id) AS total_flows,
           (SELECT COUNT(*) FROM approval_flows af WHERE af.module='overtime' AND af.target_id=o.id AND af.status='approved') AS approved_flows,
           (SELECT COUNT(*) FROM approval_flows af WHERE af.module='overtime' AND af.target_id=o.id AND af.status='pending') AS pending_flows,
           (SELECT COUNT(*) FROM approval_flows af WHERE af.module='overtime' AND af.target_id=o.id AND af.status='rejected') AS rejected_flows,
           (SELECT MAX(decided_at) FROM approval_flows af WHERE af.module='overtime' AND af.target_id=o.id AND af.status='approved') AS last_approved_at,
           (SELECT approver_id FROM approval_flows af WHERE af.module='overtime' AND af.target_id=o.id AND af.status='approved' ORDER BY decided_at DESC LIMIT 1) AS last_approver_id
    FROM overtimes o
    LEFT JOIN users u ON o.user_id = u.id
    WHERE o.status = 'pending'
    ORDER BY o.id DESC
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 針對每筆判斷：是否 advanceSequentialApproval 已完成（後端沒有 pending，且有 approved）
$toApprove = array();
$toReject = array();
foreach ($rows as $r) {
    if ((int)$r['total_flows'] === 0) continue; // 沒流程的跳過
    if ((int)$r['rejected_flows'] > 0) {
        $toReject[] = $r;
        continue;
    }
    // 最後一關簽完的規則：pending_flows=0 且 approved_flows >= 1
    // 但分關模式下完成指的是最後 level 簽完，無下一關可建
    // 保守判斷：pending=0 且 approved>=1 且該 target 沒有再被建 flow（advanceSequentialApproval 已回 completed）
    if ((int)$r['pending_flows'] === 0 && (int)$r['approved_flows'] >= 1) {
        $toApprove[] = $r;
    }
}

echo "<h4>待回填為 approved：" . count($toApprove) . " 筆</h4>";
if (!empty($toApprove)) {
    echo "<table border='1' cellpadding='6' cellspacing='0' style='border-collapse:collapse;font-size:.85rem'>";
    echo "<thead><tr><th>id</th><th>申請人</th><th>日期</th><th>flow 統計</th><th>最後簽核時間</th><th>最後簽核人 id</th></tr></thead><tbody>";
    foreach ($toApprove as $r) {
        echo "<tr>"
           . "<td>{$r['id']}</td>"
           . "<td>" . htmlspecialchars((string)$r['real_name']) . "</td>"
           . "<td>" . htmlspecialchars((string)$r['overtime_date']) . "</td>"
           . "<td>total={$r['total_flows']}, approved={$r['approved_flows']}, pending={$r['pending_flows']}</td>"
           . "<td>" . htmlspecialchars((string)$r['last_approved_at']) . "</td>"
           . "<td>{$r['last_approver_id']}</td>"
           . "</tr>";
    }
    echo "</tbody></table>";
}

echo "<h4>待回填為 rejected：" . count($toReject) . " 筆</h4>";
if (!empty($toReject)) {
    echo "<p>（有 rejected flow 的）</p>";
    echo "<ul>";
    foreach ($toReject as $r) {
        echo "<li>id={$r['id']} ({$r['real_name']}, {$r['overtime_date']})</li>";
    }
    echo "</ul>";
}

if ($dryRun && (!empty($toApprove) || !empty($toReject))) {
    echo "<p><a href='?go=1' onclick='return confirm(\"確定執行回填？\")' "
       . "style='display:inline-block;padding:8px 20px;background:#c62828;color:#fff;text-decoration:none;border-radius:4px'>"
       . "執行回填</a></p>";
    exit;
}

if (!$dryRun) {
    $upA = $db->prepare("UPDATE overtimes SET status = 'approved', approved_by = ?, approved_at = ? WHERE id = ? AND status = 'pending'");
    $upR = $db->prepare("UPDATE overtimes SET status = 'rejected', approved_by = ?, approved_at = ? WHERE id = ? AND status = 'pending'");
    $aDone = 0;
    $rDone = 0;
    foreach ($toApprove as $r) {
        $upA->execute(array($r['last_approver_id'], $r['last_approved_at'] ?: date('Y-m-d H:i:s'), $r['id']));
        $aDone += $upA->rowCount();
    }
    foreach ($toReject as $r) {
        $upR->execute(array(null, date('Y-m-d H:i:s'), $r['id']));
        $rDone += $upR->rowCount();
    }
    echo "<hr><p style='color:#2e7d32'>✓ approved: {$aDone} 筆, rejected: {$rDone} 筆</p>";
    echo "<p><a href='/overtimes.php'>回加班單管理</a></p>";
}
