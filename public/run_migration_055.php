<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('admin') && !in_array(Auth::user()['role'], array('boss','manager'))) {
    die('需要管理員權限');
}

$db = Database::getInstance();

try {
    // 檢查是否已存在
    $stmt = $db->prepare("SELECT COUNT(*) FROM system_roles WHERE role_key = 'hq'");
    $stmt->execute();
    if ($stmt->fetchColumn() > 0) {
        echo '<h1 style="color:gray">SKIP: 總公司角色已存在</h1>';
    } else {
        // 取得最大 sort_order
        $maxSort = $db->query("SELECT COALESCE(MAX(sort_order), 0) FROM system_roles")->fetchColumn();

        $db->prepare("INSERT INTO system_roles (role_key, role_label, is_active, sort_order) VALUES (?, ?, 1, ?)")
           ->execute(array('hq', '總公司', $maxSort + 1));
        echo '<h1 style="color:green">OK: 總公司角色已新增</h1>';
    }
} catch (Exception $e) {
    echo '<h1 style="color:red">ERROR: ' . htmlspecialchars($e->getMessage()) . '</h1>';
}

echo '<p><a href="/staff.php">前往人員管理</a></p>';
