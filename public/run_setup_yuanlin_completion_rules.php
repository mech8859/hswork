<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('admin')) { die('需要管理員權限'); }
header('Content-Type: text/plain; charset=utf-8');
$db = Database::getInstance();

$execute = isset($_GET['execute']) && $_GET['execute'] == '1';
echo $execute ? "=== 執行模式 ===\n\n" : "=== 預覽模式 === (加 ?execute=1 執行)\n\n";

// 1. 找員林分公司
$bStmt = $db->prepare("SELECT id, name, code FROM branches WHERE code = ? OR name LIKE ? LIMIT 1");
$bStmt->execute(array('YUANLIN', '%員林%'));
$branch = $bStmt->fetch(PDO::FETCH_ASSOC);
if (!$branch) { die("找不到員林分公司\n"); }
$branchId = (int)$branch['id'];
echo "員林分公司：id={$branchId}, name={$branch['name']}, code={$branch['code']}\n\n";

// 2. 找三位簽核人
$names = array(
    1 => array('role' => 'eng_manager', 'name' => '謝旻倫',   'label' => '工程主管'),
    2 => array('role' => 'admin_staff', 'name' => '蕭竹芸',   'label' => '行政人員'),
    3 => array('role' => 'accountant',  'name' => '林晏鈴',   'label' => '會計人員'),
);
$userIds = array();
foreach ($names as $level => $info) {
    $uStmt = $db->prepare("SELECT id, real_name, role FROM users WHERE real_name = ? AND is_active = 1 LIMIT 1");
    $uStmt->execute(array($info['name']));
    $u = $uStmt->fetch(PDO::FETCH_ASSOC);
    if (!$u) {
        echo "[錯誤] 找不到在職使用者：{$info['name']}\n";
        die();
    }
    $userIds[$level] = (int)$u['id'];
    echo "Level {$level} {$info['label']}：id={$u['id']}, name={$u['real_name']}, role={$u['role']}\n";
}
echo "\n";

// 3. 建立三條規則
$ruleDefs = array(
    1 => '完工簽核 - 員林分公司 Level 1 工程主管',
    2 => '完工簽核 - 員林分公司 Level 2 行政人員（勾選有無收款）',
    3 => '完工簽核 - 員林分公司 Level 3 會計人員（確認入帳）',
);

foreach ($ruleDefs as $level => $ruleName) {
    $chk = $db->prepare("
        SELECT id, rule_name, approver_id FROM approval_rules
        WHERE module='case_completion' AND level_order=?
          AND condition_branch_ids = ?
          AND is_active=1
        LIMIT 1
    ");
    $chk->execute(array($level, (string)$branchId));
    $exists = $chk->fetch(PDO::FETCH_ASSOC);

    $approverId = $userIds[$level];
    $approverRole = $names[$level]['role'];

    if ($exists) {
        echo "[已存在] Level {$level} 規則 id={$exists['id']}（approver_id={$exists['approver_id']}）";
        if ((int)$exists['approver_id'] !== $approverId) {
            echo " → 將更新 approver_id 為 {$approverId}\n";
            if ($execute) {
                $db->prepare("UPDATE approval_rules SET rule_name=?, approver_id=?, approver_role=? WHERE id=?")
                   ->execute(array($ruleName, $approverId, $approverRole, $exists['id']));
                echo "  → 完成\n";
            }
        } else {
            echo "（簽核人一致，略過）\n";
        }
    } else {
        echo "[新增] Level {$level} 規則：{$ruleName} → approver_id={$approverId}\n";
        if ($execute) {
            $db->prepare("
                INSERT INTO approval_rules
                  (module, rule_name, min_amount, max_amount, min_profit_rate,
                   condition_type, case_types, condition_branch_ids,
                   approver_role, approver_id, level_order, is_active)
                VALUES
                  ('case_completion', ?, 0, NULL, NULL,
                   'amount', NULL, ?,
                   ?, ?, ?, 1)
            ")->execute(array($ruleName, (string)$branchId, $approverRole, $approverId, $level));
            echo "  → 完成，新 id=" . $db->lastInsertId() . "\n";
        }
    }
}

echo "\n完成。";
echo $execute ? "\n\n→ 請到 /approvals.php?action=settings 確認\n" : "\n\n(預覽模式，無變更。加 ?execute=1 實際執行)\n";
