<?php
/**
 * Migration 053b: 建立 product_price_history（不用 FK）
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

if (!Auth::hasPermission('admin')) {
    die('需要管理員權限');
}

$db = Database::getInstance();

try {
    $tables = $db->query("SHOW TABLES LIKE 'product_price_history'")->fetchAll();
    if (empty($tables)) {
        $db->exec("
            CREATE TABLE product_price_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                product_id INT NOT NULL,
                date_from DATE NOT NULL,
                date_to DATE DEFAULT NULL,
                cost INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_product_id (product_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        echo '<h2 style="color:green">OK: product_price_history 資料表已建立</h2>';
    } else {
        echo '<h2 style="color:gray">SKIP: product_price_history 已存在</h2>';
    }
} catch (Exception $e) {
    echo '<h2 style="color:red">ERROR: ' . htmlspecialchars($e->getMessage()) . '</h2>';
}

echo '<p><a href="/products.php">返回產品目錄</a></p>';
