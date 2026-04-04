<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();

echo '<h2>Migration 031: 注意事項欄位</h2>';
try {
    $cols = array_column($db->query("SHOW COLUMNS FROM users")->fetchAll(), 'Field');
    if (!in_array('caution_notes', $cols)) {
        $db->exec("ALTER TABLE users ADD COLUMN caution_notes TEXT DEFAULT NULL COMMENT '注意事項'");
        echo '<p style="color:green">✓ users.caution_notes 已新增</p>';
    } else {
        echo '<p>已存在</p>';
    }
} catch (Exception $e) {
    echo '<p style="color:red">✗ ' . htmlspecialchars($e->getMessage()) . '</p>';
}
echo '<br><a href="/staff.php">返回人員管理</a>';
