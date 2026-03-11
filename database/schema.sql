-- =============================================
-- 弱電工程排程系統 資料庫結構
-- Database: hswork
-- Charset: utf8mb4
-- =============================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -------------------------------------------
-- 1. branches 據點
-- -------------------------------------------
CREATE TABLE IF NOT EXISTS `branches` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL COMMENT '據點名稱',
  `code` VARCHAR(20) NOT NULL UNIQUE COMMENT '據點代碼',
  `address` VARCHAR(200) DEFAULT NULL,
  `phone` VARCHAR(30) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='據點';

INSERT INTO `branches` (`name`, `code`) VALUES
('潭子分公司', 'TANZI'),
('清水分公司', 'QINGSHUI'),
('員林分公司', 'YUANLIN'),
('東區電子鎖專賣店', 'DONGQU_LOCK'),
('清水電子鎖專賣店', 'QS_LOCK');

-- -------------------------------------------
-- 2. users 使用者
-- -------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `branch_id` INT UNSIGNED NOT NULL COMMENT '所屬據點',
  `username` VARCHAR(50) NOT NULL UNIQUE COMMENT '帳號',
  `password_hash` VARCHAR(255) NOT NULL COMMENT '密碼雜湊',
  `real_name` VARCHAR(50) NOT NULL COMMENT '真實姓名',
  `role` ENUM('boss','sales_manager','eng_manager','eng_deputy','sales','sales_assistant','admin_staff') NOT NULL COMMENT '角色',
  `phone` VARCHAR(30) DEFAULT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `is_engineer` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否為工程師(可排工)',
  `is_mobile` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否使用手機介面',
  `can_view_all_branches` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '可查看全區',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `last_login_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='使用者';

-- -------------------------------------------
-- 3. vehicles 車輛
-- -------------------------------------------
CREATE TABLE IF NOT EXISTS `vehicles` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `branch_id` INT UNSIGNED NOT NULL,
  `vehicle_type` VARCHAR(50) NOT NULL COMMENT '車種',
  `plate_number` VARCHAR(20) NOT NULL UNIQUE COMMENT '車牌',
  `seats` TINYINT UNSIGNED NOT NULL DEFAULT 4 COMMENT '座位數',
  `default_driver_id` INT UNSIGNED DEFAULT NULL COMMENT '固定駕駛',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`),
  FOREIGN KEY (`default_driver_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='車輛';

-- -------------------------------------------
-- 4. skills 技能項目
-- -------------------------------------------
CREATE TABLE IF NOT EXISTS `skills` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE COMMENT '技能名稱',
  `category` VARCHAR(50) DEFAULT NULL COMMENT '分類',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='技能項目';

INSERT INTO `skills` (`name`, `category`) VALUES
('監視系統安裝', '監控'),
('監視系統維修', '監控'),
('門禁系統安裝', '門禁'),
('門禁系統維修', '門禁'),
('電子鎖安裝', '電子鎖'),
('電子鎖維修', '電子鎖'),
('對講機安裝', '對講'),
('對講機維修', '對講'),
('網路佈線', '網路'),
('網路設備設定', '網路'),
('光纖熔接', '網路'),
('電話系統安裝', '電話'),
('電話系統維修', '電話'),
('廣播系統安裝', '廣播'),
('廣播系統維修', '廣播'),
('車牌辨識安裝', '車辨'),
('車牌辨識維修', '車辨'),
('PVC管線施工', '管線'),
('EMT管線施工', '管線'),
('RSG管線施工', '管線'),
('高空作業', '特殊'),
('電銲作業', '特殊');

-- -------------------------------------------
-- 5. user_skills 人員技能熟練度
-- -------------------------------------------
CREATE TABLE IF NOT EXISTS `user_skills` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `skill_id` INT UNSIGNED NOT NULL,
  `proficiency` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '熟練度 1-5星',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_user_skill` (`user_id`, `skill_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`skill_id`) REFERENCES `skills`(`id`) ON DELETE CASCADE,
  CHECK (`proficiency` BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='人員技能熟練度';

-- -------------------------------------------
-- 6. certifications 證照/工作證類型
-- -------------------------------------------
CREATE TABLE IF NOT EXISTS `certifications` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE COMMENT '證照名稱',
  `has_expiry` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否有到期日',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='證照/工作證類型';

-- -------------------------------------------
-- 7. user_certifications 人員證照記錄
-- -------------------------------------------
CREATE TABLE IF NOT EXISTS `user_certifications` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `certification_id` INT UNSIGNED NOT NULL,
  `cert_number` VARCHAR(100) DEFAULT NULL COMMENT '證照號碼',
  `issue_date` DATE DEFAULT NULL COMMENT '發證日期',
  `expiry_date` DATE DEFAULT NULL COMMENT '到期日',
  `attachment_path` VARCHAR(500) DEFAULT NULL COMMENT '證照掃描檔路徑',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`certification_id`) REFERENCES `certifications`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='人員證照記錄';

-- -------------------------------------------
-- 8. engineer_pairs 人員配對表
-- -------------------------------------------
CREATE TABLE IF NOT EXISTS `engineer_pairs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_a_id` INT UNSIGNED NOT NULL,
  `user_b_id` INT UNSIGNED NOT NULL,
  `compatibility` TINYINT UNSIGNED NOT NULL DEFAULT 3 COMMENT '配對度 1-5星',
  `note` TEXT DEFAULT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_pair` (`user_a_id`, `user_b_id`),
  FOREIGN KEY (`user_a_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_b_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CHECK (`compatibility` BETWEEN 1 AND 5),
  CHECK (`user_a_id` < `user_b_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='人員配對表';

-- -------------------------------------------
-- 9. cases 案件主表
-- -------------------------------------------
CREATE TABLE IF NOT EXISTS `cases` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `branch_id` INT UNSIGNED NOT NULL COMMENT '所屬據點',
  `case_number` VARCHAR(30) NOT NULL UNIQUE COMMENT '案件編號',
  `title` VARCHAR(200) NOT NULL COMMENT '案件名稱',
  `case_type` ENUM('new_install','maintenance','repair','inspection','other') NOT NULL DEFAULT 'new_install' COMMENT '案件類型',
  `status` ENUM('pending','ready','scheduled','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending' COMMENT '狀態',
  `difficulty` TINYINT UNSIGNED NOT NULL DEFAULT 3 COMMENT '難易度 1-5',
  `estimated_hours` DECIMAL(5,1) DEFAULT NULL COMMENT '預估工時',
  `total_visits` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '預估施工次數',
  `current_visit` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '目前第幾次施工',
  `max_engineers` TINYINT UNSIGNED NOT NULL DEFAULT 4 COMMENT '最多施工人數',
  `address` VARCHAR(300) DEFAULT NULL COMMENT '施工地址',
  `description` TEXT DEFAULT NULL COMMENT '案件說明',
  `ragic_id` VARCHAR(50) DEFAULT NULL COMMENT 'Ragic案件ID',
  `sales_id` INT UNSIGNED DEFAULT NULL COMMENT '業務負責人',
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`),
  FOREIGN KEY (`sales_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  CHECK (`difficulty` BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='案件主表';

-- -------------------------------------------
-- 10. case_readiness 排工條件驗證
-- -------------------------------------------
CREATE TABLE IF NOT EXISTS `case_readiness` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `case_id` INT UNSIGNED NOT NULL UNIQUE,
  `has_quotation` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '已有報價單',
  `has_site_photos` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '已有現場照片',
  `has_amount_confirmed` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '金額已確認',
  `has_site_info` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '現場資料已備齊',
  `notes` TEXT DEFAULT NULL COMMENT '備註/缺少項目提示',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`case_id`) REFERENCES `cases`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='排工條件驗證';

-- -------------------------------------------
-- 11. case_contacts 案件聯絡人
-- -------------------------------------------
CREATE TABLE IF NOT EXISTS `case_contacts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `case_id` INT UNSIGNED NOT NULL,
  `contact_name` VARCHAR(50) NOT NULL,
  `contact_phone` VARCHAR(30) DEFAULT NULL,
  `contact_role` VARCHAR(50) DEFAULT NULL COMMENT '聯絡人角色(屋主/管委會/工地主任等)',
  `note` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`case_id`) REFERENCES `cases`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='案件聯絡人';

-- -------------------------------------------
-- 12. case_site_conditions 現場環境
-- -------------------------------------------
CREATE TABLE IF NOT EXISTS `case_site_conditions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `case_id` INT UNSIGNED NOT NULL UNIQUE,
  `structure_type` SET('RC','steel_sheet','open_area','construction_site') DEFAULT NULL COMMENT '建築結構：RC/鐵皮/空曠地/建築工地',
  `conduit_type` SET('PVC','EMT','RSG','molding','wall_penetration','aerial','underground') DEFAULT NULL COMMENT '管線需求',
  `floor_count` TINYINT UNSIGNED DEFAULT NULL COMMENT '樓層數',
  `has_elevator` TINYINT(1) DEFAULT 0 COMMENT '有電梯',
  `has_ladder_needed` TINYINT(1) DEFAULT 0 COMMENT '需要梯子',
  `special_requirements` TEXT DEFAULT NULL COMMENT '特殊需求',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`case_id`) REFERENCES `cases`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='現場環境';

-- -------------------------------------------
-- 13. case_required_skills 案件所需技能
-- -------------------------------------------
CREATE TABLE IF NOT EXISTS `case_required_skills` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `case_id` INT UNSIGNED NOT NULL,
  `skill_id` INT UNSIGNED NOT NULL,
  `min_proficiency` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '最低熟練度要求',
  UNIQUE KEY `uk_case_skill` (`case_id`, `skill_id`),
  FOREIGN KEY (`case_id`) REFERENCES `cases`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`skill_id`) REFERENCES `skills`(`id`) ON DELETE CASCADE,
  CHECK (`min_proficiency` BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='案件所需技能';

-- -------------------------------------------
-- 14. case_attachments 附件
-- -------------------------------------------
CREATE TABLE IF NOT EXISTS `case_attachments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `case_id` INT UNSIGNED NOT NULL,
  `file_type` ENUM('quotation','repair_order','site_survey','in_progress','completion') NOT NULL COMMENT '附件類型',
  `file_name` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(500) NOT NULL COMMENT '路徑 /uploads/cases/{case_id}/',
  `file_size` INT UNSIGNED DEFAULT NULL,
  `uploaded_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`case_id`) REFERENCES `cases`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='案件附件';

-- -------------------------------------------
-- 15. payments 收款
-- -------------------------------------------
CREATE TABLE IF NOT EXISTS `payments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `case_id` INT UNSIGNED NOT NULL,
  `payment_type` ENUM('deposit','final_payment') NOT NULL COMMENT '訂金/尾款',
  `payment_method` ENUM('cash','transfer','check') NOT NULL COMMENT '現金/匯款/支票',
  `amount` DECIMAL(10,0) NOT NULL COMMENT '金額',
  `payment_date` DATE DEFAULT NULL,
  `check_number` VARCHAR(50) DEFAULT NULL COMMENT '支票號碼',
  `check_due_date` DATE DEFAULT NULL COMMENT '支票到期日',
  `note` TEXT DEFAULT NULL,
  `recorded_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`case_id`) REFERENCES `cases`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`recorded_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='收款';

-- -------------------------------------------
-- 16. schedules 排工主表
-- -------------------------------------------
CREATE TABLE IF NOT EXISTS `schedules` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `case_id` INT UNSIGNED NOT NULL,
  `schedule_date` DATE NOT NULL COMMENT '施工日期',
  `vehicle_id` INT UNSIGNED DEFAULT NULL COMMENT '派遣車輛',
  `visit_number` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '第幾次施工',
  `status` ENUM('planned','confirmed','in_progress','completed','cancelled') NOT NULL DEFAULT 'planned',
  `note` TEXT DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`case_id`) REFERENCES `cases`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='排工主表';

-- -------------------------------------------
-- 17. schedule_engineers 排工人員
-- -------------------------------------------
CREATE TABLE IF NOT EXISTS `schedule_engineers` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `schedule_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `is_lead` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否為主工程師',
  `is_override` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '工程主管強制加入',
  `override_reason` VARCHAR(200) DEFAULT NULL COMMENT '強制加入原因',
  UNIQUE KEY `uk_schedule_user` (`schedule_id`, `user_id`),
  FOREIGN KEY (`schedule_id`) REFERENCES `schedules`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='排工人員';

-- -------------------------------------------
-- 18. schedule_visit_check 多次施工人員連續性檢查
-- -------------------------------------------
CREATE TABLE IF NOT EXISTS `schedule_visit_check` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `case_id` INT UNSIGNED NOT NULL,
  `visit_number` TINYINT UNSIGNED NOT NULL,
  `previous_visit_number` TINYINT UNSIGNED NOT NULL,
  `is_same_team` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否同一組人',
  `different_members` TEXT DEFAULT NULL COMMENT '不同成員名單(JSON)',
  `notified` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '已通知',
  `acknowledged_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`case_id`) REFERENCES `cases`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`acknowledged_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='多次施工人員連續性檢查';

-- -------------------------------------------
-- 19. work_logs 工程師當日回報
-- -------------------------------------------
CREATE TABLE IF NOT EXISTS `work_logs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `schedule_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `arrival_time` DATETIME DEFAULT NULL COMMENT '到場時間',
  `departure_time` DATETIME DEFAULT NULL COMMENT '離場時間',
  `work_description` TEXT DEFAULT NULL COMMENT '施作說明',
  `issues` TEXT DEFAULT NULL COMMENT '問題/異常',
  `next_visit_needed` TINYINT(1) DEFAULT 0 COMMENT '需再次施工',
  `next_visit_note` TEXT DEFAULT NULL COMMENT '下次施工備註',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`schedule_id`) REFERENCES `schedules`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='工程師當日回報';

