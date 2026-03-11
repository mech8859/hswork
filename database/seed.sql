-- =============================================
-- 初始資料 - 預設管理員帳號
-- 密碼: admin123 (部署後請立即修改)
-- =============================================

-- 預設管理員 (密碼 admin123 的 bcrypt hash)
INSERT INTO `users` (`branch_id`, `username`, `password_hash`, `real_name`, `role`, `is_engineer`, `is_mobile`, `can_view_all_branches`)
VALUES (1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '系統管理員', 'boss', 0, 0, 1);

-- 預設證照類型
INSERT INTO `certifications` (`name`, `has_expiry`) VALUES
('低壓室內配線技術士', 0),
('低壓工業配線技術士', 0),
('甲種電匠', 0),
('乙種電匠', 0),
('高空作業人員安全訓練', 1),
('營造業勞工安全衛生教育訓練', 1),
('特定化學物質作業主管', 1),
('堆高機操作訓練', 1),
('施工架組配作業人員', 1),
('建築工地主任', 0);
