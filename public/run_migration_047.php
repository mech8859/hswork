<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();
$results = array();

try {
    $db->exec("ALTER TABLE cases ADD COLUMN construction_note TEXT DEFAULT NULL COMMENT '施工注意事項' AFTER notes");
    $results[] = "construction_note 欄位已新增";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate') !== false) {
        $results[] = "construction_note 欄位已存在，跳過";
    } else {
        $results[] = "錯誤: " . $e->getMessage();
    }
}

echo "<h2>Migration 047 - 施工注意事項</h2><ul>";
foreach ($results as $r) echo "<li style='color:green'>$r</li>";
echo "</ul><p><a href='/cases.php'>← 案件管理</a></p>";
