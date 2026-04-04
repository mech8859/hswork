<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
require_once __DIR__ . '/../modules/procurement/ProcurementModel.php';
header('Content-Type: text/plain; charset=utf-8');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { echo "Usage: ?id=REQ_ID"; exit; }

$model = new ProcurementModel();
$result = $model->convertFromRequisition($id);

echo "=== Header ===\n";
print_r($result['header']);
echo "\n=== Items ===\n";
print_r($result['items']);
