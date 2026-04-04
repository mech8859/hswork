<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
require_once __DIR__ . '/../modules/procurement/ProcurementModel.php';

echo "<h2>Test Requisition Create Page</h2>";
$model = new ProcurementModel();
$branchIds = Auth::getAccessibleBranchIds();

$record = null;
$items = array();
$branches = $model->getBranches($branchIds);

echo "Branches: " . count($branches) . "<br>";
echo "Loading form template...<br>";

try {
    $pageTitle = '新增請購單';
    $currentPage = 'requisitions';
    require __DIR__ . '/../templates/layouts/header.php';
    require __DIR__ . '/../templates/requisitions/form.php';
    require __DIR__ . '/../templates/layouts/footer.php';
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
} catch (Error $e) {
    echo "Fatal Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine();
}
