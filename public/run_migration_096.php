<?php
/**
 * Migration 096: Add start_time, end_time, designated_time to schedules table
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();
$added = 0;

$cols = array(
    'start_time'      => "TIME DEFAULT NULL COMMENT '預計開始時間' AFTER schedule_date",
    'end_time'        => "TIME DEFAULT NULL COMMENT '預計結束時間' AFTER start_time",
    'designated_time' => "TIME DEFAULT NULL COMMENT '指定時間（從案件帶入）' AFTER end_time",
);

foreach ($cols as $col => $def) {
    $exists = $db->query("SHOW COLUMNS FROM schedules LIKE '{$col}'")->fetchAll();
    if (empty($exists)) {
        $db->exec("ALTER TABLE schedules ADD COLUMN {$col} {$def}");
        echo "Added {$col}\n";
        $added++;
    } else {
        echo "{$col} already exists\n";
    }
}

echo "\nMigration 096 completed. Added {$added} columns.\n";
