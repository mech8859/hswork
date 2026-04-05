<?php
/**
 * Migration 093: 通知規則 notify_target 欄位加大（支援多目標逗號分隔）
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();

try {
    $db->exec("ALTER TABLE notification_settings MODIFY COLUMN notify_target VARCHAR(500) NOT NULL COMMENT '通知目標（可逗號分隔多個）'");
    echo "OK: notify_target 欄位已加大為 VARCHAR(500)\n";
} catch (Exception $e) {
    echo "ERR: " . $e->getMessage() . "\n";
}
