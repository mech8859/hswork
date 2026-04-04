<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');

$db = Database::getInstance();
$newHash = password_hash('hs823932', PASSWORD_DEFAULT);
$stmt = $db->prepare("UPDATE users SET password_hash = ?, failed_login_count = 0, locked_until = NULL WHERE username = 'hs023'");
$stmt->execute(array($newHash));

$affected = $stmt->rowCount();
header('Content-Type: text/html; charset=utf-8');
if ($affected > 0) {
    echo '<h3 style="color:green">hs023 密碼已重設為 hs823932，失敗次數已清除</h3>';
} else {
    echo '<h3 style="color:red">找不到帳號 hs023</h3>';
}
echo '<p><a href="/staff.php">返回人員管理</a></p>';
