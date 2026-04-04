<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

header('Content-Type: text/plain; charset=utf-8');
$db = Database::getInstance();

// 1. Rename manager label
$db->prepare("UPDATE system_roles SET role_label = ? WHERE role_key = 'manager'")->execute(array('分公司／部門管理者'));
echo "Renamed manager to 分公司／部門管理者\n";

// 2. Add new roles
$newRoles = array(
    array('vice_president', '副總', 2),
    array('assistant_manager', '協理', 4),
    array('accountant', '會計人員', 12),
    array('warehouse', '倉管', 13),
    array('purchaser', '採購', 14),
);

foreach ($newRoles as $r) {
    $check = $db->prepare("SELECT id FROM system_roles WHERE role_key = ?");
    $check->execute(array($r[0]));
    if ($check->fetch()) {
        echo "Role {$r[0]} already exists, skipped.\n";
        continue;
    }
    $db->prepare("INSERT INTO system_roles (role_key, role_label, is_system, sort_order, is_active) VALUES (?, ?, 1, ?, 1)")
        ->execute(array($r[0], $r[1], $r[2]));
    echo "Added role: {$r[1]} ({$r[0]})\n";
}

// 3. Add role to users table enum if needed (MySQL allows any varchar, so just verify)
echo "\nDone. New roles are now available in the system.\n";
