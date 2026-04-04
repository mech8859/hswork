<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
require_once __DIR__ . '/../modules/cases/CaseModel.php';
header('Content-Type: text/plain; charset=utf-8');

try {
    echo "Testing generate_doc_number('customers')...\n";
    $no = generate_doc_number('customers');
    echo "Result: {$no}\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
}

try {
    echo "\nTesting deleteCase...\n";
    $model = new CaseModel();
    // Just test getById
    $case = $model->getById(99999);
    echo "getById(99999): " . ($case ? 'found' : 'not found') . "\n";
    echo "OK\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
