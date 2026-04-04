<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();
echo "<h2>Migration 032: 聯絡地址 + 場勘日期</h2>";
echo "<style>body{font-family:sans-serif;padding:20px;} .ok{color:green;} .warn{color:orange;}</style>";
$sqls = array(
    "ALTER TABLE cases ADD COLUMN contact_address VARCHAR(300) DEFAULT NULL COMMENT '聯絡地址' AFTER address",
    "ALTER TABLE cases ADD COLUMN survey_date DATE DEFAULT NULL COMMENT '場勘日期' AFTER deal_date",
);
foreach ($sqls as $sql) {
    try {
        $db->exec($sql);
        echo "<p class='ok'>OK: " . htmlspecialchars(substr($sql, 0, 80)) . "...</p>";
    } catch (Exception $e) {
        $msg = $e->getMessage();
        if (strpos($msg, 'Duplicate column') !== false) {
            echo "<p class='warn'>已存在: " . htmlspecialchars(substr($sql, 0, 80)) . "</p>";
        } else {
            echo "<p style='color:red'>ERROR: {$msg}</p>";
        }
    }
}
echo "<p><a href='/business_tracking.php'>返回業務追蹤</a></p>";
