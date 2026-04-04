<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

if (Session::getUser()['role'] !== 'boss') {
    die('需要管理員權限');
}

$db = Database::getInstance();
$sql = file_get_contents(__DIR__ . '/../database/migration_026_finance2.sql');
$statements = array_filter(array_map('trim', explode(';', $sql)));

echo '<h2>Migration 026: 銀行帳戶/零用金/備用金/現金明細</h2>';
echo '<pre>';

$success = 0;
$errors = 0;

foreach ($statements as $stmt) {
    $stmt = preg_replace('/^--.*$/m', '', $stmt);
    $stmt = trim($stmt);
    if (empty($stmt)) continue;

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
echo '<p><a href="/bank_transactions.php">銀行帳戶明細</a> | <a href="/petty_cash.php">零用金管理</a> | <a href="/reserve_fund.php">備用金管理</a> | <a href="/cash_details.php">現金明細</a></p>';
