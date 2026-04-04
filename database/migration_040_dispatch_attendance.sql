-- Migration 040: dispatch_attendance (點工出勤管理)
CREATE TABLE IF NOT EXISTS dispatch_attendance (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dispatch_worker_id INT UNSIGNED NOT NULL COMMENT '點工人員ID',
    schedule_id INT UNSIGNED NULL COMMENT '關聯排工ID（可為空，手動新增時無排工）',
    attendance_date DATE NOT NULL COMMENT '出勤日期',
    branch_id INT UNSIGNED NOT NULL COMMENT '出勤分公司',
    charge_type ENUM('full_day','half_day') NOT NULL DEFAULT 'full_day' COMMENT '計費方式',
    daily_rate INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '日薪',
    amount INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '實際金額（半日=日薪/2）',
    status ENUM('present','absent','cancelled') NOT NULL DEFAULT 'present' COMMENT '出勤狀態',
    settled TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否已結算',
    settle_month VARCHAR(7) NULL COMMENT '結算月份 (YYYY-MM)',
    note TEXT NULL COMMENT '備註',
    recorded_by INT UNSIGNED NULL COMMENT '登錄人',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_worker_date (dispatch_worker_id, attendance_date),
    INDEX idx_date (attendance_date),
    INDEX idx_branch (branch_id),
    INDEX idx_settled (settled),
    INDEX idx_schedule (schedule_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='點工出勤記錄';
