<?php
/**
 * 一次性腳本：為「東區電子鎖」分公司建立完工簽核三層規則
 *   Level 1 工程主管 - 蕭凱澤
 *   Level 2 行政人員（勾選有無收款）- 張䕒尹
 *   Level 3 會計人員（確認入帳）- 林晏鈴
 *
 * 用法：/run_setup_dongqu_lock_approval.php           （預覽）
 *       /run_setup_dongqu_lock_approval.php?execute=1 （實際執行）
 */
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('admin') && Auth::user()['role'] !== 'boss') {
    die('需要管理員權限');
}
header('Content-Type: text/plain; charset=utf-8');
$db = Database::getInstance();
$execute = isset($_GET['execute']) && $_GET['execute'] == '1';

echo $execute ? "=== 執行模式 ===\n\n" : "=== 預覽模式 === (加 ?execute=1 執行)\n\n";

// 1. 找分公司
$bStmt = $db->prepare("SELECT id, name FROM branches WHERE name = ? LIMIT 1");
$bStmt->execute(array('東區電子鎖'));
$branch = $bStmt->fetch(PDO::FETCH_ASSOC);
if (!$branch) {
    // 兼容：LIKE 模糊比對（避免名稱前後空白或括號）
    $bStmt2 = $db->prepare("SELECT id, name FROM branches WHERE name LIKE ? LIMIT 1");
    $bStmt2->execute(array('%東區電子鎖%'));
    $branch = $bStmt2->fetch(PDO::FETCH_ASSOC);
}
if (!$branch) { die("[錯誤] 找不到分公司「東區電子鎖」\n"); }
$branchId = (int)$branch['id'];
echo "分公司：{$branch['name']} (id={$branchId})\n\n";

// 2. 找簽核人
function findUser($db, $name) {
    $s = $db->prepare("SELECT id, real_name, role FROM users WHERE real_name = ? AND is_active = 1 LIMIT 1");
    $s->execute(array($name));
    return $s->fetch(PDO::FETCH_ASSOC);
}
$u1 = findUser($db, '蕭凱澤');
$u2 = findUser($db, '張䕒尹');
$u3 = findUser($db, '林晏鈴');

foreach (array('蕭凱澤' => $u1, '張䕒尹' => $u2, '林晏鈴' => $u3) as $n => $u) {
    if (!$u) die("[錯誤] 找不到使用者「$n」（is_active=1）\n");
    echo "✓ {$n}: id={$u['id']}, role={$u['role']}\n";
}
echo "\n";

// 3. 三條規則
$rules = array(
    array(
        'level' => 1,
        'name'  => '完工簽核 - 工程主管（東區電子鎖）',
        'role'  => 'eng_manager',
        'user'  => $u1,
    ),
    array(
        'level' => 2,
        'name'  => '完工簽核 - Level 2 行政人員（東區電子鎖，勾選有無收款）',
        'role'  => 'admin_staff',
        'user'  => $u2,
    ),
    array(
        'level' => 3,
        'name'  => '完工簽核 - Level 3 會計人員（東區電子鎖，確認入帳）',
        'role'  => 'accountant',
        'user'  => $u3,
    ),
);

foreach ($rules as $r) {
    // 是否已存在同 level + 同 branch 規則
    $chk = $db->prepare("
        SELECT id, rule_name FROM approval_rules
        WHERE module='case_completion' AND level_order=? AND is_active=1 AND condition_branch_ids = ?
        LIMIT 1
    ");
    $chk->execute(array($r['level'], (string)$branchId));
    $exist = $chk->fetch(PDO::FETCH_ASSOC);
    if ($exist) {
        echo "[已存在] Level {$r['level']}：{$exist['rule_name']} (id={$exist['id']})，略過\n";
        continue;
    }

    echo "[新增] Level {$r['level']}：{$r['name']}\n";
    echo "       role={$r['role']}, approver={$r['user']['real_name']}(id={$r['user']['id']}), branch_id={$branchId}\n";
    if ($execute) {
        $stmt = $db->prepare("
            INSERT INTO approval_rules
              (module, rule_name, min_amount, max_amount, min_profit_rate, max_profit_rate,
               condition_type, approver_role, approver_id, level_order, is_active,
               condition_branch_ids)
            VALUES
              ('case_completion', ?, 0, NULL, NULL, NULL,
               'amount', ?, ?, ?, 1, ?)
        ");
        $stmt->execute(array(
            $r['name'], $r['role'], (int)$r['user']['id'], (int)$r['level'], (string)$branchId,
        ));
        echo "       → 完成，新 id=" . $db->lastInsertId() . "\n";
    }
}

echo "\n完成。";
echo $execute
    ? "\n\n→ 請到 /approvals.php?action=settings 確認\n"
    : "\n\n(預覽模式，無變更。加 ?execute=1 實際執行)\n";
