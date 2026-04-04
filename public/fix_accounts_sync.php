<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();

// Sync account_code → code, account_name → name
$count = $db->exec("UPDATE chart_of_accounts SET code = account_code, name = account_name WHERE account_code IS NOT NULL AND account_code != ''");
echo "<h2>已同步 {$count} 筆 account_code → code, account_name → name</h2>";
echo "<p><a href='/accounting.php?action=accounts'>→ 會計科目管理</a></p>";
