<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();

echo "<h3>cases 表欄位檢查</h3>";
$stmt = $db->query("SHOW COLUMNS FROM cases");
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
$colNames = array();
foreach ($cols as $c) {
    $colNames[] = $c['Field'];
    echo $c['Field'] . " - " . $c['Type'] . "<br>";
}

echo "<br><b>金額欄位檢查：</b><br>";
$financialCols = array('deal_amount','is_tax_included','tax_amount','total_amount','deposit_amount','deposit_payment_date','deposit_method','balance_amount','completion_amount','total_collected','quote_amount');
foreach ($financialCols as $fc) {
    $exists = in_array($fc, $colNames) ? '✓ 存在' : '✗ 不存在';
    echo "{$fc}: {$exists}<br>";
}