-- -------------------------------------------
-- 20. material_usage 材料使用
-- -------------------------------------------
CREATE TABLE IF NOT EXISTS `material_usage` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `work_log_id` INT UNSIGNED NOT NULL,
  `material_type` ENUM('cable','equipment','consumable') NOT NULL COMMENT '線材/器材/耗材',
  `material_name` VARCHAR(100) NOT NULL COMMENT '材料名稱',
  `unit` VARCHAR(20) DEFAULT NULL COMMENT '單位',
  `shipped_qty` DECIMAL(10,2) DEFAULT 0 COMMENT '出貨數量',
  `used_qty` DECIMAL(10,2) DEFAULT 0 COMMENT '使用數量',
  `returned_qty` DECIMAL(10,2) DEFAULT 0 COMMENT '退回數量',
  `note` TEXT DEFAULT NULL,
  FOREIGN KEY (`work_log_id`) REFERENCES `work_logs`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='材料使用';

-- -------------------------------------------
-- 21. inter_branch_support 跨點點工
-- -------------------------------------------
CREATE TABLE IF NOT EXISTS `inter_branch_support` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL COMMENT '支援人員',
  `from_branch_id` INT UNSIGNED NOT NULL COMMENT '原據點',
  `to_branch_id` INT UNSIGNED NOT NULL COMMENT '支援據點',
  `support_date` DATE NOT NULL,
  `charge_type` ENUM('full_day','hourly') NOT NULL COMMENT '整日或小時計',
  `hours` DECIMAL(4,1) DEFAULT NULL COMMENT '時數(小時計時用)',
  `schedule_id` INT UNSIGNED DEFAULT NULL,
  `settled` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否已結算',
  `settle_month` VARCHAR(7) DEFAULT NULL COMMENT '結算月份 YYYY-MM',
  `note` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`from_branch_id`) REFERENCES `branches`(`id`),
  FOREIGN KEY (`to_branch_id`) REFERENCES `branches`(`id`),
  FOREIGN KEY (`schedule_id`) REFERENCES `schedules`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='跨點點工';

