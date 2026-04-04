<?php
/**
 * Migration 045 - 完工簽核預設規則
 * 新增 case_completion 模組的兩關簽核規則：
 *   Level 1: eng_manager (工程主管)
 *   Level 2: admin_staff (會計確認)
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();
$results = array();

// Level 1: 工程主管
try {
    $check = $db->prepare("SELECT COUNT(*) FROM approval_rules WHERE module = 'case_completion' AND level_order = 1");
    $check->execute();
    if ((int)$check->fetchColumn() === 0) {
        $db->prepare("
            INSERT INTO approval_rules (module, rule_name, min_amount, max_amount, min_profit_rate, approver_role, approver_id, level_order, is_active)
            VALUES ('case_completion', '完工簽核 - 工程主管', 0, NULL, NULL, 'eng_manager', NULL, 1, 1)
        ")->execute();
        $results[] = "Level 1 規則已建立（工程主管）";
    } else {
        $results[] = "Level 1 規則已存在，跳過";
    }
} catch (PDOException $e) {
    $results[] = "Level 1 錯誤: " . $e->getMessage();
}

// Level 2: 會計（行政人員）
try {
    $check = $db->prepare("SELECT COUNT(*) FROM approval_rules WHERE module = 'case_completion' AND level_order = 2");
    $check->execute();
    if ((int)$check->fetchColumn() === 0) {
        $db->prepare("
            INSERT INTO approval_rules (module, rule_name, min_amount, max_amount, min_profit_rate, approver_role, approver_id, level_order, is_active)
            VALUES ('case_completion', '完工簽核 - 會計確認', 0, NULL, NULL, 'admin_staff', NULL, 2, 1)
        ")->execute();
        $results[] = "Level 2 規則已建立（會計確認）";
    } else {
        $results[] = "Level 2 規則已存在，跳過";
    }
} catch (PDOException $e) {
    $results[] = "Level 2 錯誤: " . $e->getMessage();
}

echo "<h2>Migration 045 - 完工簽核預設規則</h2><ul>";
foreach ($results as $r) echo "<li style='color:green'>$r</li>";
echo "</ul><p><a href='/approvals.php?action=settings'>← 簽核設定</a></p>";
