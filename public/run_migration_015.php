<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

$db = Database::getInstance();
$results = array();

// 排工日設定表（管理者控制每日是否可排工、最大組數/人數）
$sql = "CREATE TABLE IF NOT EXISTS schedule_day_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_date DATE NOT NULL,
    is_open TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否開放排工 0=不可排 1=可排',
    max_teams INT DEFAULT NULL COMMENT '最大組數(NULL=不限)',
    max_engineers INT DEFAULT NULL COMMENT '最大可排人數(NULL=不限)',
    note VARCHAR(255) DEFAULT NULL COMMENT '備註',
    updated_by INT DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_date (setting_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

try {
    $db->exec($sql);
    $results[] = 'OK: schedule_day_settings table created';
} catch (Exception $e) {
    $results[] = 'ERR: ' . $e->getMessage();
}

echo '<h2>Migration 015 - Schedule Day Settings</h2>';
echo '<ul>';
foreach ($results as $r) {
    echo '<li>' . htmlspecialchars($r) . '</li>';
}
echo '</ul>';
echo '<p><a href="/schedule.php">Go to Schedule</a></p>';
