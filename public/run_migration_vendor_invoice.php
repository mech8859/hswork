<?php
/**
 * 廠商請款單 AI 辨識 — 建立 3 張新表
 * 1. vendor_invoices       請款單主表
 * 2. vendor_invoice_items  明細
 * 3. product_price_history 產品價格變動史
 *
 * 全為新表，不影響現有任何資料。
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('all')) die('admin only');
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();

function ensureTable($db, $name, $sql) {
    try {
        $db->exec($sql);
        echo "✓ Created table: {$name}\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo "→ Table {$name} already exists, skipped\n";
        } else {
            echo "✗ Error on {$name}: " . $e->getMessage() . "\n";
        }
    }
}

// ============================================================
// 1. vendor_invoices  請款單主表
// ============================================================
ensureTable($db, 'vendor_invoices', "
CREATE TABLE vendor_invoices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    status ENUM('pending','recognized','confirmed') NOT NULL DEFAULT 'pending' COMMENT 'pending=待辨識; recognized=已辨識待確認; confirmed=已確認',
    file_path VARCHAR(255) NOT NULL COMMENT '掃描檔相對路徑（uploads/vendor_invoices/...）',
    file_name VARCHAR(255) NOT NULL COMMENT '原始檔名',
    file_size INT UNSIGNED DEFAULT NULL COMMENT '檔案大小（bytes）',
    file_pages INT UNSIGNED DEFAULT NULL COMMENT 'PDF 頁數',
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    uploaded_by INT UNSIGNED DEFAULT NULL,
    recognized_at DATETIME DEFAULT NULL,
    recognized_data JSON DEFAULT NULL COMMENT 'AI 辨識原始 JSON（保留以便日後重跑）',
    vendor_id INT UNSIGNED DEFAULT NULL,
    invoice_date DATE DEFAULT NULL,
    invoice_number VARCHAR(50) DEFAULT NULL,
    total_amount DECIMAL(12,2) DEFAULT NULL,
    confirmed_at DATETIME DEFAULT NULL,
    confirmed_by INT UNSIGNED DEFAULT NULL,
    note TEXT DEFAULT NULL,
    linked_purchase_invoice_id INT UNSIGNED DEFAULT NULL COMMENT '預留：未來連動進項發票',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_status (status),
    KEY idx_vendor (vendor_id),
    KEY idx_invoice_date (invoice_date),
    KEY idx_uploaded_at (uploaded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='廠商請款單收件匣'
");

// ============================================================
// 2. vendor_invoice_items  請款單明細
// ============================================================
ensureTable($db, 'vendor_invoice_items', "
CREATE TABLE vendor_invoice_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vendor_invoice_id BIGINT UNSIGNED NOT NULL,
    line_no INT UNSIGNED NOT NULL DEFAULT 1,
    -- AI 辨識原始（不可改）
    ai_model VARCHAR(100) DEFAULT NULL,
    ai_name VARCHAR(255) DEFAULT NULL,
    ai_qty DECIMAL(12,3) DEFAULT NULL,
    ai_unit VARCHAR(20) DEFAULT NULL,
    ai_unit_price DECIMAL(12,2) DEFAULT NULL,
    ai_amount DECIMAL(12,2) DEFAULT NULL,
    -- 人工確認後（覆寫用）
    matched_product_id INT UNSIGNED DEFAULT NULL,
    final_model VARCHAR(100) DEFAULT NULL,
    final_name VARCHAR(255) DEFAULT NULL,
    final_qty DECIMAL(12,3) DEFAULT NULL,
    final_unit VARCHAR(20) DEFAULT NULL,
    final_unit_price DECIMAL(12,2) DEFAULT NULL,
    final_amount DECIMAL(12,2) DEFAULT NULL,
    note TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_invoice (vendor_invoice_id),
    KEY idx_product (matched_product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='廠商請款單明細'
");

// ============================================================
// 3. product_price_history  產品價格變動史
// ============================================================
ensureTable($db, 'product_price_history', "
CREATE TABLE product_price_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    vendor_id INT UNSIGNED DEFAULT NULL,
    old_price DECIMAL(12,2) DEFAULT NULL,
    new_price DECIMAL(12,2) NOT NULL,
    change_pct DECIMAL(7,2) DEFAULT NULL COMMENT '變動百分比（顯示用）',
    source_type ENUM('vendor_invoice','manual','goods_receipt','import') NOT NULL DEFAULT 'manual',
    source_id BIGINT UNSIGNED DEFAULT NULL COMMENT '對應 source_type 的 id（如 vendor_invoices.id）',
    note TEXT DEFAULT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_by INT UNSIGNED DEFAULT NULL,
    KEY idx_product (product_id),
    KEY idx_vendor (vendor_id),
    KEY idx_source (source_type, source_id),
    KEY idx_updated_at (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='產品價格變動史（永久保留）'
");

echo "\nDone.\n";
echo "下一步：上傳此檔到 /www/ 後執行 https://hswork.com.tw/run_migration_vendor_invoice.php\n";
