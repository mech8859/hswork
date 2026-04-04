<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

$db = Database::getInstance();
$results = array();

// 1. 修正 role enum，加入 manager 和 engineer
$sql = "ALTER TABLE users MODIFY COLUMN role ENUM('boss','manager','sales_manager','eng_manager','eng_deputy','engineer','sales','sales_assistant','admin_staff') NOT NULL DEFAULT 'engineer'";
try {
    $db->exec($sql);
    $results[] = 'OK: role enum updated (added manager, engineer)';
} catch (Exception $e) {
    $results[] = 'ERR: ' . $e->getMessage();
}

// 2. 檢查沒有角色的使用者
$stmt = $db->query("SELECT id, username, real_name, role FROM users ORDER BY id");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo '<h2>Migration 019 - Fix Role Enum</h2>';
echo '<ul>';
foreach ($results as $r) {
    $color = strpos($r, 'OK') === 0 ? 'green' : 'red';
    echo '<li style="color:' . $color . '">' . htmlspecialchars($r) . '</li>';
}
echo '</ul>';

echo '<h3>Current Users:</h3>';
echo '<table border="1" cellpadding="5"><tr><th>ID</th><th>Username</th><th>Name</th><th>Role</th></tr>';
foreach ($users as $u) {
    $roleDisplay = $u['role'] ?: '<span style="color:red">EMPTY</span>';
    echo '<tr><td>' . $u['id'] . '</td><td>' . htmlspecialchars($u['username']) . '</td><td>' . htmlspecialchars($u['real_name']) . '</td><td>' . $roleDisplay . '</td></tr>';
}
echo '</table>';
echo '<p><a href="/staff.php">Go to Staff</a></p>';
