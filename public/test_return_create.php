<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
require_once __DIR__ . '/../modules/returns/ReturnModel.php';
require_once __DIR__ . '/../modules/inventory/InventoryModel.php';

$model = new ReturnModel();
$invModel = new InventoryModel();

echo "Step 1: model loaded<br>";

$record = null;
$items = array();
$warehouses = $model->getWarehouses();
echo "Step 2: warehouses=" . count($warehouses) . "<br>";

try {
    $db = Database::getInstance();
    echo "Step 2.5: db ok<br>";
    $branches = $db->query("SELECT id, name FROM branches WHERE is_active = 1 ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);
    echo "Step 3: branches=" . count($branches) . "<br>";
} catch (\Throwable $e) {
    echo "Step 3 ERROR: " . $e->getMessage() . "<br>";
    $branches = array();
}

echo "Step 4: loading template<br>";

$pageTitle = '新增退貨單';
$currentPage = 'returns';
$isEdit = false;

try {
    require __DIR__ . '/../templates/layouts/header.php';
    require __DIR__ . '/../templates/returns/form.php';
    require __DIR__ . '/../templates/layouts/footer.php';
} catch (\Throwable $e) {
    echo "<pre>Template Error: " . $e->getMessage() . "\nFile: " . $e->getFile() . ":" . $e->getLine() . "</pre>";
}
