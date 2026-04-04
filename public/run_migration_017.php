<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

$db = Database::getInstance();
$results = array();

$sql = "ALTER TABLE users ADD COLUMN custom_permissions TEXT DEFAULT NULL COMMENT '個人權限設定(JSON)，NULL=使用角色預設'";
try {
    $db->exec($sql);
    $results[] = 'OK: users.custom_permissions column added';
} catch (Exception $e) {
    $results[] = 'ERR: ' . $e->getMessage();
}

echo '<h2>Migration 017 - Custom Permissions</h2>';
echo '<ul>';
foreach ($results as $r) {
    echo '<li>' . htmlspecialchars($r) . '</li>';
}
echo '</ul>';
echo '<p><a href="/staff.php">Go to Staff</a></p>';
