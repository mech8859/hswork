<?php
/**
 * Migration 124: 為 4 張財務表加 is_starred 欄位（星號標註）
 * receivables, receipts, payables, payments_out
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('all')) die('admin only');
$db = Database::getInstance();

echo "<!DOCTYPE html><meta charset=utf-8><pre>";

$tables = array('receivables', 'receipts', 'payables', 'payments_out');

foreach ($tables as $t) {
    try {
        $exists = $db->query("SHOW COLUMNS FROM `{$t}` LIKE 'is_starred'")->fetch();
        if ($exists) {
            echo "✓ {$t}.is_starred 已存在，略過\n";
        } else {
            $db->exec("ALTER TABLE `{$t}` ADD COLUMN `is_starred` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '星號標註' AFTER `id`");
            echo "✓ {$t} 加上 is_starred 欄位\n";
        }
        // 加索引便於快速找出有標註的
        $hasIdx = $db->query("SHOW INDEX FROM `{$t}` WHERE Key_name = 'idx_starred'")->fetch();
        if (!$hasIdx) {
            $db->exec("ALTER TABLE `{$t}` ADD INDEX `idx_starred` (`is_starred`)");
            echo "  ✓ 加 idx_starred 索引\n";
        }
    } catch (Exception $e) {
        echo "✗ {$t} 失敗：" . $e->getMessage() . "\n";
    }
}

echo "\n完成\n";
