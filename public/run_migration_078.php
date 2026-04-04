<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();
$sqls = array(
    // 工程師等級
    "ALTER TABLE users ADD COLUMN engineer_level ENUM('leader','senior','regular','probation') DEFAULT NULL COMMENT '工程師等級: 組長/資深/一般/試用' AFTER is_engineer",
    // 可帶隊
    "ALTER TABLE users ADD COLUMN can_lead TINYINT(1) NOT NULL DEFAULT 0 COMMENT '可帶隊(可任主工程師)' AFTER engineer_level",
    // 查修優先
    "ALTER TABLE users ADD COLUMN repair_priority TINYINT(1) NOT NULL DEFAULT 0 COMMENT '查修優先人員' AFTER can_lead",
    // 師傅 ID
    "ALTER TABLE users ADD COLUMN mentor_id INT DEFAULT NULL COMMENT '師傅ID(師徒制)' AFTER repair_priority",
    // 師徒開始日期
    "ALTER TABLE users ADD COLUMN mentor_start_date DATE DEFAULT NULL COMMENT '師徒開始日期' AFTER mentor_id",
    // 索引
    "ALTER TABLE users ADD INDEX idx_mentor_id (mentor_id)",
    "ALTER TABLE users ADD INDEX idx_engineer_level (engineer_level)",
    // 回填：現有工程師設為 regular
    "UPDATE users SET engineer_level = 'regular' WHERE is_engineer = 1 AND engineer_level IS NULL AND (employment_status IN ('active','') OR employment_status IS NULL)",
    // 回填：試用期工程師設為 probation
    "UPDATE users SET engineer_level = 'probation' WHERE is_engineer = 1 AND employment_status = 'probation'",
);
foreach ($sqls as $sql) {
    try {
        $db->exec($sql);
        echo "[OK] " . substr($sql, 0, 80) . "...\n";
    } catch (Exception $e) {
        echo "[SKIP] " . $e->getMessage() . "\n";
    }
}
echo "\nDone!\n";
