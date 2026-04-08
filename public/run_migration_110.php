<?php
/**
 * Migration 110: 五星評價統計模組
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');
$db = Database::getInstance();
header('Content-Type: text/plain; charset=utf-8');

$sqls = array(
    "CREATE TABLE IF NOT EXISTS five_star_reviews (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        review_number VARCHAR(20) NOT NULL DEFAULT '' COMMENT '五星評價編號 (2026-001)',
        review_date DATE DEFAULT NULL COMMENT '日期',
        reason TEXT DEFAULT NULL COMMENT '不符獎金原因',
        photo_path VARCHAR(500) DEFAULT NULL COMMENT '照片路徑',
        group_photo_engineer_ids TEXT DEFAULT NULL COMMENT '施工人員合影 (JSON 陣列)',
        customer_name VARCHAR(100) DEFAULT NULL COMMENT '客戶名稱',
        original_customer_name VARCHAR(100) DEFAULT NULL COMMENT '原客戶名稱(後刪)',
        google_reviewer_name VARCHAR(100) DEFAULT NULL COMMENT 'Google評價人名稱',
        engineer_ids TEXT DEFAULT NULL COMMENT '施工人員 (JSON 陣列)',
        original_engineer_names VARCHAR(255) DEFAULT NULL COMMENT '原施工人員(後刪)',
        branch_id INT UNSIGNED DEFAULT NULL COMMENT '所屬分公司',
        bonus_payment_date DATE DEFAULT NULL COMMENT '獎金發放日期',
        created_by INT UNSIGNED DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_review_number (review_number),
        KEY idx_review_date (review_date),
        KEY idx_branch_id (branch_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='五星評價統計'",
);
foreach ($sqls as $sql) {
    try {
        $db->exec($sql);
        echo "OK: CREATE TABLE five_star_reviews\n";
    } catch (PDOException $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
    }
}

// 加入 number_sequences 設定（2026-001 格式）
try {
    $stmt = $db->prepare("SELECT id FROM number_sequences WHERE module = 'five_star_reviews'");
    $stmt->execute();
    if (!$stmt->fetch()) {
        $db->prepare("INSERT INTO number_sequences (module, module_label, prefix, date_format, `separator`, seq_digits, last_sequence) VALUES (?, ?, ?, ?, ?, ?, 0)")
           ->execute(array('five_star_reviews', '五星評價編號', '', 'Y', '-', 3));
        echo "OK: number_sequences 加入 five_star_reviews (Y-3 digits, 例 2026-001)\n";
    } else {
        echo "SKIP: number_sequences five_star_reviews already exists\n";
    }
} catch (Exception $e) {
    echo "ERR: " . $e->getMessage() . "\n";
}

echo "\nDone.\n";
