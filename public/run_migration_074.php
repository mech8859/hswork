<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();
$sqls = array(
    "ALTER TABLE cases ADD COLUMN customer_email VARCHAR(200) DEFAULT NULL COMMENT '客戶 Email' AFTER contact_line_id",
);
foreach ($sqls as $sql) {
    try {
        $db->exec($sql);
        echo "[OK] $sql\n";
    } catch (Exception $e) {
        echo "[SKIP] " . $e->getMessage() . "\n";
    }
}
echo "\nDone!\n";
