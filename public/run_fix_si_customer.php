<?php
/**
 * 一次性：回填入庫單的 customer_name（從來源出庫單帶入）
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();

// 從出庫單帶入 customer_name
$stmt = $db->query("
    UPDATE stock_ins si
    JOIN stock_outs so ON si.source_id = so.id
    SET si.customer_name = so.customer_name
    WHERE si.source_type IN ('return_material','manual_return','delivery_order','case')
      AND (si.customer_name IS NULL OR si.customer_name = '')
      AND so.customer_name IS NOT NULL AND so.customer_name != ''
");
$count = $stmt->rowCount();
echo "Updated {$count} stock_ins with customer_name from stock_outs.\n";
echo "Done.\n";
