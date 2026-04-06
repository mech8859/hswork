<?php
/**
 * Migration 097: Add source_worklog_id to case_work_logs for sync tracking
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();

$cols = array(
    'source_worklog_id' => "INT UNSIGNED DEFAULT NULL COMMENT '來源 work_logs.id（工程回報同步用）' AFTER photo_paths",
    'arrival_time'      => "DATETIME DEFAULT NULL COMMENT '到場時間' AFTER work_content",
    'departure_time'    => "DATETIME DEFAULT NULL COMMENT '離場時間' AFTER arrival_time",
);

$added = 0;
foreach ($cols as $col => $def) {
    $exists = $db->query("SHOW COLUMNS FROM case_work_logs LIKE '{$col}'")->fetchAll();
    if (empty($exists)) {
        try {
            $db->exec("ALTER TABLE case_work_logs ADD COLUMN {$col} {$def}");
            echo "Added {$col}\n";
            $added++;
        } catch (Exception $e) {
            echo "Error adding {$col}: " . $e->getMessage() . "\n";
        }
    } else {
        echo "{$col} already exists\n";
    }
}

// 加索引
try {
    $db->exec("CREATE INDEX idx_cwl_source ON case_work_logs (source_worklog_id)");
    echo "Added index idx_cwl_source\n";
} catch (Exception $e) {
    echo "Index: " . $e->getMessage() . "\n";
}

echo "\nMigration 097 completed. Added {$added} columns.\n";
