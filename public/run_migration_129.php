<?php
/**
 * Migration 129: approval_rules 加「分公司」「角色」條件欄位
 * - condition_branch_ids: 逗號分隔分公司 id（空=全部）
 * - condition_user_roles: 逗號分隔角色代碼（空=全部）
 * 用於區分「潭子工程師請假 → A 流程」「員林工程師請假 → B 流程」等需求
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Session::getUser()['role'] !== 'boss') die('需要 boss 權限');
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();
echo "=== Migration 129 ===\n\n";

$cols = $db->query("SHOW COLUMNS FROM approval_rules")->fetchAll(PDO::FETCH_ASSOC);
$names = array_column($cols, 'Field');

if (!in_array('condition_branch_ids', $names)) {
    $db->exec("ALTER TABLE approval_rules ADD COLUMN condition_branch_ids VARCHAR(255) NULL COMMENT '適用分公司id（逗號分隔，空=全部）' AFTER case_types");
    echo "ADDED: condition_branch_ids\n";
} else echo "EXISTS: condition_branch_ids\n";

if (!in_array('condition_user_roles', $names)) {
    $db->exec("ALTER TABLE approval_rules ADD COLUMN condition_user_roles VARCHAR(255) NULL COMMENT '適用角色（逗號分隔，空=全部）' AFTER condition_branch_ids");
    echo "ADDED: condition_user_roles\n";
} else echo "EXISTS: condition_user_roles\n";

echo "\n=== approval_rules 最終欄位 ===\n";
foreach ($db->query("SHOW COLUMNS FROM approval_rules")->fetchAll(PDO::FETCH_ASSOC) as $c) {
    echo "  " . $c['Field'] . " " . $c['Type'] . "\n";
}

echo "\n完成\n";
