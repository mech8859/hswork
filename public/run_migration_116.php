<?php
/**
 * Migration 116: 案件金額異動紀錄表
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');
$db = Database::getInstance();
header('Content-Type: text/plain; charset=utf-8');

$db->exec("
    CREATE TABLE IF NOT EXISTS case_amount_changes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        case_id INT UNSIGNED NOT NULL,
        field_name VARCHAR(50) NOT NULL COMMENT 'deal_amount/total_amount/tax_amount/total_collected/balance_amount',
        old_value DECIMAL(12,0) DEFAULT 0,
        new_value DECIMAL(12,0) DEFAULT 0,
        change_source VARCHAR(50) DEFAULT 'manual_edit' COMMENT 'manual_edit/payment_add/payment_edit/payment_delete',
        changed_by INT UNSIGNED,
        changed_by_name VARCHAR(50),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_case (case_id),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='案件金額異動紀錄'
");

echo "Migration 116 done: case_amount_changes table created.\n";
