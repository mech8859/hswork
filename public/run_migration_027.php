<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

if (Session::getUser()['role'] !== 'boss') {
    die('需要管理員權限');
}

$db = Database::getInstance();
$sql = file_get_contents(__DIR__ . '/../database/migration_027_procurement_inventory.sql');
$statements = array_filter(array_map('trim', explode(';', $sql)));

echo '<h2>Migration 027: 採購庫存模組</h2>';
echo '<pre>';

$success = 0;
$errors = 0;

foreach ($statements as $stmt) {
    $stmt = preg_replace('/^--.*$/m', '', $stmt);
    $stmt = trim($stmt);
    if (empty($stmt)) continue;

    // Identify statement type for display
    $label = '';
    if (preg_match('/CREATE TABLE IF NOT EXISTS (\w+)/', $stmt, $m)) {
        $label = '建立表 ' . $m[1];
    } elseif (preg_match('/INSERT INTO (\w+)/', $stmt, $m)) {
        $label = '插入種子 ' . $m[1];
    } else {
        continue;
    }

    try {
        $db->exec($stmt);
        echo "✅ {$label} 成功\n";
        $success++;
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo "⚠️ {$label} 已存在，跳過\n";
        } elseif (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            echo "⚠️ {$label} 資料已存在，跳過\n";
        } else {
            echo "❌ {$label} 失敗: " . $e->getMessage() . "\n";
            $errors++;
        }
    }
}

echo "\n完成: {$success} 成功, {$errors} 失敗\n";
echo '</pre>';
echo '<p><a href="/vendors.php">廠商管理</a> | <a href="/inventory.php">庫存管理</a> | <a href="/requisitions.php">請購單</a> | <a href="/purchase_orders.php">採購單</a> | <a href="/warehouse_transfers.php">倉庫調撥</a></p>';
