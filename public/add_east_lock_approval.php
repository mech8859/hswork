<?php
/**
 * 新增東區電子鎖的加班單 / 請假單簽核規則
 *   送簽 → 張孟歆 (單層簽核)
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('system.manage') && !Auth::hasPermission('all')) {
    die('No permission');
}

$db = Database::getInstance();
header('Content-Type: text/html; charset=utf-8');

echo "<h3>新增東區電子鎖 加班單/請假單 簽核規則</h3>";

// 找東區電子鎖 branch id
$bStmt = $db->prepare("SELECT id, name FROM branches WHERE name LIKE ?");
$bStmt->execute(array('%東區電子鎖%'));
$branches = $bStmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($branches)) {
    die("<p style='color:#c62828'>找不到東區電子鎖分公司</p>");
}
$branchId = (int)$branches[0]['id'];
$branchName = $branches[0]['name'];
echo "<p>東區電子鎖 branch_id = <b>{$branchId}</b> ({$branchName})</p>";

// 找張孟歆 user id
$uStmt = $db->prepare("SELECT id, real_name, is_active FROM users WHERE real_name = ?");
$uStmt->execute(array('張孟歆'));
$zhang = $uStmt->fetch(PDO::FETCH_ASSOC);
if (!$zhang) {
    die("<p style='color:#c62828'>找不到張孟歆帳號</p>");
}
if (!$zhang['is_active']) {
    die("<p style='color:#c62828'>張孟歆帳號已停用</p>");
}
$zhangId = (int)$zhang['id'];
echo "<p>張孟歆 user_id = <b>{$zhangId}</b></p>";

$dryRun = !isset($_GET['go']) || $_GET['go'] !== '1';
echo "<p>模式：" . ($dryRun ? '<b style="color:#c62828">Dry-run (預覽)</b>' : '<b style="color:#2e7d32">實際執行</b>') . "</p>";

// 檢查是否已存在（避免重複）
$ruleNameLeave = "東區電子鎖請假 - 張孟歆簽核";
$ruleNameOT    = "東區電子鎖加班 - 張孟歆簽核";

$checkStmt = $db->prepare("
    SELECT id, rule_name, module FROM approval_rules
    WHERE module IN ('leaves','overtime')
      AND condition_branch_ids = ?
");
$checkStmt->execute(array((string)$branchId));
$existing = $checkStmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($existing)) {
    echo "<h4>已存在的規則</h4><ul>";
    foreach ($existing as $e) {
        echo "<li>id={$e['id']}, module={$e['module']}, " . htmlspecialchars($e['rule_name']) . "</li>";
    }
    echo "</ul>";
}

$toInsert = array();
$hasLeave = false;
$hasOT = false;
foreach ($existing as $e) {
    if ($e['module'] === 'leaves') $hasLeave = true;
    if ($e['module'] === 'overtime') $hasOT = true;
}

if (!$hasLeave) $toInsert[] = array('module' => 'leaves', 'rule_name' => $ruleNameLeave);
if (!$hasOT)    $toInsert[] = array('module' => 'overtime', 'rule_name' => $ruleNameOT);

if (empty($toInsert)) {
    echo "<p style='color:#2e7d32'>✓ 兩條規則都已存在，無需新增。</p>";
    exit;
}

echo "<h4>將新增</h4><ul>";
foreach ($toInsert as $ti) {
    echo "<li>module=<b>{$ti['module']}</b>, rule_name={$ti['rule_name']}, approver=張孟歆 (id={$zhangId}), 分公司條件={$branchId}, 層級=1, min=0, max=無上限</li>";
}
echo "</ul>";

if ($dryRun) {
    echo "<p><a href='?go=1' onclick='return confirm(\"確定新增 " . count($toInsert) . " 條規則？\")' "
       . "style='display:inline-block;padding:8px 20px;background:#c62828;color:#fff;text-decoration:none;border-radius:4px'>"
       . "執行新增</a></p>";
    exit;
}

// 實際執行
$ins = $db->prepare("
    INSERT INTO approval_rules
      (module, rule_name, min_amount, max_amount,
       condition_type, condition_branch_ids, condition_user_roles,
       approver_role, approver_id, extra_approver_ids,
       level_order, is_active)
    VALUES (?, ?, 0, NULL, 'amount', ?, NULL, NULL, ?, NULL, 1, 1)
");

$done = 0;
foreach ($toInsert as $ti) {
    try {
        $ins->execute(array($ti['module'], $ti['rule_name'], (string)$branchId, $zhangId));
        $newId = (int)$db->lastInsertId();
        AuditLog::log('approvals', 'rule_create', $newId, "新增 {$ti['module']} 規則：{$ti['rule_name']}");
        echo "<p style='color:#2e7d32'>✓ 新增 {$ti['module']} 規則 id={$newId}</p>";
        $done++;
    } catch (Exception $e) {
        echo "<p style='color:#c62828'>✗ 新增 {$ti['module']} 失敗：" . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

echo "<hr><p style='color:#2e7d32'>完成 {$done} 條</p>";
echo "<p><a href='/approvals.php?action=settings'>回簽核設定確認</a></p>";
