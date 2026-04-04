<?php
/**
 * Migration 068: 廠商產品對照表
 * 1. 建立 vendor_products 表
 * 2. 從已確認進貨單歷史自動產生初始對照資料
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');

$db = Database::getInstance();
$results = array();

// Step 1: 建表
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS `vendor_products` (
          `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          `vendor_id` INT UNSIGNED DEFAULT NULL COMMENT '廠商ID',
          `product_id` INT UNSIGNED DEFAULT NULL COMMENT '對應系統產品ID',
          `vendor_model` VARCHAR(200) NOT NULL COMMENT '廠商型號/編號',
          `vendor_name` VARCHAR(500) DEFAULT NULL COMMENT '廠商品名',
          `vendor_price` DECIMAL(12,2) DEFAULT NULL COMMENT '廠商報價',
          `last_purchase_price` DECIMAL(12,2) DEFAULT NULL COMMENT '最近進價',
          `last_purchase_date` DATE DEFAULT NULL COMMENT '最近進貨日',
          `note` TEXT DEFAULT NULL,
          `is_active` TINYINT(1) NOT NULL DEFAULT 1,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          UNIQUE KEY `uk_vendor_model` (`vendor_id`, `vendor_model`),
          INDEX `idx_product_id` (`product_id`),
          INDEX `idx_vendor_model` (`vendor_model`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='廠商產品對照表'
    ");
    $results[] = '[OK] vendor_products 資料表已建立';
} catch (Exception $e) {
    $results[] = '[SKIP] vendor_products: ' . $e->getMessage();
}

// Step 2: 從已確認進貨單歷史產生初始對照
try {
    // 取得已確認進貨單的品項，按廠商+型號分組，取最近一筆
    $stmt = $db->query("
        SELECT
            gr.vendor_id,
            gri.model AS vendor_model,
            gri.product_name AS vendor_name,
            gri.product_id,
            gri.unit_price AS last_purchase_price,
            gr.gr_date AS last_purchase_date
        FROM goods_receipt_items gri
        JOIN goods_receipts gr ON gri.goods_receipt_id = gr.id
        WHERE gr.status = '已確認'
          AND gri.model IS NOT NULL
          AND gri.model != ''
          AND gr.vendor_id IS NOT NULL
        ORDER BY gr.gr_date DESC
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 按 vendor_id + vendor_model 分組，只取第一筆（最新）
    $seen = array();
    $insertCount = 0;
    $skipCount = 0;

    $ins = $db->prepare("
        INSERT IGNORE INTO vendor_products
        (vendor_id, product_id, vendor_model, vendor_name, last_purchase_price, last_purchase_date)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    foreach ($rows as $row) {
        $key = $row['vendor_id'] . '|' . $row['vendor_model'];
        if (isset($seen[$key])) {
            $skipCount++;
            continue;
        }
        $seen[$key] = true;

        $ins->execute(array(
            $row['vendor_id'],
            $row['product_id'] ?: null,
            $row['vendor_model'],
            $row['vendor_name'] ?: null,
            $row['last_purchase_price'] ?: null,
            $row['last_purchase_date'] ?: null
        ));

        if ($ins->rowCount() > 0) {
            $insertCount++;
        } else {
            $skipCount++;
        }
    }

    $results[] = "[OK] 從進貨歷史產生對照：新增 {$insertCount} 筆，跳過 {$skipCount} 筆(重複)";
} catch (Exception $e) {
    $results[] = '[ERR] 初始化對照資料: ' . $e->getMessage();
}

// Output
header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html><head><title>Migration 068</title></head><body>';
echo '<h2>Migration 068: 廠商產品對照表</h2><ul>';
foreach ($results as $r) {
    $color = strpos($r, '[OK]') === 0 ? 'green' : (strpos($r, '[SKIP]') === 0 ? 'orange' : 'red');
    echo '<li style="color:' . $color . '">' . htmlspecialchars($r) . '</li>';
}
echo '</ul>';
echo '<p><a href="/vendor_products.php">前往廠商產品對照</a></p>';
echo '</body></html>';
