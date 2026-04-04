<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();

echo '<h2>Migration 030b: 擴充點工人員+外包廠商</h2>';

try {
    // 擴充 dispatch_workers 表
    $cols = array_column($db->query("SHOW COLUMNS FROM dispatch_workers")->fetchAll(), 'Field');
    
    $adds = array();
    if (!in_array('id_number', $cols)) $adds[] = "ADD COLUMN id_number VARCHAR(20) DEFAULT NULL COMMENT '身分證字號' AFTER name";
    if (!in_array('address', $cols)) $adds[] = "ADD COLUMN address VARCHAR(200) DEFAULT NULL COMMENT '聯絡地址' AFTER phone";
    if (!in_array('birth_date', $cols)) $adds[] = "ADD COLUMN birth_date DATE DEFAULT NULL COMMENT '出生年月日' AFTER address";
    if (!in_array('status', $cols)) $adds[] = "ADD COLUMN status VARCHAR(20) DEFAULT 'primary' COMMENT '優先/備用' AFTER specialty";
    if (!in_array('emergency_contact', $cols)) $adds[] = "ADD COLUMN emergency_contact VARCHAR(50) DEFAULT NULL COMMENT '緊急聯絡人' AFTER daily_rate";
    if (!in_array('emergency_phone', $cols)) $adds[] = "ADD COLUMN emergency_phone VARCHAR(30) DEFAULT NULL COMMENT '緊急聯絡人電話' AFTER emergency_contact";
    if (!in_array('vendor_id', $cols)) $adds[] = "ADD COLUMN vendor_id INT UNSIGNED DEFAULT NULL COMMENT '所屬外包廠商ID' AFTER vendor";
    if (!in_array('worker_type', $cols)) $adds[] = "ADD COLUMN worker_type VARCHAR(20) DEFAULT 'dispatch' COMMENT 'dispatch=點工/outsource=外包' AFTER id";
    
    if (!empty($adds)) {
        $sql = "ALTER TABLE dispatch_workers " . implode(', ', $adds);
        $db->exec($sql);
        echo '<p style="color:green">✓ dispatch_workers 表已擴充 ' . count($adds) . ' 個欄位</p>';
    } else {
        echo '<p>dispatch_workers 已是最新</p>';
    }

    // 建立外包廠商表
    $db->exec("CREATE TABLE IF NOT EXISTS outsource_vendors (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL COMMENT '廠商名稱',
        contact_person VARCHAR(50) DEFAULT NULL COMMENT '聯繫人',
        phone VARCHAR(30) DEFAULT NULL,
        address VARCHAR(200) DEFAULT NULL,
        tax_id VARCHAR(20) DEFAULT NULL COMMENT '統一編號',
        note TEXT,
        is_active TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo '<p style="color:green">✓ outsource_vendors 表已建立</p>';

    // 建立點工人員附件表
    $db->exec("CREATE TABLE IF NOT EXISTS dispatch_worker_files (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        worker_id INT UNSIGNED NOT NULL,
        file_type VARCHAR(30) DEFAULT 'other' COMMENT 'id_front/id_back/photo/license/other',
        file_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(500) NOT NULL,
        file_size INT DEFAULT 0,
        uploaded_by INT UNSIGNED DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_worker (worker_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo '<p style="color:green">✓ dispatch_worker_files 表已建立</p>';

} catch (Exception $e) {
    echo '<p style="color:red">✗ ' . htmlspecialchars($e->getMessage()) . '</p>';
}
echo '<br><a href="/staff.php">返回人員管理</a>';
