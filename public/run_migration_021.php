<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();

echo "<h3>Migration 021 - Customer Files + Fields</h3>";

// 1. customer_files table
try {
$db->exec("CREATE TABLE IF NOT EXISTS customer_files (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL,
    file_type ENUM('quotation','contract','photo','invoice','other') NOT NULL DEFAULT 'other',
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT UNSIGNED DEFAULT 0,
    note VARCHAR(200) DEFAULT NULL,
    uploaded_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cf_cust (customer_id),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "OK: customer_files table<br>";
} catch (Exception $e) { echo "ERR: customer_files " . $e->getMessage() . "<br>"; }

// 2. customers add line_id
try {
    $db->query("SELECT line_id FROM customers LIMIT 1");
    echo "SKIP: customers.line_id exists<br>";
} catch (Exception $e) {
    try {
        $db->exec("ALTER TABLE customers ADD COLUMN line_id VARCHAR(50) DEFAULT NULL");
        echo "OK: customers.line_id<br>";
    } catch (Exception $e2) { echo "ERR: " . $e2->getMessage() . "<br>"; }
}

echo "<br>DONE<br>";
echo '<a href="/customers.php">customers</a>';
