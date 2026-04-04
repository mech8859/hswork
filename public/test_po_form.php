<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
require_once __DIR__ . '/../modules/procurement/ProcurementModel.php';

$model = new ProcurementModel();
$branchIds = Auth::getAccessibleBranchIds();

echo "Step 1<br>";
$record = null;
$isFromRequisition = false;
$items = array();
echo "Step 2<br>";

try {
    $branches = $model->getBranches($branchIds);
    echo "Step 3: branches=" . count($branches) . "<br>";
} catch (\Throwable $e) {
    echo "branches error: " . $e->getMessage() . "<br>";
    $branches = array();
}

try {
    $vendors = $model->getVendors(array());
    echo "Step 4: vendors=" . count($vendors) . "<br>";
} catch (\Throwable $e) {
    echo "vendors error: " . $e->getMessage() . "<br>";
    $vendors = array();
}

$pageTitle = '測試';
$currentPage = 'purchase_orders';
echo "Step 5: loading form<br>";

try {
    require __DIR__ . '/../templates/layouts/header.php';
    require __DIR__ . '/../templates/purchase_orders/form.php';
    require __DIR__ . '/../templates/layouts/footer.php';
} catch (\Throwable $e) {
    echo "<pre>Template Error: " . $e->getMessage() . "\nFile: " . $e->getFile() . ":" . $e->getLine() . "</pre>";
}
