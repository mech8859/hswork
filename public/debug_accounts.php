<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
require_once __DIR__ . '/../modules/accounting/AccountingModel.php';
$model = new AccountingModel();
$all = $model->getAccountsTree(false);
echo "<h3>getAccountsTree returned: " . count($all) . " rows</h3>";
if (count($all) > 0) {
    echo "<p>First: " . htmlspecialchars($all[0]['code'] . ' - ' . $all[0]['name']) . "</p>";
    echo "<p>Last: " . htmlspecialchars($all[count($all)-1]['code'] . ' - ' . $all[count($all)-1]['name']) . "</p>";
}
// Direct count
$stmt = Database::getInstance()->query("SELECT COUNT(*) FROM chart_of_accounts WHERE is_active = 1");
echo "<h3>Direct DB count: " . $stmt->fetchColumn() . "</h3>";
// Check for table structure
$cols = Database::getInstance()->query("SHOW COLUMNS FROM chart_of_accounts")->fetchAll(PDO::FETCH_ASSOC);
echo "<h3>Table columns:</h3><ul>";
foreach ($cols as $c) echo "<li>" . $c['Field'] . " (" . $c['Type'] . ")</li>";
echo "</ul>";
