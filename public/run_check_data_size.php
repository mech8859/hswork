<?php
require_once __DIR__ . '/../includes/Database.php';
$db = Database::getInstance();
header('Content-Type: text/plain; charset=utf-8');

echo "=== 資料筆數 ===\n";
$tables = array(
    'customers' => '客戶',
    'cases' => '案件',
    'case_payments' => '帳款交易',
    'case_attachments' => '案件附件',
    'customer_files' => '客戶檔案',
    'users' => '人員',
    'products' => '產品',
    'inventory' => '庫存',
    'quotation_items' => '報價明細',
);
foreach ($tables as $t => $label) {
    try {
        $cnt = $db->query("SELECT COUNT(*) FROM {$t}")->fetchColumn();
        echo "  {$label} ({$t}): {$cnt} 筆\n";
    } catch (Exception $e) {
        echo "  {$label} ({$t}): 表不存在\n";
    }
}

echo "\n=== 客戶欄位 ===\n";
$cols = $db->query("SHOW COLUMNS FROM customers")->fetchAll(PDO::FETCH_COLUMN);
echo "  " . implode(', ', $cols) . "\n";
echo "  共 " . count($cols) . " 個欄位\n";

echo "\n=== 案件欄位 ===\n";
$cols = $db->query("SHOW COLUMNS FROM cases")->fetchAll(PDO::FETCH_COLUMN);
echo "  共 " . count($cols) . " 個欄位\n";
