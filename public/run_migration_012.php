<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();

$db = Database::getInstance();
$results = array();

// 車輛基本資料
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS vehicles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            plate_number VARCHAR(20) NOT NULL COMMENT '車牌號碼',
            vehicle_type ENUM('truck','van','business') NOT NULL DEFAULT 'van' COMMENT '車輛類型',
            brand VARCHAR(50) DEFAULT NULL COMMENT '品牌',
            model VARCHAR(100) DEFAULT NULL COMMENT '型號',
            year INT DEFAULT NULL COMMENT '出廠年份',
            color VARCHAR(20) DEFAULT NULL COMMENT '顏色',
            custodian_id INT DEFAULT NULL COMMENT '保管人ID',
            branch_id INT DEFAULT NULL COMMENT '所屬分公司',
            last_maintenance_date DATE DEFAULT NULL COMMENT '上次保養日期',
            maintenance_mileage INT DEFAULT NULL COMMENT '保養里程',
            next_maintenance_date DATE DEFAULT NULL COMMENT '下次保養日期',
            current_mileage INT DEFAULT NULL COMMENT '目前里程',
            note TEXT DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_branch (branch_id),
            INDEX idx_custodian (custodian_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='車輛資料'
    ");
    $results[] = 'vehicles table OK';
} catch (Exception $e) {
    $results[] = 'vehicles: ' . $e->getMessage();
}

// 車輛保養紀錄
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS vehicle_maintenance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            vehicle_id INT NOT NULL,
            maintenance_date DATE NOT NULL COMMENT '保養日期',
            maintenance_type VARCHAR(50) NOT NULL DEFAULT 'regular' COMMENT '保養類型',
            mileage INT DEFAULT NULL COMMENT '保養時里程',
            cost DECIMAL(10,0) DEFAULT 0 COMMENT '費用',
            description TEXT DEFAULT NULL COMMENT '保養內容',
            next_date DATE DEFAULT NULL COMMENT '下次保養日期',
            next_mileage INT DEFAULT NULL COMMENT '下次保養里程',
            created_by INT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_vehicle (vehicle_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='車輛保養紀錄'
    ");
    $results[] = 'vehicle_maintenance table OK';
} catch (Exception $e) {
    $results[] = 'vehicle_maintenance: ' . $e->getMessage();
}

// 車輛工具配備
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS vehicle_tools (
            id INT AUTO_INCREMENT PRIMARY KEY,
            vehicle_id INT NOT NULL,
            tool_name VARCHAR(100) NOT NULL COMMENT '工具名稱',
            quantity INT NOT NULL DEFAULT 1 COMMENT '數量',
            note VARCHAR(255) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_vehicle (vehicle_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='車輛工具配備'
    ");
    $results[] = 'vehicle_tools table OK';
} catch (Exception $e) {
    $results[] = 'vehicle_tools: ' . $e->getMessage();
}

// 車輛檔案
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS vehicle_files (
            id INT AUTO_INCREMENT PRIMARY KEY,
            vehicle_id INT NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_type VARCHAR(50) DEFAULT 'other',
            uploaded_by INT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_vehicle (vehicle_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='車輛檔案'
    ");
    $results[] = 'vehicle_files table OK';
} catch (Exception $e) {
    $results[] = 'vehicle_files: ' . $e->getMessage();
}

echo '<h2>Migration 012 - Vehicle Management</h2>';
echo '<ul>';
foreach ($results as $r) {
    echo '<li>' . htmlspecialchars($r) . '</li>';
}
echo '</ul>';
echo '<p><a href="/vehicles.php">Go to Vehicle Management</a></p>';
