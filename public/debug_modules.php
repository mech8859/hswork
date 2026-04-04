<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

echo "<h2>Module Debug</h2>";

$tests = array(
    'StockModel' => __DIR__ . '/../modules/inventory/StockModel.php',
    'GoodsReceiptModel' => __DIR__ . '/../modules/procurement/GoodsReceiptModel.php',
    'ReturnModel' => __DIR__ . '/../modules/returns/ReturnModel.php',
    'DeliveryModel' => __DIR__ . '/../modules/delivery/DeliveryModel.php',
    'InventoryModel' => __DIR__ . '/../modules/inventory/InventoryModel.php',
);

foreach ($tests as $class => $file) {
    echo "<h3>$class</h3>";
    if (!file_exists($file)) {
        echo "<span style='color:red'>FILE NOT FOUND: $file</span><br>";
        continue;
    }
    try {
        require_once $file;
        $obj = new $class();
        echo "<span style='color:green'>OK - class loaded</span><br>";
    } catch (Throwable $e) {
        echo "<span style='color:red'>ERROR: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . "</span><br>";
    }
}

// Test template paths
echo "<h3>Template Paths</h3>";
$tpls = array(
    'stock_ins/list.php',
    'stock_outs/list.php',
    'goods_receipts/list.php',
    'returns/list.php',
    'delivery_orders/list.php',
);
foreach ($tpls as $t) {
    $path = __DIR__ . '/../templates/' . $t;
    $exists = file_exists($path);
    echo ($exists ? '<span style="color:green">✓</span>' : '<span style="color:red">✗</span>') . " templates/$t<br>";
}
