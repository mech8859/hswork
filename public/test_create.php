<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
require_once __DIR__ . '/../modules/cases/CaseModel.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $model = new CaseModel();
    $branchIds = Auth::getAccessibleBranchIds();
    echo "branchIds: " . json_encode($branchIds) . "\n";
    
    $branches = $model->getAllBranches();
    echo "branches: " . count($branches) . "\n";
    
    $skills = $model->getAllSkills();
    echo "skills: " . count($skills) . "\n";
    
    $salesUsers = $model->getSalesUsers($branchIds);
    echo "salesUsers: " . count($salesUsers) . "\n";
    
    $customerDemandOptions = $model->getDropdownOptions('customer_demand');
    echo "customerDemand: " . count($customerDemandOptions) . "\n";
    
    $systemTypeOptions = $model->getDropdownOptions('system_type');
    echo "systemType: " . count($systemTypeOptions) . "\n";
    
    $depositMethodOptions = $model->getDropdownOptions('deposit_method');
    echo "depositMethod: " . count($depositMethodOptions) . "\n";
    
    echo "\n全部 OK！";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
