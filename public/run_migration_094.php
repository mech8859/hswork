<?php
/**
 * Migration 094: Add case_date to customers table
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();

try {
    $cols = $db->query("SHOW COLUMNS FROM customers LIKE 'case_date'")->fetchAll();
    if (empty($cols)) {
        $db->exec("ALTER TABLE customers ADD COLUMN case_date DATE DEFAULT NULL COMMENT '進件日期' AFTER case_number");
        echo "Added case_date column to customers table.\n";
    } else {
        echo "case_date column already exists.\n";
    }
    echo "Migration 094 completed.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
