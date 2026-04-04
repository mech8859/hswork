<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
echo "<h3>1. Bootstrap OK</h3>";

require_once __DIR__ . '/../modules/inventory/StockModel.php';
echo "<h3>2. StockModel loaded</h3>";

require_once __DIR__ . '/../modules/inventory/InventoryModel.php';
echo "<h3>3. InventoryModel loaded</h3>";

$model = new StockModel();
echo "<h3>4. StockModel instantiated</h3>";

$filters = array('status' => '', 'warehouse_id' => '', 'source_type' => '', 'keyword' => '', 'date_from' => '', 'date_to' => '');
try {
    $result = $model->getStockIns($filters);
    echo "<h3>5. getStockIns OK - " . count($result['data']) . " records</h3>";
} catch (Exception $e) {
    echo "<h3 style='color:red'>5. getStockIns ERROR: " . $e->getMessage() . "</h3>";
}

echo "<h3>6. About to load header...</h3>";
$pageTitle = 'Debug';
$currentPage = 'stock_ins';
try {
    require __DIR__ . '/../templates/layouts/header.php';
    echo "<h3>7. Header loaded OK</h3>";
} catch (Exception $e) {
    echo "<h3 style='color:red'>7. Header ERROR: " . $e->getMessage() . "</h3>";
}
