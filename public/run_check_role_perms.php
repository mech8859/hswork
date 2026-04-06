<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/../includes/Database.php';
$db = Database::getInstance();

echo "=== system_roles 表結構 ===\n";
try {
    $cols = $db->query("SHOW COLUMNS FROM system_roles")->fetchAll(PDO::FETCH_COLUMN);
    echo implode(', ', $cols) . "\n";
} catch (Exception $e) {
    echo "表不存在: " . $e->getMessage() . "\n";
}

echo "\n=== purchaser 角色 (system_roles) ===\n";
try {
    $stmt = $db->prepare("SELECT * FROM system_roles WHERE role_key = 'purchaser'");
    $stmt->execute();
    $role = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($role) {
        foreach ($role as $k => $v) {
            if ($k === 'default_permissions') {
                echo "$k:\n";
                $perms = json_decode($v, true);
                if ($perms) {
                    foreach ($perms as $pk => $pv) {
                        echo "  $pk => " . (is_bool($pv) ? ($pv ? 'true' : 'false') : $pv) . "\n";
                    }
                }
            } else {
                echo "$k: $v\n";
            }
        }
    } else {
        echo "NOT FOUND -> 使用 config/app.php\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
