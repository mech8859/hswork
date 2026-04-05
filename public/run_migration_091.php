<?php
/**
 * Migration 091: 案件預計使用線材與配件
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (Auth::user()['role'] !== 'boss') { die('Permission denied'); }
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();

$sql = "CREATE TABLE IF NOT EXISTS case_material_estimates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED DEFAULT NULL COMMENT '關聯產品ID',
    material_name VARCHAR(255) NOT NULL COMMENT '品名',
    model_number VARCHAR(100) DEFAULT NULL COMMENT '型號',
    unit VARCHAR(20) DEFAULT NULL COMMENT '單位',
    estimated_qty DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT '預估數量',
    note TEXT DEFAULT NULL COMMENT '備註',
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '排序',
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_case (case_id),
    KEY idx_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='案件預估線材與配件'";

try {
    $db->exec($sql);
    echo "OK: case_material_estimates 表已建立\n";
} catch (Exception $e) {
    echo "ERR: " . $e->getMessage() . "\n";
}
