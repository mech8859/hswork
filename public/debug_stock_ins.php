<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
require_once __DIR__ . '/../modules/inventory/StockModel.php';
require_once __DIR__ . '/../modules/inventory/InventoryModel.php';

echo "1. Models loaded<br>";

$model = new StockModel();
$invModel = new InventoryModel();

echo "2. Objects created<br>";

$filters = array(
    'status' => '', 'warehouse_id' => '', 'keyword' => '',
    'date_from' => '', 'date_to' => '', 'source_type' => '',
);

try {
    $records = $model->getStockIns($filters);
    echo "3. Query OK, records: " . count($records) . "<br>";
} catch (Throwable $e) {
    echo "3. Query ERROR: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . "<br>";
    $records = array();
}

try {
    $warehouses = $invModel->getWarehouses();
    echo "4. Warehouses OK: " . count($warehouses) . "<br>";
} catch (Throwable $e) {
    echo "4. Warehouses ERROR: " . $e->getMessage() . "<br>";
    $warehouses = array();
}

$pageTitle = '入庫單';
$currentPage = 'stock_ins';

echo "5. About to load header<br>";
try {
    require __DIR__ . '/../templates/layouts/header.php';
    echo "6. Header loaded<br>";
} catch (Throwable $e) {
    echo "6. Header ERROR: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . "<br>";
}

echo "7. About to load list template<br>";
try {
    require __DIR__ . '/../templates/stock_ins/list.php';
    echo "8. List loaded<br>";
} catch (Throwable $e) {
    echo "8. List ERROR: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . "<br>";
}
