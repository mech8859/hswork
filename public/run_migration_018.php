<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

$db = Database::getInstance();
$results = array();

// 1. quotations 主表
$sql1 = "CREATE TABLE IF NOT EXISTS `quotations` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `quotation_number` VARCHAR(30) NOT NULL UNIQUE COMMENT '報價單號 Q-YYYYMMDD-NNN',
  `branch_id` INT UNSIGNED NOT NULL,
  `case_id` INT UNSIGNED DEFAULT NULL COMMENT '關聯案件',
  `format` ENUM('simple','project') NOT NULL DEFAULT 'simple' COMMENT '普銷/專案',
  `status` ENUM('draft','sent','accepted','rejected') NOT NULL DEFAULT 'draft',
  `customer_name` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '客戶名稱',
  `contact_person` VARCHAR(50) DEFAULT NULL COMMENT '連絡對象',
  `contact_phone` VARCHAR(30) DEFAULT NULL COMMENT '連絡電話',
  `site_name` VARCHAR(200) DEFAULT NULL COMMENT '案場名稱',
  `site_address` VARCHAR(300) DEFAULT NULL COMMENT '施工地址',
  `invoice_title` VARCHAR(100) DEFAULT NULL COMMENT '發票抬頭',
  `invoice_tax_id` VARCHAR(20) DEFAULT NULL COMMENT '統編',
  `quote_date` DATE NOT NULL COMMENT '報價日期',
  `valid_date` DATE NOT NULL COMMENT '有效日期',
  `subtotal` DECIMAL(12,0) NOT NULL DEFAULT 0 COMMENT '未稅合計',
  `tax_rate` DECIMAL(4,2) NOT NULL DEFAULT 5.00 COMMENT '稅率%',
  `tax_amount` DECIMAL(12,0) NOT NULL DEFAULT 0 COMMENT '營業稅',
  `total_amount` DECIMAL(12,0) NOT NULL DEFAULT 0 COMMENT '含稅合計',
  `total_cost` DECIMAL(12,0) NOT NULL DEFAULT 0 COMMENT '總成本(內部)',
  `profit_amount` DECIMAL(12,0) NOT NULL DEFAULT 0 COMMENT '利潤(內部)',
  `profit_rate` DECIMAL(5,2) NOT NULL DEFAULT 0 COMMENT '利潤率%(內部)',
  `labor_days` DECIMAL(5,1) DEFAULT NULL COMMENT '施工天數',
  `labor_people` INT UNSIGNED DEFAULT NULL COMMENT '施工人數',
  `labor_hours` DECIMAL(5,1) DEFAULT NULL COMMENT '施工時數',
  `labor_cost_total` DECIMAL(12,0) DEFAULT NULL COMMENT '人力成本',
  `payment_terms` TEXT DEFAULT NULL COMMENT '收款條件',
  `notes` TEXT DEFAULT NULL COMMENT '附註說明',
  `sales_id` INT UNSIGNED DEFAULT NULL COMMENT '承辦業務',
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_status` (`status`),
  INDEX `idx_case` (`case_id`),
  INDEX `idx_quote_date` (`quote_date`),
  INDEX `idx_branch` (`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='報價單'";

try {
    $db->exec($sql1);
    $results[] = 'OK: quotations table created';
} catch (Exception $e) {
    $results[] = 'ERR quotations: ' . $e->getMessage();
}

// 2. quotation_sections 區段表
$sql2 = "CREATE TABLE IF NOT EXISTS `quotation_sections` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `quotation_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '區段標題',
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `subtotal` DECIMAL(12,0) NOT NULL DEFAULT 0 COMMENT '區段小計',
  INDEX `idx_quotation` (`quotation_id`),
  FOREIGN KEY (`quotation_id`) REFERENCES `quotations`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='報價單區段'";

try {
    $db->exec($sql2);
    $results[] = 'OK: quotation_sections table created';
} catch (Exception $e) {
    $results[] = 'ERR quotation_sections: ' . $e->getMessage();
}

// 3. quotation_items 項目表
$sql3 = "CREATE TABLE IF NOT EXISTS `quotation_items` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `section_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED DEFAULT NULL COMMENT '關聯產品',
  `item_name` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '品名型號',
  `quantity` DECIMAL(10,2) NOT NULL DEFAULT 1,
  `unit` VARCHAR(20) DEFAULT '式' COMMENT '單位',
  `unit_price` DECIMAL(12,0) NOT NULL DEFAULT 0 COMMENT '單價',
  `amount` DECIMAL(12,0) NOT NULL DEFAULT 0 COMMENT '小計',
  `remark` VARCHAR(200) DEFAULT NULL COMMENT '備註',
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `unit_cost` DECIMAL(12,0) DEFAULT 0 COMMENT '單位成本(內部)',
  `cost_amount` DECIMAL(12,0) DEFAULT 0 COMMENT '成本小計(內部)',
  `material_type` ENUM('product','cable','consumable','labor','other') DEFAULT 'product' COMMENT '材料類型',
  INDEX `idx_section` (`section_id`),
  FOREIGN KEY (`section_id`) REFERENCES `quotation_sections`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='報價單項目'";

try {
    $db->exec($sql3);
    $results[] = 'OK: quotation_items table created';
} catch (Exception $e) {
    $results[] = 'ERR quotation_items: ' . $e->getMessage();
}

echo '<h2>Migration 018 - Quotation System</h2>';
echo '<ul>';
foreach ($results as $r) {
    echo '<li>' . htmlspecialchars($r) . '</li>';
}
echo '</ul>';
echo '<p><a href="/quotations.php">Go to Quotations</a></p>';
