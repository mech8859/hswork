<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('admin')) { die('需要管理員權限'); }
header('Content-Type: text/plain; charset=utf-8');
$db = Database::getInstance();

$execute = isset($_GET['execute']) && $_GET['execute'] == '1';
echo $execute ? "=== 執行模式 ===\n\n" : "=== 預覽模式 === (加 ?execute=1 執行)\n\n";

// 1. approval_rules 加 max_profit_rate
$col = $db->query("SHOW COLUMNS FROM approval_rules LIKE 'max_profit_rate'")->fetch();
if ($col) {
    echo "[已存在] approval_rules.max_profit_rate\n";
} else {
    echo "[新增] approval_rules.max_profit_rate DECIMAL(6,2) DEFAULT NULL\n";
    if ($execute) {
        $db->exec("ALTER TABLE approval_rules ADD COLUMN max_profit_rate DECIMAL(6,2) DEFAULT NULL COMMENT '最高利潤率（NULL=無上限）' AFTER min_profit_rate");
        echo "  → 完成\n";
    }
}

// 2. quotations 加 loss_reason
$col = $db->query("SHOW COLUMNS FROM quotations LIKE 'loss_reason'")->fetch();
if ($col) {
    echo "[已存在] quotations.loss_reason\n";
} else {
    echo "[新增] quotations.loss_reason VARCHAR(500) DEFAULT NULL\n";
    if ($execute) {
        $db->exec("ALTER TABLE quotations ADD COLUMN loss_reason VARCHAR(500) DEFAULT NULL COMMENT '虧損報價送簽原因'");
        echo "  → 完成\n";
    }
}

// 3. 新增虧損簽核規則（預設 boss 簽）
$bossRow = $db->query("SELECT id, real_name FROM users WHERE role = 'boss' AND is_active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$bossRow) {
    echo "[警告] 找不到 boss 使用者，規則 approver_id 會留空、僅指定 approver_role='boss'\n";
    $approverId = null;
    $approverName = '(role=boss)';
} else {
    $approverId = (int)$bossRow['id'];
    $approverName = $bossRow['real_name'];
}

$existRule = $db->prepare("SELECT id FROM approval_rules WHERE module='quotations' AND rule_name LIKE '%虧損%' LIMIT 1");
$existRule->execute();
$existRuleRow = $existRule->fetch(PDO::FETCH_ASSOC);
if ($existRuleRow) {
    echo "[已存在] 虧損審核規則 id={$existRuleRow['id']}\n";
} else {
    echo "[新增] 報價單 - 虧損審核 規則 (max_profit_rate=0, approver={$approverName})\n";
    if ($execute) {
        $db->prepare("
            INSERT INTO approval_rules
              (module, rule_name, min_amount, max_amount, min_profit_rate, max_profit_rate,
               condition_type, approver_role, approver_id, level_order, is_active)
            VALUES
              ('quotations', '報價單 - 虧損審核（profit_rate<0）', 0, NULL, NULL, 0,
               'amount', 'boss', ?, 1, 1)
        ")->execute(array($approverId));
        echo "  → 完成，新 id=" . $db->lastInsertId() . "\n";
    }
}

echo "\n完成。";
echo $execute ? "\n\n→ 請到 /approvals.php?action=settings 確認\n" : "\n\n(預覽模式，無變更。加 ?execute=1 實際執行)\n";
