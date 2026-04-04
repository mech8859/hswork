<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

$db = Database::getInstance();
$errors = array();
$success = array();

// quotations 增加 hide_model_on_print 欄位
try {
    $db->exec("ALTER TABLE quotations ADD COLUMN hide_model_on_print TINYINT(1) NOT NULL DEFAULT 0 COMMENT '報價單不顯示型號'");
    $success[] = "quotations 表已新增 hide_model_on_print 欄位";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        $success[] = "hide_model_on_print 欄位已存在，跳過";
    } else {
        $errors[] = "新增 hide_model_on_print 欄位失敗: " . $e->getMessage();
    }
}

echo "<h2>Migration 037 - 報價單型號顯示設定</h2>";
if ($success) {
    echo "<div style='color:green'><ul>";
    foreach ($success as $s) echo "<li>$s</li>";
    echo "</ul></div>";
}
if ($errors) {
    echo "<div style='color:red'><ul>";
    foreach ($errors as $e) echo "<li>$e</li>";
    echo "</ul></div>";
}
echo "<p><a href='/quotations.php'>← 回報價管理</a></p>";
