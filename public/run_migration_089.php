<?php
/**
 * Migration 089: 案件加「是否已完工」欄位
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();
$results = [];

try {
    $db->exec("ALTER TABLE cases ADD COLUMN is_completed TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否已完工' AFTER construction_note");
    $results[] = "cases.is_completed 欄位已新增";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        $results[] = "cases.is_completed 已存在，跳過";
    } else {
        $results[] = "錯誤: " . $e->getMessage();
    }
}

echo "<h3>Migration 089 結果</h3>";
foreach ($results as $r) {
    echo "<p>$r</p>";
}
