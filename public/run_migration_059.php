<?php
/**
 * Migration 059 - 預算管理表 (budgets)
 * 執行 database/migration_041_budgets.sql
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

if (!Auth::hasPermission('all') && !in_array(Auth::user()['role'], array('boss','manager'))) {
    die('Permission denied');
}

$db = Database::getInstance();
$sqlFile = __DIR__ . '/../database/migration_041_budgets.sql';

if (!file_exists($sqlFile)) {
    die('SQL file not found: ' . $sqlFile);
}

$sql = file_get_contents($sqlFile);
echo '<h2>Migration 059 - 預算管理表</h2>';
echo '<pre>';
try {
    $db->exec($sql);
    echo "Migration executed successfully.\n";
    echo "Table 'budgets' created (or already exists).\n";
} catch (PDOException $e) {
    echo "Error: " . htmlspecialchars($e->getMessage()) . "\n";
}
echo '</pre>';
echo '<p><a href="/accounting.php?action=budget">前往預算編輯</a></p>';
