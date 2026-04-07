<?php
/**
 * Migration 106: bank_transactions 加 transaction_number 欄位 + 自動編號設定
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');
$db = Database::getInstance();
header('Content-Type: text/plain; charset=utf-8');

// 1. 加欄位
$sqls = array(
    "ALTER TABLE bank_transactions ADD COLUMN transaction_number VARCHAR(30) DEFAULT NULL COMMENT '銀行交易編號' AFTER id",
    "ALTER TABLE bank_transactions ADD INDEX idx_transaction_number (transaction_number)",
);
foreach ($sqls as $sql) {
    try { $db->exec($sql); echo "OK: $sql\n"; }
    catch (PDOException $e) {
        echo (strpos($e->getMessage(), 'Duplicate') !== false ? "SKIP\n" : "ERROR: " . $e->getMessage() . "\n");
    }
}

// 2. 加入 number_sequences 設定
try {
    $stmt = $db->prepare("SELECT id FROM number_sequences WHERE module = 'bank_transactions'");
    $stmt->execute();
    if (!$stmt->fetch()) {
        $db->prepare("INSERT INTO number_sequences (module, module_label, prefix, date_format, separator, seq_digits, last_sequence) VALUES (?, ?, ?, ?, ?, ?, 0)")
           ->execute(array('bank_transactions', '銀行交易編號', 'BT', 'Y', '-', 6));
        echo "OK: number_sequences 加入 bank_transactions (BT-Y-6 digits)\n";
    } else {
        echo "SKIP: number_sequences bank_transactions already exists\n";
    }
} catch (Exception $e) {
    echo "ERR: " . $e->getMessage() . "\n";
}

// 3. 把 bank_statements 的 module_label 改名
try {
    $r = $db->prepare("UPDATE number_sequences SET module_label = ? WHERE module = ?")->execute(array('銀行交易編號', 'bank_statements'));
    echo "OK: bank_statements label 改為「銀行交易編號」\n";
} catch (Exception $e) {
    echo "ERR: " . $e->getMessage() . "\n";
}

echo "\nDone.\n";
