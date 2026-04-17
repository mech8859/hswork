<?php
/**
 * Migration 130: quotation_sections 加 discount_amount（區段優惠價）
 * 專案模式下每個區段可獨立設定優惠價，未稅合計改用優惠價統計
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Session::getUser()['role'] !== 'boss') die('需要 boss 權限');
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();
echo "=== Migration 130 ===\n\n";

$cols = $db->query("SHOW COLUMNS FROM quotation_sections")->fetchAll(PDO::FETCH_ASSOC);
$names = array_column($cols, 'Field');

if (!in_array('discount_amount', $names)) {
    $db->exec("ALTER TABLE quotation_sections ADD COLUMN discount_amount INT DEFAULT NULL COMMENT '區段優惠價（NULL=未啟用，使用 subtotal）' AFTER subtotal");
    echo "ADDED: discount_amount\n";
} else {
    echo "EXISTS: discount_amount\n";
}

echo "\n完成\n";
