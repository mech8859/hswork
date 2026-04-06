<?php
/**
 * Migration 095: Add upload_no, remittance_code, counterparty_account, memo to bank_transactions
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();
$added = 0;

$cols = array(
    'upload_no'           => "VARCHAR(50) DEFAULT NULL COMMENT '上傳編號' AFTER remark",
    'remittance_code'     => "VARCHAR(50) DEFAULT NULL COMMENT '存匯代號' AFTER upload_no",
    'counterparty_account'=> "VARCHAR(50) DEFAULT NULL COMMENT '對方帳號' AFTER remittance_code",
    'memo'                => "VARCHAR(255) DEFAULT NULL COMMENT '註記' AFTER counterparty_account",
);

foreach ($cols as $col => $def) {
    $exists = $db->query("SHOW COLUMNS FROM bank_transactions LIKE '{$col}'")->fetchAll();
    if (empty($exists)) {
        $db->exec("ALTER TABLE bank_transactions ADD COLUMN {$col} {$def}");
        echo "Added {$col}\n";
        $added++;
    } else {
        echo "{$col} already exists\n";
    }
}

echo "\nMigration 095 completed. Added {$added} columns.\n";
