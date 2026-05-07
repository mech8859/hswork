<?php
/**
 * Migration 141：考勤系統 — attendance_employees 對照、attendance_records 出勤明細
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireLogin();
Auth::requireRole('boss');
header('Content-Type: text/plain; charset=utf-8');

$db = Database::getInstance();
try {
    // 1) 員工對照表（MOA 姓名/員編 → hswork users.id）
    $exists = $db->query("SHOW TABLES LIKE 'attendance_employees'")->fetch();
    if ($exists) {
        echo "[skip] attendance_employees 已存在\n";
    } else {
        $db->exec("CREATE TABLE attendance_employees (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            moa_name VARCHAR(100) NOT NULL COMMENT 'MOA 員工姓名',
            moa_employee_no VARCHAR(50) DEFAULT NULL COMMENT 'MOA 員編（001/25/...）',
            moa_dept VARCHAR(100) DEFAULT NULL,
            user_id INT UNSIGNED DEFAULT NULL COMMENT 'hswork users.id；NULL=未對應',
            note VARCHAR(255) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_moa_name (moa_name),
            KEY idx_user_id (user_id),
            KEY idx_emp_no (moa_employee_no)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='MOA 考勤員工對照表'");
        echo "OK: attendance_employees 已建立\n";
    }

    // 2) 出勤紀錄（員工 × 日期）
    $exists2 = $db->query("SHOW TABLES LIKE 'attendance_records'")->fetch();
    if ($exists2) {
        echo "[skip] attendance_records 已存在\n";
    } else {
        $db->exec("CREATE TABLE attendance_records (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED DEFAULT NULL COMMENT 'hswork users.id（透過姓名對應，NULL=未對應）',
            moa_name VARCHAR(100) NOT NULL,
            moa_employee_no VARCHAR(50) DEFAULT NULL,
            moa_dept VARCHAR(100) DEFAULT NULL,
            work_date DATE NOT NULL,
            weekday VARCHAR(10) DEFAULT NULL,
            is_abnormal TINYINT(1) NOT NULL DEFAULT 0,
            has_application TINYINT(1) NOT NULL DEFAULT 0,
            expected_minutes INT DEFAULT NULL COMMENT '應出工時（分）',
            actual_minutes INT DEFAULT NULL COMMENT '實出工時（分）',
            extra_minutes INT DEFAULT NULL COMMENT '額外（分）',
            comp_off_minutes INT DEFAULT NULL COMMENT '調休（分）',
            business_trip_minutes INT DEFAULT NULL COMMENT '出差（分）',
            sign_in_time TIME DEFAULT NULL COMMENT '簽到',
            sign_out_time TIME DEFAULT NULL COMMENT '簽退',
            sign_in_status VARCHAR(20) DEFAULT NULL COMMENT '正常/未簽/-',
            sign_out_status VARCHAR(20) DEFAULT NULL,
            late_minutes INT DEFAULT NULL,
            early_leave_minutes INT DEFAULT NULL,
            absent_minutes INT DEFAULT NULL,
            source_file VARCHAR(255) DEFAULT NULL,
            imported_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_name_date (moa_name, work_date),
            KEY idx_user_date (user_id, work_date),
            KEY idx_date (work_date),
            KEY idx_dept_date (moa_dept, work_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='MOA 考勤出勤明細'");
        echo "OK: attendance_records 已建立\n";
    }

    // 3) 匯入紀錄
    $exists3 = $db->query("SHOW TABLES LIKE 'attendance_imports'")->fetch();
    if ($exists3) {
        echo "[skip] attendance_imports 已存在\n";
    } else {
        $db->exec("CREATE TABLE attendance_imports (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            file_name VARCHAR(255) DEFAULT NULL,
            date_from DATE DEFAULT NULL,
            date_to DATE DEFAULT NULL,
            total_rows INT DEFAULT 0,
            inserted_rows INT DEFAULT 0,
            updated_rows INT DEFAULT 0,
            unmatched_count INT DEFAULT 0,
            note VARCHAR(500) DEFAULT NULL,
            imported_by INT UNSIGNED DEFAULT NULL,
            imported_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='MOA 考勤匯入紀錄'");
        echo "OK: attendance_imports 已建立\n";
    }

    AuditLog::log('attendance', 'migration', 0, 'Migration 141: 建立考勤系統 3 個資料表');
    echo "Migration 141 done.\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
