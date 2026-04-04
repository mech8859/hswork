<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();
try {
    $db->exec("ALTER TABLE cases ADD COLUMN needs_multiple_visits TINYINT(1) NOT NULL DEFAULT 0 AFTER is_large_project");
    echo "✓ needs_multiple_visits 欄位已新增";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate') !== false) echo "⚠ 已存在";
    else echo "✗ " . $e->getMessage();
}
