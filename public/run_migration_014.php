<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

$db = Database::getInstance();
$results = array();

// 儲存明文密碼（供系統管理者查看）
try {
    $db->exec("ALTER TABLE users ADD COLUMN plain_password VARCHAR(255) DEFAULT NULL COMMENT '明文密碼(管理者查看用)' AFTER password_hash");
    $results[] = 'users.plain_password column added';
} catch (Exception $e) {
    $results[] = 'users.plain_password: ' . $e->getMessage();
}

echo '<h2>Migration 014 - Plain Password Column</h2>';
echo '<ul>';
foreach ($results as $r) {
    echo '<li>' . htmlspecialchars($r) . '</li>';
}
echo '</ul>';
echo '<p><a href="/staff.php">Go to Staff</a></p>';
