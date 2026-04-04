-- 點工人員資料表
CREATE TABLE IF NOT EXISTS dispatch_workers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL,
  phone VARCHAR(30) DEFAULT NULL,
  specialty VARCHAR(100) DEFAULT NULL COMMENT '專長',
  daily_rate DECIMAL(8,0) DEFAULT 0 COMMENT '日薪',
  vendor VARCHAR(100) DEFAULT NULL COMMENT '所屬廠商(外包)',
  is_active TINYINT(1) DEFAULT 1,
  note TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 排工-點工人員關聯表
CREATE TABLE IF NOT EXISTS schedule_dispatch_workers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  schedule_id INT UNSIGNED NOT NULL,
  dispatch_worker_id INT UNSIGNED NOT NULL,
  note VARCHAR(200) DEFAULT NULL,
  UNIQUE KEY uk_schedule_worker (schedule_id, dispatch_worker_id),
  INDEX idx_worker (dispatch_worker_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
