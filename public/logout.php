<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$user = Auth::user();
if ($user) {
    AuditLog::log('auth', 'logout', $user['id'], $user['real_name']);
}
Auth::logout();
redirect('/login.php');
