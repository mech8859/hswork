<?php
/**
 * Migration 098: Add start_time, end_time to leaves table
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();
$added = 0;

$cols = array(
    'start_time' => "TIME DEFAULT NULL COMMENT '開始時間（非整天假）' AFTER start_date",
    'end_time'   => "TIME DEFAULT NULL COMMENT '結束時間（非整天假）' AFTER end_date",
);

foreach ($cols as $col => $def) {
    $exists = $db->query("SHOW COLUMNS FROM leaves LIKE '{$col}'")->fetchAll();
    if (empty($exists)) {
        $db->exec("ALTER TABLE leaves ADD COLUMN {$col} {$def}");
        echo "Added {$col}\n";
        $added++;
    } else {
        echo "{$col} already exists\n";
    }
}

echo "\nMigration 098 completed. Added {$added} columns.\n";
