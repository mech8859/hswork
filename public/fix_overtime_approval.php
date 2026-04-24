<?php
/**
 * 診斷 + 修復：加班單沒跑簽核流程
 *
 * 情境：使用者新增加班單，但 approval_flows 沒建立記錄（狀態仍是待核准卻沒有人被通知簽核）
 *
 * 檢查項目：
 *  1. 列出最近「待核准」的加班單
 *  2. 檢查每筆是否有 approval_flows 紀錄
 *  3. 檢查加班規則的 branch / role / amount 條件是否造成不匹配
 *  4. 可一鍵針對缺失者重跑 submitForApproval
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('system.manage') && !Auth::hasPermission('all')) {
    die('No permission');
}

require_once __DIR__ . '/../modules/approvals/ApprovalModel.php';

$db = Database::getInstance();
header('Content-Type: text/html; charset=utf-8');

echo "<h3>加班單簽核流程診斷</h3>";

// 1. 抓最近待核准加班單（含無 approval_flows 的）
$stmt = $db->query("
    SELECT o.id, o.user_id, o.overtime_date, o.hours, o.status, o.reason, o.created_at,
           u.real_name, u.branch_id, u.role, b.name AS branch_name,
           (SELECT COUNT(*) FROM approval_flows af WHERE af.module='overtime' AND af.target_id=o.id) AS flow_cnt,
           (SELECT COUNT(*) FROM approval_flows af WHERE af.module='overtime' AND af.target_id=o.id AND af.status='pending') AS pending_cnt
    FROM overtimes o
    LEFT JOIN users u ON o.user_id = u.id
    LEFT JOIN branches b ON u.branch_id = b.id
    WHERE o.status IN ('pending', '待核准', 'waiting')
    ORDER BY o.id DESC
    LIMIT 30
");
$overtimes = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h4>近期待核准加班單</h4>";
echo "<table border='1' cellpadding='6' cellspacing='0' style='border-collapse:collapse;font-size:.85rem'>";
echo "<thead><tr><th>id</th><th>申請人</th><th>分公司</th><th>role</th><th>日期</th><th>時數</th><th>事由</th><th>flow_cnt</th><th>pending</th><th>狀態</th></tr></thead><tbody>";
$missingIds = array();
foreach ($overtimes as $o) {
    $missing = (int)$o['flow_cnt'] === 0;
    if ($missing) $missingIds[] = (int)$o['id'];
    $color = $missing ? '#c62828' : '#000';
    echo "<tr style='color:{$color}'>"
       . "<td>{$o['id']}</td>"
       . "<td>" . htmlspecialchars((string)$o['real_name']) . "</td>"
       . "<td>" . htmlspecialchars((string)$o['branch_name']) . " (id={$o['branch_id']})</td>"
       . "<td>" . htmlspecialchars((string)$o['role']) . "</td>"
       . "<td>" . htmlspecialchars((string)$o['overtime_date']) . "</td>"
       . "<td>" . htmlspecialchars((string)$o['hours']) . "</td>"
       . "<td>" . htmlspecialchars(mb_substr((string)$o['reason'], 0, 30)) . "</td>"
       . "<td>{$o['flow_cnt']}</td>"
       . "<td>{$o['pending_cnt']}</td>"
       . "<td>" . htmlspecialchars($o['status']) . "</td>"
       . "</tr>";
}
echo "</tbody></table>";

// 2. 加班規則
echo "<h4>加班 (overtime) 規則</h4>";
$rStmt = $db->query("
    SELECT r.*, u.real_name AS approver_name
    FROM approval_rules r
    LEFT JOIN users u ON r.approver_id = u.id
    WHERE r.module = 'overtime'
    ORDER BY r.level_order, r.id
");
$rules = $rStmt->fetchAll(PDO::FETCH_ASSOC);
echo "<table border='1' cellpadding='6' cellspacing='0' style='border-collapse:collapse;font-size:.85rem'>";
echo "<thead><tr><th>id</th><th>規則名稱</th><th>layer</th><th>approver</th><th>min_amount</th><th>max_amount</th><th>分公司條件</th><th>角色條件</th><th>啟用</th></tr></thead><tbody>";
foreach ($rules as $r) {
    echo "<tr>"
       . "<td>{$r['id']}</td>"
       . "<td>" . htmlspecialchars($r['rule_name']) . "</td>"
       . "<td>{$r['level_order']}</td>"
       . "<td>" . htmlspecialchars((string)$r['approver_name']) . " (id=" . (string)$r['approver_id'] . ")</td>"
       . "<td>" . htmlspecialchars((string)$r['min_amount']) . "</td>"
       . "<td>" . htmlspecialchars((string)$r['max_amount']) . "</td>"
       . "<td>" . htmlspecialchars((string)$r['condition_branch_ids']) . "</td>"
       . "<td>" . htmlspecialchars((string)$r['condition_user_roles']) . "</td>"
       . "<td>" . ($r['is_active'] ? '✓' : '✗') . "</td>"
       . "</tr>";
}
echo "</tbody></table>";

// 3. 對於每筆缺 flow 的加班單，跑 needsApproval 模擬，顯示匹配結果
if (!empty($missingIds)) {
    echo "<h4>診斷：為何缺流程？</h4>";
    $am = new ApprovalModel();
    foreach ($overtimes as $o) {
        if ((int)$o['flow_cnt'] !== 0) continue;
        echo "<div style='margin:6px 0;padding:8px;border-left:3px solid #c62828;background:#fff3e0'>";
        echo "<b>加班單 id={$o['id']} ({$o['real_name']}, branch_id={$o['branch_id']}, role={$o['role']})</b><br>";
        $matched = $am->needsApproval('overtime', 0, null, array(), (int)$o['user_id']);
        if ($matched === false) {
            echo "<span style='color:#c62828'>→ needsApproval 回傳 false（規則未匹配或全是 auto_approve）</span>";
        } else {
            echo "→ 匹配到 " . count($matched) . " 條規則<br>";
            foreach ($matched as $m) {
                echo "&nbsp;&nbsp;• 規則 id={$m['id']}, level={$m['level_order']}, approver_id={$m['approver_id']}, role={$m['approver_role']}<br>";
            }
        }
        echo "</div>";
    }
}

// 4. 一鍵重跑
if (!empty($missingIds)) {
    if (isset($_GET['go']) && $_GET['go'] === 'resend') {
        echo "<hr><h4>執行重送</h4>";
        $am = new ApprovalModel();
        $done = 0;
        foreach ($overtimes as $o) {
            if ((int)$o['flow_cnt'] !== 0) continue;
            try {
                $result = $am->submitForApproval('overtime', (int)$o['id'], 0, null, (int)$o['user_id']);
                AuditLog::log('overtimes', 'resubmit_approval', (int)$o['id'], '手動重送簽核');
                $cnt = is_array($result) && !isset($result['auto_approved']) ? count($result) : 0;
                echo "<p>id={$o['id']} ({$o['real_name']}): 建立了 {$cnt} 筆 flow"
                   . (isset($result['auto_approved']) ? "（auto_approved）" : "")
                   . "</p>";
                $done++;
            } catch (Exception $e) {
                echo "<p style='color:#c62828'>id={$o['id']} 失敗：" . htmlspecialchars($e->getMessage()) . "</p>";
            }
        }
        echo "<p style='color:#2e7d32'>✓ 完成 {$done} 筆</p>";
        echo "<p><a href='?'>重新檢查</a> | <a href='/overtimes.php'>回加班單</a></p>";
    } else {
        echo "<hr><p><a href='?go=resend' onclick='return confirm(\"確定重送 " . count($missingIds) . " 筆加班單的簽核流程？\")' "
           . "style='display:inline-block;padding:8px 20px;background:#c62828;color:#fff;text-decoration:none;border-radius:4px'>"
           . "重送 " . count($missingIds) . " 筆加班單簽核流程</a></p>";
    }
}
