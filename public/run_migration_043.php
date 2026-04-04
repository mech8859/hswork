<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();
$results = array();

try {
    $db->exec("ALTER TABLE case_readiness ADD COLUMN no_photo_allowed TINYINT(1) NOT NULL DEFAULT 0 COMMENT '客戶不允許拍照'");
    $results[] = "no_photo_allowed 欄位已新增";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate') !== false) {
        $results[] = "no_photo_allowed 欄位已存在，跳過";
    } else {
        $results[] = "錯誤: " . $e->getMessage();
    }
}

echo "<h2>Migration 043 - 客戶不允許拍照</h2><ul>";
foreach ($results as $r) echo "<li style='color:green'>$r</li>";
echo "</ul><p><a href='/cases.php'>← 案件管理</a></p>";
