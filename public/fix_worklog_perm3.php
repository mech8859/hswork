<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();

// 幫所有有 custom_permissions 但缺 worklog 的用戶補上
$stmt = $db->query("SELECT id, real_name, custom_permissions FROM users WHERE custom_permissions IS NOT NULL AND custom_permissions != ''");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
$fixed = 0;

foreach ($users as $u) {
    $cp = json_decode($u['custom_permissions'], true);
    if (!$cp || !isset($cp['case_sections'])) continue;
    if (in_array('worklog', $cp['case_sections'])) continue;
    
    $cp['case_sections'][] = 'worklog';
    $db->prepare("UPDATE users SET custom_permissions = ? WHERE id = ?")->execute(array(json_encode($cp), $u['id']));
    $fixed++;
    echo $u['real_name'] . ' — 已補上 worklog<br>';
}

// 也處理沒有 case_sections 但有 custom_permissions 的
$stmt2 = $db->query("SELECT id, real_name, role, custom_permissions FROM users WHERE (custom_permissions IS NULL OR custom_permissions = '' OR custom_permissions = '{}') AND role IN ('boss','manager')");
foreach ($stmt2->fetchAll(PDO::FETCH_ASSOC) as $u) {
    echo $u['real_name'] . ' (boss/manager) — 使用 Auth 自動全開，無需修正<br>';
}

echo "<br>修正 {$fixed} 位<br>";
echo '<p style="color:green;font-weight:600">boss/manager 角色已改為自動全開，不受 case_sections 限制</p>';
echo '<a href="/logout.php">登出重新登入</a>';
