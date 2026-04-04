<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

$db = Database::getInstance();
$errors = array();
$success = array();

// 1. 建立 customer_contacts 表
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS customer_contacts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            customer_id INT UNSIGNED NOT NULL,
            contact_name VARCHAR(50) NOT NULL,
            phone VARCHAR(30) DEFAULT NULL,
            mobile VARCHAR(30) DEFAULT NULL,
            role VARCHAR(50) DEFAULT NULL COMMENT '角色',
            note TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $success[] = "customer_contacts 表已建立";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'already exists') !== false) {
        $success[] = "customer_contacts 表已存在，跳過";
    } else {
        $errors[] = "建立 customer_contacts 表失敗: " . $e->getMessage();
    }
}

// 2. 將現有 customers.contact_person 遷移到 customer_contacts
try {
    $count = $db->exec("
        INSERT IGNORE INTO customer_contacts (customer_id, contact_name, phone, mobile)
        SELECT id, contact_person, phone, mobile FROM customers
        WHERE contact_person IS NOT NULL AND contact_person != ''
        AND id NOT IN (SELECT DISTINCT customer_id FROM customer_contacts)
    ");
    $success[] = "已遷移 {$count} 筆既有聯絡人到 customer_contacts";
} catch (PDOException $e) {
    $errors[] = "聯絡人資料遷移失敗: " . $e->getMessage();
}

// 3. 確保 quotations 表有 hide_model_on_print 欄位（上次 migration 037）
try {
    $db->exec("ALTER TABLE quotations ADD COLUMN hide_model_on_print TINYINT(1) NOT NULL DEFAULT 0 COMMENT '報價單不顯示型號'");
    $success[] = "quotations 表已新增 hide_model_on_print 欄位";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        $success[] = "hide_model_on_print 欄位已存在，跳過";
    } else {
        $errors[] = "新增 hide_model_on_print 失敗: " . $e->getMessage();
    }
}

// 4. quotation_items 加 model_number 欄位
try {
    $db->exec("ALTER TABLE quotation_items ADD COLUMN model_number VARCHAR(100) DEFAULT NULL COMMENT '型號' AFTER item_name");
    $success[] = "quotation_items 表已新增 model_number 欄位";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        $success[] = "model_number 欄位已存在，跳過";
    } else {
        $errors[] = "新增 model_number 失敗: " . $e->getMessage();
    }
}

// 5. quotations 加 customer_id 欄位（如果不存在）
try {
    $db->exec("ALTER TABLE quotations ADD COLUMN customer_id INT UNSIGNED DEFAULT NULL COMMENT '關聯客戶' AFTER branch_id");
    $success[] = "quotations 表已新增 customer_id 欄位";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        $success[] = "quotations.customer_id 已存在，跳過";
    } else {
        $errors[] = "新增 quotations.customer_id 失敗: " . $e->getMessage();
    }
}

echo "<h2>Migration 038 - 客戶聯絡人 + 報價單型號 + 客戶關聯</h2>";
if ($success) {
    echo "<div style='color:green'><ul>";
    foreach ($success as $s) echo "<li>$s</li>";
    echo "</ul></div>";
}
if ($errors) {
    echo "<div style='color:red'><ul>";
    foreach ($errors as $e) echo "<li>$e</li>";
    echo "</ul></div>";
}
echo "<p><a href='/cases.php'>← 回案件管理</a> | <a href='/customers.php'>客戶管理</a></p>";
