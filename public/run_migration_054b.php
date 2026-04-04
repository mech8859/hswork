<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }
header('Content-Type: text/plain; charset=utf-8');
$db = Database::getInstance();
try {
    $db->exec("ALTER TABLE case_billing_items ADD COLUMN customer_billable_info TEXT AFTER customer_billable");
    echo "OK: customer_billable_info column added\n";
} catch (Exception $e) {
    echo "INFO: " . $e->getMessage() . "\n";
}
echo "Done.\n";
