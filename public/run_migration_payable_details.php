<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();

// 進貨明細
$db->exec("CREATE TABLE IF NOT EXISTS payable_purchase_details (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payable_id INT UNSIGNED NOT NULL,
    check_month VARCHAR(7) DEFAULT NULL,
    purchase_date DATE DEFAULT NULL,
    purchase_number VARCHAR(50) DEFAULT NULL,
    branch_name VARCHAR(100) DEFAULT NULL,
    vendor_name VARCHAR(200) DEFAULT NULL,
    amount_untaxed INT DEFAULT 0,
    tax_amount INT DEFAULT 0,
    total_amount INT DEFAULT 0,
    paid_amount INT DEFAULT 0,
    payment_date DATE DEFAULT NULL,
    invoice_date DATE DEFAULT NULL,
    invoice_track VARCHAR(50) DEFAULT NULL,
    invoice_amount INT DEFAULT 0,
    monthly_check VARCHAR(50) DEFAULT NULL,
    note TEXT DEFAULT NULL,
    sort_order INT DEFAULT 0,
    INDEX idx_payable (payable_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "payable_purchase_details table created.\n";

// 進退明細
$db->exec("CREATE TABLE IF NOT EXISTS payable_return_details (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payable_id INT UNSIGNED NOT NULL,
    return_date DATE DEFAULT NULL,
    return_number VARCHAR(50) DEFAULT NULL,
    purchase_number VARCHAR(50) DEFAULT NULL,
    vendor_name VARCHAR(200) DEFAULT NULL,
    doc_status VARCHAR(50) DEFAULT NULL,
    branch_name VARCHAR(100) DEFAULT NULL,
    warehouse_name VARCHAR(100) DEFAULT NULL,
    refund_amount INT DEFAULT 0,
    return_reason VARCHAR(500) DEFAULT NULL,
    accounting_method VARCHAR(100) DEFAULT NULL,
    allowance_doc VARCHAR(100) DEFAULT NULL,
    sort_order INT DEFAULT 0,
    INDEX idx_payable (payable_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "payable_return_details table created.\n";

echo "Done.\n";
