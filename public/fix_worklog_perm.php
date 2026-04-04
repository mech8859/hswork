<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();

echo '<h2>修正施工回報權限</h2>';

// 取所有有 custom_permissions 的使用者
$stmt = $db->query("SELECT id, username, real_name, role, custom_permissions FROM users WHERE custom_permissions IS NOT NULL AND custom_permissions != ''");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$fixed = 0;
foreach ($users as $u) {
    $cp = json_decode($u['custom_permissions'], true);
    if (!$cp || !is_array($cp)) continue;
    
    $sections = isset($cp['case_sections']) ? $cp['case_sections'] : null;
    if ($sections === null) continue; // 沒設定 case_sections，用預設
    
    if (!in_array('worklog', $sections)) {
        $sections[] = 'worklog';
        $cp['case_sections'] = $sections;
        $db->prepare("UPDATE users SET custom_permissions = ? WHERE id = ?")->execute(array(json_encode($cp), $u['id']));
        $fixed++;
        echo '<p style="color:green">✓ ' . htmlspecialchars($u['real_name']) . ' (' . $u['role'] . ') — 已加入 worklog 權限</p>';
    } else {
        echo '<p>' . htmlspecialchars($u['real_name']) . ' — 已有 worklog 權限</p>';
    }
}

if ($fixed === 0) {
    echo '<p>所有使用者都已有 worklog 權限，或使用預設值</p>';
}

echo '<p style="color:orange;font-weight:600;margin-top:16px">⚠ 請重新登入讓權限生效</p>';
echo '<br><a href="/cases.php">返回案件管理</a>';
