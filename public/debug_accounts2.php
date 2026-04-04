<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
require_once __DIR__ . '/../modules/accounting/AccountingModel.php';
$model = new AccountingModel();
$all = $model->getAccountsTree(false);
echo "<h3>共 " . count($all) . " 筆</h3>";
echo "<table border='1' cellpadding='4' style='border-collapse:collapse;font-size:12px'>";
echo "<tr><th>ID</th><th>code</th><th>name</th><th>account_code</th><th>account_name</th><th>account_type</th><th>level</th><th>is_detail</th><th>normal_balance</th><th>is_active</th><th>parent_id</th></tr>";
foreach (array_slice($all, 0, 30) as $a) {
    echo "<tr>";
    echo "<td>" . ($a['id'] ?? '') . "</td>";
    echo "<td>" . htmlspecialchars($a['code'] ?? '') . "</td>";
    echo "<td>" . htmlspecialchars($a['name'] ?? '') . "</td>";
    echo "<td>" . htmlspecialchars($a['account_code'] ?? '') . "</td>";
    echo "<td>" . htmlspecialchars($a['account_name'] ?? '') . "</td>";
    echo "<td>" . ($a['account_type'] ?? '') . "</td>";
    echo "<td>" . ($a['level'] ?? '') . "</td>";
    echo "<td>" . ($a['is_detail'] ?? '') . "</td>";
    echo "<td>" . ($a['normal_balance'] ?? '') . "</td>";
    echo "<td>" . ($a['is_active'] ?? '') . "</td>";
    echo "<td>" . ($a['parent_id'] ?? '') . "</td>";
    echo "</tr>";
}
echo "</table>";
