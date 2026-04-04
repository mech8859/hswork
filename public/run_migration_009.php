<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!in_array(Session::getUser()['role'], array('boss', 'admin'))) { die('Admin only'); }

$db = Database::getInstance();
$sqls = array(
    "ALTER TABLE `work_logs` ADD COLUMN `payment_collected` TINYINT(1) DEFAULT 0 COMMENT '是否收款' AFTER `next_visit_note`",
    "ALTER TABLE `work_logs` ADD COLUMN `payment_amount` DECIMAL(10,2) DEFAULT NULL COMMENT '收款金額' AFTER `payment_collected`",
    "ALTER TABLE `work_logs` ADD COLUMN `payment_method` VARCHAR(20) DEFAULT NULL COMMENT '收款方式' AFTER `payment_amount`",
    "ALTER TABLE `work_logs` ADD COLUMN `payment_note` VARCHAR(255) DEFAULT NULL COMMENT '收款備註' AFTER `payment_method`",
    "CREATE TABLE IF NOT EXISTS `worklog_photos` (
      `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      `work_log_id` INT UNSIGNED NOT NULL,
      `file_path` VARCHAR(255) NOT NULL COMMENT '檔案路徑',
      `caption` VARCHAR(255) DEFAULT NULL COMMENT '照片說明',
      `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (`work_log_id`) REFERENCES `work_logs`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='施工回報照片'",
    "ALTER TABLE `material_usage` ADD COLUMN `product_id` INT UNSIGNED DEFAULT NULL COMMENT '關聯產品ID' AFTER `work_log_id`",
    "ALTER TABLE `material_usage` ADD COLUMN `unit_cost` DECIMAL(10,2) DEFAULT NULL COMMENT '單價' AFTER `returned_qty`",
);

echo '<h2>Migration 009: 工程回報增強</h2><pre>';
foreach ($sqls as $sql) {
    try {
        $db->exec($sql);
        echo "OK: " . mb_substr($sql, 0, 80) . "...\n";
    } catch (Exception $e) {
        $msg = $e->getMessage();
        if (strpos($msg, 'Duplicate column') !== false || strpos($msg, 'already exists') !== false) {
            echo "SKIP (already exists): " . mb_substr($sql, 0, 80) . "...\n";
        } else {
            echo "ERROR: " . $msg . "\n  SQL: " . mb_substr($sql, 0, 100) . "\n";
        }
    }
}
echo "\nDone!</pre>";
