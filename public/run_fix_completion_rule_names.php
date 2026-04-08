<?php
/**
 * 修正完工簽核規則名稱（一次性腳本）
 *
 * 把舊的 Level 1/2 規則名稱統一：
 *   Level 1 → 完工簽核 - Level 1 工程主管
 *   Level 2 → 完工簽核 - Level 2 行政人員（勾選有無收款）
 *   Level 3 → 完工簽核 - Level 3 會計人員（確認入帳）
 *
 * 不會動到 approver_id / extra_approver_ids，所以你已經設好的簽核人不會被清掉
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();

echo "=== 修正完工簽核規則名稱 ===\n\n";

$rules = array(
    1 => '完工簽核 - Level 1 工程主管',
    2 => '完工簽核 - Level 2 行政人員（勾選有無收款）',
    3 => '完工簽核 - Level 3 會計人員（確認入帳）',
);

foreach ($rules as $level => $newName) {
    $stmt = $db->prepare("SELECT id, rule_name FROM approval_rules WHERE module='case_completion' AND level_order=? AND is_active=1 LIMIT 1");
    $stmt->execute(array($level));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo "Level $level: 規則不存在，請先跑 migration 115\n";
        continue;
    }
    if ($row['rule_name'] === $newName) {
        echo "Level $level: 已是新名稱，跳過\n";
        continue;
    }
    $db->prepare("UPDATE approval_rules SET rule_name = ? WHERE id = ?")
       ->execute(array($newName, $row['id']));
    echo "Level $level: 「" . $row['rule_name'] . "」→「" . $newName . "」\n";
}

echo "\n=== 完成 ===\n";
echo "請回到 /approvals.php?action=settings 確認規則名稱已更新\n";
