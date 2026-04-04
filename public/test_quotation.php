<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
require_once __DIR__ . '/../modules/quotations/QuotationModel.php';

echo "<h3>Step 1: Module loaded OK</h3>";

$canManage = Auth::hasPermission('quotations.manage');
$canView = Auth::hasPermission('quotations.view');
$canOwn = Auth::hasPermission('quotations.own');
echo "<p>Permissions: manage=$canManage, view=$canView, own=$canOwn</p>";

$model = new QuotationModel();
echo "<h3>Step 2: Model created OK</h3>";

$branchIds = Auth::getAccessibleBranchIds();
echo "<p>Branch IDs: " . implode(',', $branchIds) . "</p>";

echo "<h3>Step 3: Getting salespeople...</h3>";
try {
    $salespeople = $model->getSalespeople($branchIds);
    echo "<p>Salespeople count: " . count($salespeople) . "</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>ERROR: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<h3>Step 4: Getting branches...</h3>";
try {
    $branches = $model->getBranches();
    echo "<p>Branches count: " . count($branches) . "</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>ERROR: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<h3>Step 5: Getting cases...</h3>";
try {
    $cases = $model->getCaseOptions($branchIds);
    echo "<p>Cases count: " . count($cases) . "</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>ERROR: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<h3>Step 6: Loading template...</h3>";
try {
    $quote = null;
    $pageTitle = '新增報價單';
    $currentPage = 'quotations';
    require __DIR__ . '/../templates/layouts/header.php';
    echo "<p style='color:green'>Header loaded OK</p>";
    require __DIR__ . '/../templates/quotations/form.php';
    echo "<p style='color:green'>Form loaded OK</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>ERROR: " . htmlspecialchars($e->getMessage()) . "</p>";
}
