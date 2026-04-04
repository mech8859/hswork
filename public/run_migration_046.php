<?php
/**
 * Migration 046 - 人員證照文件上傳
 * 建立 staff_documents 與 staff_doc_types 資料表
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();
$results = array();

// 1. 建立 staff_doc_types
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS staff_doc_types (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            type_key VARCHAR(50) NOT NULL UNIQUE,
            type_label VARCHAR(100) NOT NULL,
            sort_order INT DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='人員文件類型'
    ");
    $results[] = "staff_doc_types 資料表已建立";
} catch (PDOException $e) {
    $results[] = "staff_doc_types 錯誤: " . $e->getMessage();
}

// 2. 建立 staff_documents
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS staff_documents (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            doc_type VARCHAR(50) NOT NULL COMMENT '文件類型代碼',
            doc_label VARCHAR(100) NOT NULL COMMENT '文件名稱',
            file_path VARCHAR(500) DEFAULT NULL,
            file_name VARCHAR(255) DEFAULT NULL,
            sort_order INT DEFAULT 0,
            uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='人員證照文件'
    ");
    $results[] = "staff_documents 資料表已建立";
} catch (PDOException $e) {
    $results[] = "staff_documents 錯誤: " . $e->getMessage();
}

// 3. 插入預設文件類型
$defaultTypes = array(
    array('id_front',       '身分證-正面', 1),
    array('id_back',        '身分證-反面', 2),
    array('license_front',  '汽車駕照-正面', 3),
    array('license_back',   '汽車駕照-反面', 4),
    array('photo',          '大頭貼', 5),
    array('bank_account',   '銀行帳號', 6),
    array('safety_officer', '甲種營造業職業安全衛生業務主管', 10),
    array('safety_education', '一般安全衛生教育結業證書-營造業', 11),
    array('aerial_worker',  '高空作業車操作人員', 12),
    array('first_aid',      '急救人員', 13),
    array('telecom_c',      '通訊技術士-丙', 20),
    array('telecom_b',      '通訊技術士-乙', 21),
    array('network_c',      '網路架設-丙', 22),
    array('network_b',      '網路架設-乙', 23),
    array('indoor_c',       '室內配線-丙', 24),
    array('indoor_b',       '室內配線-乙', 25),
    array('cad_c',          '繪圖設計-丙', 26),
    array('cad_b',          '繪圖設計-乙', 27),
    array('web_c',          '網頁設計-丙', 28),
    array('web_b',          '網頁設計-乙', 29),
    array('unifi_cert',     'UNIFI證書', 30),
    array('fluke_cert',     'FLUKE證書', 31),
);

$insertStmt = $db->prepare("INSERT IGNORE INTO staff_doc_types (type_key, type_label, sort_order) VALUES (?, ?, ?)");
$inserted = 0;
foreach ($defaultTypes as $dt) {
    try {
        $insertStmt->execute($dt);
        if ($insertStmt->rowCount() > 0) $inserted++;
    } catch (PDOException $e) {
        $results[] = "插入 {$dt[0]} 錯誤: " . $e->getMessage();
    }
}
$results[] = "已插入 {$inserted} 筆預設文件類型";

echo "<h2>Migration 046 - 人員證照文件上傳</h2><ul>";
foreach ($results as $r) echo "<li style='color:green'>" . htmlspecialchars($r) . "</li>";
echo "</ul><p><a href='/staff.php'>← 人員管理</a></p>";
