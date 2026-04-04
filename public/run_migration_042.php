<?php
/**
 * Migration 042: 動態角色管理
 * 1. ALTER users.role 從 ENUM 改為 VARCHAR(50)
 * 2. 建立 system_roles 資料表
 * 3. 匯入目前所有角色為初始資料
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');

$db = Database::getInstance();

$results = array();

// Step 1: ALTER users.role from ENUM to VARCHAR(50)
try {
    $db->exec("ALTER TABLE `users` MODIFY COLUMN `role` VARCHAR(50) NOT NULL DEFAULT 'sales' COMMENT '角色'");
    $results[] = '[OK] users.role 已從 ENUM 改為 VARCHAR(50)';
} catch (Exception $e) {
    $results[] = '[SKIP] users.role: ' . $e->getMessage();
}

// Step 2: Create system_roles table
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS `system_roles` (
          `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          `role_key` VARCHAR(50) NOT NULL UNIQUE COMMENT '角色代碼(英文)',
          `role_label` VARCHAR(100) NOT NULL COMMENT '角色名稱(中文)',
          `is_system` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '系統內建角色(不可刪除)',
          `sort_order` INT NOT NULL DEFAULT 0,
          `is_active` TINYINT(1) NOT NULL DEFAULT 1,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX idx_active_sort (`is_active`, `sort_order`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='系統角色定義'
    ");
    $results[] = '[OK] system_roles 資料表已建立';
} catch (Exception $e) {
    $results[] = '[SKIP] system_roles: ' . $e->getMessage();
}

// Step 3: Insert all current roles as initial data
$defaultRoles = array(
    array('boss',            '系統管理者',   1, 1),
    array('manager',         '管理者',       1, 2),
    array('sales_manager',   '業務主管',     1, 3),
    array('eng_manager',     '工程主管',     1, 4),
    array('eng_deputy',      '工程副主管',   1, 5),
    array('engineer',        '工程人員',     1, 6),
    array('sales',           '業務',         1, 7),
    array('sales_assistant', '業務助理',     1, 8),
    array('admin_staff',     '行政人員',     1, 9),
);

$insertCount = 0;
$skipCount = 0;
foreach ($defaultRoles as $role) {
    try {
        $stmt = $db->prepare(
            'INSERT INTO system_roles (role_key, role_label, is_system, sort_order) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute($role);
        $insertCount++;
    } catch (Exception $e) {
        // Duplicate key = already exists
        $skipCount++;
    }
}
$results[] = "[OK] 角色資料: 新增 {$insertCount} 筆, 跳過 {$skipCount} 筆(已存在)";

// Output results
header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html><head><title>Migration 042</title></head><body>';
echo '<h2>Migration 042: 動態角色管理</h2>';
echo '<ul>';
foreach ($results as $r) {
    $color = strpos($r, '[OK]') === 0 ? 'green' : (strpos($r, '[SKIP]') === 0 ? 'orange' : 'red');
    echo '<li style="color:' . $color . '">' . htmlspecialchars($r) . '</li>';
}
echo '</ul>';
echo '<p><a href="/dropdown_options.php?tab=roles">前往角色管理</a></p>';
echo '</body></html>';
