<?php
/**
 * 診斷 + 修復：「管理處會計請假 - 會計主管簽核」規則簽核人遺失
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('system.manage') && !Auth::hasPermission('all')) {
    die('No permission');
}

$db = Database::getInstance();
header('Content-Type: text/html; charset=utf-8');

echo "<h3>簽核規則診斷：管理處會計請假 - 會計主管簽核</h3>";

// 1. 找到該規則
$ruleStmt = $db->prepare("
    SELECT r.*, u.real_name AS approver_name, u.is_active AS approver_active
    FROM approval_rules r
    LEFT JOIN users u ON r.approver_id = u.id
    WHERE r.rule_name LIKE ? OR r.rule_name LIKE ?
    ORDER BY r.id
");
$ruleStmt->execute(array('%管理處會計請假%', '%會計主管簽核%'));
$rules = $ruleStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($rules)) {
    echo "<p style='color:#c62828'>找不到相關規則</p>";
    exit;
}

echo "<h4>相關規則</h4>";
echo "<table border='1' cellpadding='6' cellspacing='0' style='border-collapse:collapse;font-size:.88rem'>";
echo "<thead><tr><th>id</th><th>module</th><th>規則名稱</th><th>approver_id</th><th>approver 名稱</th><th>approver active</th><th>approver_role</th><th>extra_approver_ids</th><th>啟用</th></tr></thead><tbody>";
foreach ($rules as $r) {
    $color = empty($r['approver_id']) && empty($r['approver_role']) ? '#c62828' : '#000';
    echo "<tr style='color:{$color}'>"
       . "<td>{$r['id']}</td>"
       . "<td>" . htmlspecialchars($r['module']) . "</td>"
       . "<td>" . htmlspecialchars($r['rule_name']) . "</td>"
       . "<td>" . htmlspecialchars((string)$r['approver_id']) . "</td>"
       . "<td>" . htmlspecialchars((string)$r['approver_name']) . "</td>"
       . "<td>" . ($r['approver_id'] ? ($r['approver_active'] ? '✓' : '✗ 停用') : '-') . "</td>"
       . "<td>" . htmlspecialchars((string)$r['approver_role']) . "</td>"
       . "<td>" . htmlspecialchars((string)$r['extra_approver_ids']) . "</td>"
       . "<td>" . ($r['is_active'] ? '啟用' : '停用') . "</td>"
       . "</tr>";
}
echo "</tbody></table>";

// 2. 找游臻梅
echo "<h4>游臻梅 帳號狀態</h4>";
$uStmt = $db->prepare("SELECT id, real_name, username, role, is_active, branch_id FROM users WHERE real_name = ? OR real_name LIKE ?");
$uStmt->execute(array('游臻梅', '%游臻梅%'));
$users = $uStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($users)) {
    echo "<p style='color:#c62828'>⚠️ 找不到游臻梅帳號！</p>";
} else {
    echo "<table border='1' cellpadding='6' cellspacing='0' style='border-collapse:collapse;font-size:.88rem'>";
    echo "<thead><tr><th>id</th><th>real_name</th><th>username</th><th>role</th><th>is_active</th><th>branch_id</th></tr></thead><tbody>";
    foreach ($users as $u) {
        echo "<tr>"
           . "<td>{$u['id']}</td>"
           . "<td>" . htmlspecialchars($u['real_name']) . "</td>"
           . "<td>" . htmlspecialchars($u['username']) . "</td>"
           . "<td>" . htmlspecialchars($u['role']) . "</td>"
           . "<td>" . ($u['is_active'] ? '✓ 啟用' : '✗ 停用') . "</td>"
           . "<td>" . htmlspecialchars((string)$u['branch_id']) . "</td>"
           . "</tr>";
    }
    echo "</tbody></table>";
}

// 3. 查 audit log
echo "<h4>近期修改紀錄（approval_rules）</h4>";
try {
    $logStmt = $db->query("
        SELECT al.created_at, u.real_name, al.action, al.record_id, al.description
        FROM audit_logs al
        LEFT JOIN users u ON al.user_id = u.id
        WHERE al.module = 'approvals' AND al.action LIKE '%rule%'
        ORDER BY al.id DESC
        LIMIT 20
    ");
    $logs = $logStmt->fetchAll(PDO::FETCH_ASSOC);
    if ($logs) {
        echo "<table border='1' cellpadding='6' cellspacing='0' style='border-collapse:collapse;font-size:.85rem'>";
        echo "<thead><tr><th>時間</th><th>操作者</th><th>action</th><th>record_id</th><th>說明</th></tr></thead><tbody>";
        foreach ($logs as $l) {
            echo "<tr>"
               . "<td>" . htmlspecialchars($l['created_at']) . "</td>"
               . "<td>" . htmlspecialchars((string)$l['real_name']) . "</td>"
               . "<td>" . htmlspecialchars($l['action']) . "</td>"
               . "<td>" . htmlspecialchars((string)$l['record_id']) . "</td>"
               . "<td>" . htmlspecialchars((string)$l['description']) . "</td>"
               . "</tr>";
        }
        echo "</tbody></table>";
    } else {
        echo "<p>無紀錄</p>";
    }
} catch (Exception $e) {
    echo "<p>查詢 audit log 失敗：" . htmlspecialchars($e->getMessage()) . "</p>";
}

// 4. 取得張孟歆帳號
$zStmt = $db->prepare("SELECT id, real_name, is_active FROM users WHERE real_name = ? OR real_name LIKE ?");
$zStmt->execute(array('張孟歆', '%張孟歆%'));
$zusers = $zStmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h4>張孟歆 帳號狀態</h4>";
if (empty($zusers)) {
    echo "<p style='color:#c62828'>⚠️ 找不到張孟歆帳號！</p>";
} else {
    foreach ($zusers as $u) {
        echo "<p>id={$u['id']}, " . htmlspecialchars($u['real_name']) . ", " . ($u['is_active'] ? '啟用' : '停用') . "</p>";
    }
}

// 5. 一鍵修復：依 level_order 設定
//   level_order = 1 → 游臻梅
//   level_order = 2 → 張孟歆
$yuId = !empty($users) ? (int)$users[0]['id'] : 0;
$zhangId = !empty($zusers) ? (int)$zusers[0]['id'] : 0;

if (isset($_GET['go']) && $_GET['go'] === 'fix_all' && $yuId > 0 && $zhangId > 0) {
    $updated = 0;
    foreach ($rules as $r) {
        $lv = (int)$r['level_order'];
        $targetId = ($lv === 1) ? $yuId : (($lv === 2) ? $zhangId : 0);
        if ($targetId === 0) continue;
        if ((int)$r['approver_id'] === $targetId) continue;
        $db->prepare("UPDATE approval_rules SET approver_id = ? WHERE id = ?")
           ->execute(array($targetId, $r['id']));
        $targetName = ($lv === 1) ? '游臻梅' : '張孟歆';
        AuditLog::log('approvals', 'rule_fix', $r['id'], "手動回復簽核人(level={$lv})：{$targetName} (id={$targetId})");
        $updated++;
    }
    echo "<hr><p style='color:#2e7d32'>✓ 已修復 {$updated} 條規則</p>";
    echo "<p><a href='?'>重新檢查</a> | <a href='/approvals.php?action=settings'>回簽核設定</a></p>";
} elseif ($yuId > 0 && $zhangId > 0) {
    echo "<hr><h4>一鍵修復</h4>";
    echo "<p>將依 level_order 設定簽核人：</p><ul>";
    echo "<li>順序 1 → 游臻梅 (id={$yuId})</li>";
    echo "<li>順序 2 → 張孟歆 (id={$zhangId})</li>";
    echo "</ul>";

    // 預覽要改的
    $needFix = array();
    foreach ($rules as $r) {
        $lv = (int)$r['level_order'];
        $targetId = ($lv === 1) ? $yuId : (($lv === 2) ? $zhangId : 0);
        if ($targetId === 0) continue;
        if ((int)$r['approver_id'] !== $targetId) {
            $name = ($lv === 1) ? '游臻梅' : '張孟歆';
            $needFix[] = "id={$r['id']} (順序 {$lv}, " . htmlspecialchars($r['rule_name']) . ") → {$name}";
        }
    }
    if (empty($needFix)) {
        echo "<p style='color:#2e7d32'>✓ 所有規則已正確，無需修復。</p>";
    } else {
        echo "<p>將更新：</p><ul>";
        foreach ($needFix as $nf) echo "<li>{$nf}</li>";
        echo "</ul>";
        echo "<p><a href='?go=fix_all' onclick='return confirm(\"確定依 level_order 修復所有簽核規則？\")' "
           . "style='display:inline-block;padding:8px 20px;background:#c62828;color:#fff;text-decoration:none;border-radius:4px'>"
           . "執行修復 " . count($needFix) . " 筆</a></p>";
    }
}
