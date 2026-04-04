<?php
/**
 * Migration 069: 排工支援人員標記
 * schedule_engineers 加 is_support 欄位
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');

$db = Database::getInstance();
$results = array();

try {
    $db->exec("ALTER TABLE `schedule_engineers` ADD COLUMN `is_support` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '支援加入' AFTER `is_override`");
    $results[] = '[OK] 已新增 schedule_engineers.is_support 欄位';
} catch (Exception $e) {
    $results[] = '[SKIP] ' . $e->getMessage();
}

header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html><head><title>Migration 069</title></head><body>';
echo '<h2>Migration 069: 排工支援人員</h2><ul>';
foreach ($results as $r) {
    $color = strpos($r, '[OK]') === 0 ? 'green' : 'orange';
    echo '<li style="color:' . $color . '">' . htmlspecialchars($r) . '</li>';
}
echo '</ul><p><a href="/schedule.php">返回行事曆</a></p></body></html>';
