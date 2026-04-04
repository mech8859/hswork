<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();

// 直接幫當前用戶加上 worklog 權限
$userId = Auth::id();
$stmt = $db->prepare("SELECT custom_permissions FROM users WHERE id = ?");
$stmt->execute(array($userId));
$cp = json_decode($stmt->fetchColumn() ?: '{}', true);

if (!isset($cp['case_sections'])) {
    $cp['case_sections'] = array('basic','finance','schedule','attach','worklog','site','contacts','skills','delete');
} elseif (!in_array('worklog', $cp['case_sections'])) {
    $cp['case_sections'][] = 'worklog';
}

$db->prepare("UPDATE users SET custom_permissions = ? WHERE id = ?")->execute(array(json_encode($cp), $userId));

echo '<h2>已修正</h2>';
echo '<p>case_sections: ' . implode(', ', $cp['case_sections']) . '</p>';
echo '<p style="color:green;font-weight:600">請重新登入</p>';
echo '<br><a href="/logout.php">登出重新登入</a>';
