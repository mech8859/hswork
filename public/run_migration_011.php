<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!in_array(Session::getUser()['role'], array('boss', 'admin'))) { die('Admin only'); }

$db = Database::getInstance();
$sqls = array(
    "ALTER TABLE `case_attachments` MODIFY COLUMN `file_type` VARCHAR(50) NOT NULL DEFAULT 'other' COMMENT '附件類型'",
);

echo '<h2>Migration 011: case_attachments file_type 改為 VARCHAR</h2><pre>';
foreach ($sqls as $sql) {
    try {
        $db->exec($sql);
        echo "OK: " . mb_substr($sql, 0, 80) . "...\n";
    } catch (Exception $e) {
        $msg = $e->getMessage();
        if (strpos($msg, 'Duplicate column') !== false || strpos($msg, 'already exists') !== false) {
            echo "SKIP: " . mb_substr($sql, 0, 80) . "...\n";
        } else {
            echo "ERROR: " . $msg . "\n  SQL: " . mb_substr($sql, 0, 100) . "\n";
        }
    }
}
echo "\nDone!</pre>";
