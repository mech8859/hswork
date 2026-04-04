<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
if (!Auth::hasPermission('admin')) { die('需要管理員權限'); }
header('Content-Type: text/plain; charset=utf-8');
$db = Database::getInstance();

echo "=== 人員管理欄位擴充 ===\n\n";

// 1. registered_address 戶籍地址
$col = $db->query("SHOW COLUMNS FROM users LIKE 'registered_address'")->fetch();
if ($col) {
    echo "[已存在] users.registered_address\n";
} else {
    $db->exec("ALTER TABLE users ADD COLUMN registered_address VARCHAR(300) DEFAULT NULL COMMENT '戶籍地址' AFTER address");
    echo "[新增] users.registered_address\n";
}

// 2. avatar 大頭貼路徑
$col2 = $db->query("SHOW COLUMNS FROM users LIKE 'avatar'")->fetch();
if ($col2) {
    echo "[已存在] users.avatar\n";
} else {
    $db->exec("ALTER TABLE users ADD COLUMN avatar VARCHAR(500) DEFAULT NULL COMMENT '大頭貼' AFTER registered_address");
    echo "[新增] users.avatar\n";
}

// 3. user_files 表（身分證、駕照、證照等檔案）
$tbl = $db->query("SHOW TABLES LIKE 'user_files'")->fetch();
if ($tbl) {
    echo "[已存在] user_files 表\n";
} else {
    $db->exec("
        CREATE TABLE user_files (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            file_type VARCHAR(50) NOT NULL COMMENT '檔案類型: id_front,id_back,license_front,license_back,cert,other',
            file_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_size INT UNSIGNED DEFAULT 0,
            uploaded_by INT UNSIGNED NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            INDEX idx_type (user_id, file_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='人員檔案（身分證、駕照、證照等）'
    ");
    echo "[新增] user_files 表\n";
}

echo "\n完成\n";
