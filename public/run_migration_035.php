<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();

echo '<h2>Migration 035: 操作日誌與線上狀態</h2>';

try {
    // 操作日誌表
    $db->exec("CREATE TABLE IF NOT EXISTS audit_logs (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        user_name VARCHAR(50) DEFAULT NULL,
        module VARCHAR(50) NOT NULL COMMENT '模組名稱',
        action VARCHAR(20) NOT NULL COMMENT 'create/update/delete/login/logout',
        target_id INT UNSIGNED DEFAULT NULL COMMENT '操作對象ID',
        target_title VARCHAR(200) DEFAULT NULL COMMENT '操作對象標題',
        changes TEXT COMMENT 'JSON格式變更內容',
        ip_address VARCHAR(45) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user (user_id),
        INDEX idx_module (module),
        INDEX idx_action (action),
        INDEX idx_created (created_at),
        INDEX idx_target (module, target_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo '<p style="color:green">✓ audit_logs 表已建立</p>';

    // 用戶最後活動時間
    $cols = array_column($db->query("SHOW COLUMNS FROM users")->fetchAll(), 'Field');
    $adds = array();
    if (!in_array('last_active_at', $cols)) $adds[] = "ADD COLUMN last_active_at DATETIME DEFAULT NULL COMMENT '最後活動時間'";
    if (!in_array('last_active_page', $cols)) $adds[] = "ADD COLUMN last_active_page VARCHAR(100) DEFAULT NULL COMMENT '最後活動頁面'";
    if (!in_array('last_login_ip', $cols)) $adds[] = "ADD COLUMN last_login_ip VARCHAR(45) DEFAULT NULL COMMENT '最後登入IP'";
    
    if (!empty($adds)) {
        $db->exec("ALTER TABLE users " . implode(', ', $adds));
        echo '<p style="color:green">✓ users 表已擴充 ' . count($adds) . ' 個欄位</p>';
    }

} catch (Exception $e) {
    echo '<p style="color:red">✗ ' . htmlspecialchars($e->getMessage()) . '</p>';
}
echo '<br><a href="/staff.php">返回人員管理</a>';
