<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }
header('Content-Type: text/html; charset=utf-8');
echo '<pre>';
$db = Database::getInstance();
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS case_billing_items (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            case_id INT UNSIGNED NOT NULL,
            seq_no INT UNSIGNED NOT NULL DEFAULT 1,
            payment_category VARCHAR(30) NOT NULL,
            amount_untaxed DECIMAL(12,0) DEFAULT NULL,
            tax_amount DECIMAL(12,0) DEFAULT NULL,
            total_amount DECIMAL(12,0) NOT NULL DEFAULT 0,
            tax_included TINYINT(1) NOT NULL DEFAULT 0,
            note TEXT,
            customer_paid TINYINT(1) NOT NULL DEFAULT 0,
            customer_paid_info TEXT,
            customer_billable TINYINT(1) NOT NULL DEFAULT 0,
            is_billed TINYINT(1) NOT NULL DEFAULT 0,
            billed_info TEXT,
            invoice_number VARCHAR(50) DEFAULT NULL,
            created_by INT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_case_id (case_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "OK: case_billing_items table created/exists\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
echo '</pre>';
echo '<p><strong>Done.</strong> <a href="/cases.php">Go to cases</a></p>';
