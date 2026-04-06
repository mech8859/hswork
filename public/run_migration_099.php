<?php
/**
 * Migration 099: Add survey_time to cases table
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();
$exists = $db->query("SHOW COLUMNS FROM cases LIKE 'survey_time'")->fetchAll();
if (empty($exists)) {
    $db->exec("ALTER TABLE cases ADD COLUMN survey_time TIME DEFAULT NULL COMMENT '場勘時間' AFTER survey_date");
    echo "Added survey_time\n";
} else {
    echo "survey_time already exists\n";
}
echo "Migration 099 completed.\n";
