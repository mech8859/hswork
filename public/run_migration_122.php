<?php
/**
 * Migration 122: 建立 case_sales_invoice_vouchers 表
 * 案件管理端專屬的銷項發票憑證存放，不同步至銷項發票模組
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('all')) die('admin only');
$db = Database::getInstance();

echo "<!DOCTYPE html><meta charset=utf-8><pre>";

try {
    $exists = $db->query("SHOW TABLES LIKE 'case_sales_invoice_vouchers'")->fetch();
    if ($exists) {
        echo "✓ 表已存在，略過建立\n";
    } else {
        $db->exec("
            CREATE TABLE case_sales_invoice_vouchers (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                case_id INT UNSIGNED NOT NULL,
                sales_invoice_id INT UNSIGNED NOT NULL,
                file_path VARCHAR(500) NOT NULL,
                file_name VARCHAR(255) DEFAULT NULL,
                uploaded_by INT UNSIGNED DEFAULT NULL,
                uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_case (case_id),
                INDEX idx_si (sales_invoice_id),
                INDEX idx_case_si (case_id, sales_invoice_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='案件端銷項發票憑證（不同步至銷項模組）'
        ");
        echo "✓ 建立 case_sales_invoice_vouchers 表完成\n";
    }

    echo "\n=== 表結構 ===\n";
    $cols = $db->query("SHOW COLUMNS FROM case_sales_invoice_vouchers")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) echo $c['Field'] . " (" . $c['Type'] . ")\n";
} catch (Exception $e) {
    echo "✗ 失敗：" . $e->getMessage() . "\n";
}
