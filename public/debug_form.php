<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }
require_once __DIR__ . '/../modules/cases/CaseModel.php';
$model = new CaseModel();
$case = $model->getById(2254);
echo '<pre>';
echo 'case loaded: ' . ($case ? 'yes' : 'no') . "\n";
echo 'case_type: ' . ($case['case_type'] ?? 'NULL') . "\n";
echo 'completion_date: ' . ($case['completion_date'] ?? 'NULL') . "\n";
echo 'company: ' . ($case['company'] ?? 'NULL') . "\n";
echo 'case_source: ' . ($case['case_source'] ?? 'NULL') . "\n";
echo "\nTrying to render form.php...\n";
ob_start();
try {
    $branchIds = Auth::getAccessibleBranchIds();
    $branches = $model->getAllBranches();
    $skills = $model->getAllSkills();
    $salesUsers = $model->getSalesUsers($branchIds);
    $customerDemandOptions = $model->getDropdownOptions('customer_demand');
    $systemTypeOptions = $model->getDropdownOptions('system_type');
    $depositMethodOptions = $model->getDropdownOptions('deposit_method');
    $caseCompanyOptions = $model->getDropdownOptions('case_company');
    $caseSourceOptions = $model->getDropdownOptions('case_source');
    $worklogTimeline = array();
    $pageTitle = 'Debug';
    $currentPage = 'cases';
    require __DIR__ . '/../templates/cases/form.php';
    $html = ob_get_clean();
    echo "Rendered OK, length=" . strlen($html) . "\n";
} catch (Throwable $e) {
    ob_end_clean();
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
echo '</pre>';
