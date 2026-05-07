<?php
/**
 * Migration 142：MOA 雲考勤 API 同步設定
 * - attendance_settings：存 cookie 與同步狀態
 * - attendance_employees：補 moa_user_id 欄位
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();
try {
    $exists = $db->query("SHOW TABLES LIKE 'attendance_settings'")->fetch();
    if ($exists) {
        echo "[skip] attendance_settings 已存在\n";
    } else {
        $db->exec("CREATE TABLE attendance_settings (
            id INT UNSIGNED PRIMARY KEY,
            moa_company_id INT NOT NULL DEFAULT 4545 COMMENT 'MOA 企業號（畫面右上）',
            moa_org_id INT NOT NULL DEFAULT 200021 COMMENT 'MOA URL 路徑中的 org id',
            moa_cookie TEXT DEFAULT NULL COMMENT 'MOA 登入後的 Cookie 字串',
            cookie_set_at DATETIME DEFAULT NULL,
            cookie_set_by INT UNSIGNED DEFAULT NULL,
            last_sync_at DATETIME DEFAULT NULL,
            last_sync_status VARCHAR(50) DEFAULT NULL COMMENT 'success/failed',
            last_sync_message TEXT DEFAULT NULL,
            last_sync_employees INT DEFAULT 0,
            last_sync_records INT DEFAULT 0,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='MOA 雲考勤同步設定（單列）'");
        $db->exec("INSERT INTO attendance_settings (id, moa_company_id, moa_org_id) VALUES (1, 4545, 200021)");
        echo "OK: attendance_settings 已建立 (id=1 預設)\n";
    }

    // attendance_employees 補 moa_user_id
    $col = $db->query("SHOW COLUMNS FROM attendance_employees LIKE 'moa_user_id'")->fetch();
    if ($col) {
        echo "[skip] attendance_employees.moa_user_id 已存在\n";
    } else {
        $db->exec("ALTER TABLE attendance_employees ADD COLUMN moa_user_id INT DEFAULT NULL COMMENT 'MOA 內部 userId' AFTER moa_employee_no");
        $db->exec("ALTER TABLE attendance_employees ADD INDEX idx_moa_user_id (moa_user_id)");
        echo "OK: attendance_employees.moa_user_id 已新增\n";
    }

    // attendance_records 加上同步來源標記
    $col2 = $db->query("SHOW COLUMNS FROM attendance_records LIKE 'sync_source'")->fetch();
    if ($col2) {
        echo "[skip] attendance_records.sync_source 已存在\n";
    } else {
        $db->exec("ALTER TABLE attendance_records ADD COLUMN sync_source VARCHAR(20) DEFAULT 'excel' COMMENT 'excel/api' AFTER source_file");
        echo "OK: attendance_records.sync_source 已新增\n";
    }

    AuditLog::log('attendance', 'migration', 0, 'Migration 142: MOA 同步設定表 + moa_user_id');
    echo "Migration 142 done.\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
