<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

$db = Database::getInstance();

// 把 case_type = 'other' 改回 NULL
$count = $db->exec("UPDATE cases SET case_type = NULL WHERE case_type = 'other'");

echo "<h2>修正案別</h2>";
echo "<p style='color:green'>已將 {$count} 筆案別「其他」改回空值</p>";
echo "<p><a href='/cases.php'>← 回案件管理</a></p>";
