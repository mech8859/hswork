<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
require_once __DIR__ . '/../modules/cases/CaseModel.php';

try {
    $model = new CaseModel();
    $branchIds = Auth::getAccessibleBranchIds();
    $case = null;
    $branches = $model->getAllBranches();
    $skills = $model->getAllSkills();
    $salesUsers = $model->getSalesUsers($branchIds);
    $customerDemandOptions = $model->getDropdownOptions('customer_demand');
    $systemTypeOptions = $model->getDropdownOptions('system_type');
    $depositMethodOptions = $model->getDropdownOptions('deposit_method');

    $pageTitle = '新增案件';
    $currentPage = 'cases';
    
    ob_start();
    require __DIR__ . '/../templates/layouts/header.php';
    require __DIR__ . '/../templates/cases/form.php';
    require __DIR__ . '/../templates/layouts/footer.php';
    $output = ob_get_clean();
    echo $output;
} catch (Throwable $e) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString();
}
