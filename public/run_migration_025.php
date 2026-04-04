<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

if (Session::getUser()['role'] !== 'boss') {
    die('需要管理員權限');
}

$db = Database::getInstance();
$sql = file_get_contents(__DIR__ . '/../database/migration_025_finance.sql');

// 拆分成單獨語句
$statements = array_filter(array_map('trim', explode(';', $sql)));

echo '<h2>Migration 025: 財務會計模組</h2>';
echo '<pre>';

$success = 0;
$errors = 0;

foreach ($statements as $stmt) {
    if (empty($stmt)) continue;
    // 移除開頭的註解行
    $stmt = preg_replace('/^--.*$/m', '', $stmt);
    $stmt = trim($stmt);
    if (empty($stmt)) continue;

    // 取表名
    if (preg_match('/CREATE TABLE IF NOT EXISTS (\w+)/', $stmt, $m)) {
        $tableName = $m[1];
    } else {
        continue;
    }

    try {
        $db->exec($stmt);
        echo "✅ 建立表 {$tableName} 成功\n";
        $success++;
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo "⚠️ 表 {$tableName} 已存在，跳過\n";
        } else {
            echo "❌ 表 {$tableName} 失敗: " . $e->getMessage() . "\n";
            $errors++;
        }
    }
}

echo "\n完成: {$success} 成功, {$errors} 失敗\n";
echo '</pre>';
echo '<p><a href="/receivables.php">前往應收帳款</a></p>';
