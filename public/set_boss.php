<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
header('Content-Type: text/plain; charset=utf-8');
$db = Database::getInstance();
$affected = $db->exec("UPDATE users SET must_change_password = 1 WHERE role = 'boss'");
echo "OK: {$affected} 位 boss 帳號設為需改密碼\n";
