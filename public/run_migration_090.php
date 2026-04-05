<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();

$alters = array(
    "ALTER TABLE case_payments MODIFY COLUMN transaction_type VARCHAR(500) DEFAULT NULL COMMENT '交易內容'",
    "ALTER TABLE case_payments MODIFY COLUMN payment_type VARCHAR(100) DEFAULT NULL COMMENT '帳款類別'",
    "ALTER TABLE case_payments MODIFY COLUMN note TEXT DEFAULT NULL COMMENT '備註'"
);

foreach ($alters as $sql) {
    try {
        $db->exec($sql);
        echo "OK: {$sql}\n";
    } catch (Exception $e) {
        echo "ERR: " . $e->getMessage() . "\n";
    }
}

echo "\n完成，請重新執行 sync_subtables_from_json.php?execute=1\n";