-- -------------------------------------------
-- 22. api_keys API金鑰管理
-- -------------------------------------------
CREATE TABLE IF NOT EXISTS `api_keys` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `key_name` VARCHAR(100) NOT NULL COMMENT '金鑰名稱',
  `api_key` VARCHAR(64) NOT NULL UNIQUE COMMENT 'API金鑰',
  `permissions` JSON DEFAULT NULL COMMENT '權限設定(JSON)',
  `rate_limit` INT UNSIGNED DEFAULT 1000 COMMENT '每小時請求上限',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `last_used_at` DATETIME DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='API金鑰管理';

-- -------------------------------------------
-- 23. sync_logs 同步記錄
-- -------------------------------------------
CREATE TABLE IF NOT EXISTS `sync_logs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `source` ENUM('ragic','google_sheet','manual') NOT NULL COMMENT '來源',
  `direction` ENUM('import','export') NOT NULL COMMENT '方向',
  `entity_type` VARCHAR(50) NOT NULL COMMENT '資料類型(cases/payments等)',
  `entity_id` INT UNSIGNED DEFAULT NULL,
  `status` ENUM('success','failed','partial') NOT NULL,
  `records_processed` INT UNSIGNED DEFAULT 0,
  `error_message` TEXT DEFAULT NULL,
  `synced_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`synced_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='同步記錄';

SET FOREIGN_KEY_CHECKS = 1;
