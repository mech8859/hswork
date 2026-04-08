<?php
/**
 * Migration 114: 加班單管理 (overtimes)
 *
 * 建立 overtimes 表 + 預設權限
 */
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();

$sqls = array(
    "CREATE TABLE IF NOT EXISTS overtimes (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL COMMENT '加班人員',
        overtime_date DATE NOT NULL COMMENT '加班日期',
        start_time TIME NOT NULL COMMENT '開始時間',
        end_time TIME NOT NULL COMMENT '結束時間',
        hours DECIMAL(5,2) NOT NULL DEFAULT 0 COMMENT '加班時數（小時，含小數）',
        overtime_type VARCHAR(20) NOT NULL DEFAULT 'weekday' COMMENT '加班類別: weekday/rest_day/holiday/other',
        reason TEXT NOT NULL COMMENT '加班事由',
        note TEXT NULL COMMENT '備註',
        status VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending/approved/rejected',
        approved_by INT UNSIGNED NULL COMMENT '核准/駁回人員',
        approved_at DATETIME NULL,
        reject_reason TEXT NULL COMMENT '駁回原因',
        created_by INT UNSIGNED NULL COMMENT '建立者',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user_date (user_id, overtime_date),
        INDEX idx_status (status),
        INDEX idx_overtime_date (overtime_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='加班單'",
);

foreach ($sqls as $sql) {
    try {
        $db->exec($sql);
        echo "OK: " . substr($sql, 0, 60) . "...\n";
    } catch (PDOException $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
    }
}

// 將 overtime.* 權限注入 system_roles 預設值（如 system_roles 表存在）
echo "\n--- 注入預設角色權限 ---\n";
try {
    $rolePerms = array(
        'boss'              => array('overtime.manage'),
        'vice_president'    => array('overtime.manage'),
        'manager'           => array('overtime.manage'),
        'assistant_manager' => array('overtime.manage'),
        'sales_manager'     => array('overtime.view', 'overtime.own'),
        'eng_manager'       => array('overtime.manage'),
        'eng_deputy'        => array('overtime.view', 'overtime.own'),
        'engineer'          => array('overtime.own'),
        'sales'             => array('overtime.own'),
        'sales_assistant'   => array('overtime.own'),
        'admin_staff'       => array('overtime.own'),
        'accountant'        => array('overtime.own'),
        'warehouse'         => array('overtime.own'),
        'purchaser'         => array('overtime.own'),
        'hq'                => array('overtime.own'),
    );

    foreach ($rolePerms as $roleKey => $newPerms) {
        $stmt = $db->prepare("SELECT default_permissions FROM system_roles WHERE role_key = ? AND is_active = 1 LIMIT 1");
        $stmt->execute(array($roleKey));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            echo "SKIP role $roleKey (not found in system_roles)\n";
            continue;
        }
        $perms = !empty($row['default_permissions']) ? json_decode($row['default_permissions'], true) : array();
        if (!is_array($perms)) $perms = array();
        $changed = false;
        foreach ($newPerms as $p) {
            if (!in_array($p, $perms)) {
                $perms[] = $p;
                $changed = true;
            }
        }
        if ($changed) {
            $db->prepare("UPDATE system_roles SET default_permissions = ? WHERE role_key = ?")
               ->execute(array(json_encode(array_values(array_unique($perms))), $roleKey));
            echo "OK $roleKey: 加入 " . implode(',', $newPerms) . "\n";
        } else {
            echo "SKIP $roleKey (已有權限)\n";
        }
    }
} catch (PDOException $e) {
    echo "ERROR roles: " . $e->getMessage() . "\n";
}

echo "\n完成。請到「人事行政 > 加班單管理」查看。\n";
