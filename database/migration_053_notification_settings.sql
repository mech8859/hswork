-- 通知設定規則表
CREATE TABLE IF NOT EXISTS notification_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    module VARCHAR(50) NOT NULL COMMENT '模組: receipts, cases, repairs, schedule, leaves, worklog, inter_branch, quotations, business_tracking',
    event VARCHAR(50) NOT NULL COMMENT '事件: created, updated, status_changed, assigned',
    condition_field VARCHAR(50) DEFAULT NULL COMMENT '條件欄位: status, sub_status',
    condition_value VARCHAR(100) DEFAULT NULL COMMENT '條件值: 已收款, 已成交',
    notify_type ENUM('role','field') NOT NULL COMMENT 'role=角色, field=記錄欄位(如sales_id)',
    notify_target VARCHAR(50) NOT NULL COMMENT '角色代碼 或 欄位名稱',
    branch_scope ENUM('same','all') NOT NULL DEFAULT 'same' COMMENT 'same=同分公司, all=全部',
    title_template VARCHAR(200) NOT NULL COMMENT '通知標題模板 {field_name}',
    message_template TEXT COMMENT '通知內容模板',
    link_template VARCHAR(500) DEFAULT NULL COMMENT '連結模板',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_module_event (module, event, is_active),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed: 現有硬編碼的通知規則
INSERT INTO notification_settings (module, event, condition_field, condition_value, notify_type, notify_target, branch_scope, title_template, message_template, link_template, is_active, sort_order) VALUES
-- 收款單-新建
('receipts', 'created', NULL, NULL, 'role', 'boss', 'same', '新收款單通知', '客戶：{customer_name}，收款金額：NT${total_amount}，狀態：{status}', '/receipts.php?action=edit&id={id}', 1, 1),
('receipts', 'created', NULL, NULL, 'role', 'sales_manager', 'same', '新收款單通知', '客戶：{customer_name}，收款金額：NT${total_amount}，狀態：{status}', '/receipts.php?action=edit&id={id}', 1, 2),
('receipts', 'created', NULL, NULL, 'role', 'eng_manager', 'same', '新收款單通知', '客戶：{customer_name}，收款金額：NT${total_amount}，狀態：{status}', '/receipts.php?action=edit&id={id}', 1, 3),
('receipts', 'created', NULL, NULL, 'field', 'sales_id', 'same', '新收款單通知', '客戶：{customer_name}，收款金額：NT${total_amount}，狀態：{status}', '/receipts.php?action=edit&id={id}', 1, 4),
-- 收款單-已收款
('receipts', 'status_changed', 'status', '已收款', 'field', 'sales_id', 'same', '收款單已收款通知', '客戶：{customer_name}，收款金額：NT${total_amount}，狀態已更新為：已收款', '/receipts.php?action=edit&id={id}', 1, 5),
('receipts', 'status_changed', 'status', '已收款', 'role', 'sales_assistant', 'same', '收款單已收款通知', '客戶：{customer_name}，收款金額：NT${total_amount}，狀態已更新為：已收款', '/receipts.php?action=edit&id={id}', 1, 6),
-- 業務追蹤-新進件
('business_tracking', 'created', NULL, NULL, 'role', 'sales_manager', 'same', '新進件：{title}', '{actor_name} 新增了一筆進件', '/business_tracking.php?action=edit&id={id}', 1, 1),
('business_tracking', 'created', NULL, NULL, 'role', 'sales_assistant', 'same', '新進件：{title}', '{actor_name} 新增了一筆進件', '/business_tracking.php?action=edit&id={id}', 1, 2),
-- 業務追蹤-成交
('business_tracking', 'status_changed', 'sub_status', '已成交', 'role', 'eng_manager', 'same', '案件成交：{title}', '業務 {actor_name} 回報案件已成交，需安排排工', '/cases.php?action=edit&id={id}', 1, 3),
('business_tracking', 'status_changed', 'sub_status', '已成交', 'role', 'eng_deputy', 'same', '案件成交：{title}', '業務 {actor_name} 回報案件已成交，需安排排工', '/cases.php?action=edit&id={id}', 1, 4),
('business_tracking', 'status_changed', 'sub_status', '跨月成交', 'role', 'eng_manager', 'same', '案件成交：{title}', '業務 {actor_name} 回報案件跨月成交，需安排排工', '/cases.php?action=edit&id={id}', 1, 5),
('business_tracking', 'status_changed', 'sub_status', '跨月成交', 'role', 'eng_deputy', 'same', '案件成交：{title}', '業務 {actor_name} 回報案件跨月成交，需安排排工', '/cases.php?action=edit&id={id}', 1, 6),
('business_tracking', 'status_changed', 'sub_status', '現簽', 'role', 'eng_manager', 'same', '案件成交：{title}', '業務 {actor_name} 回報案件現簽成交，需安排排工', '/cases.php?action=edit&id={id}', 1, 7),
('business_tracking', 'status_changed', 'sub_status', '現簽', 'role', 'eng_deputy', 'same', '案件成交：{title}', '業務 {actor_name} 回報案件現簽成交，需安排排工', '/cases.php?action=edit&id={id}', 1, 8),
('business_tracking', 'status_changed', 'sub_status', '電話報價成交', 'role', 'eng_manager', 'same', '案件成交：{title}', '業務 {actor_name} 回報電話報價成交，需安排排工', '/cases.php?action=edit&id={id}', 1, 9),
('business_tracking', 'status_changed', 'sub_status', '電話報價成交', 'role', 'eng_deputy', 'same', '案件成交：{title}', '業務 {actor_name} 回報電話報價成交，需安排排工', '/cases.php?action=edit&id={id}', 1, 10),
-- 業務追蹤-指派業務
('business_tracking', 'assigned', NULL, NULL, 'field', 'sales_id', 'same', '新案件指派：{title}', '您已被指派為此案件的承辦業務', '/business_tracking.php?action=edit&id={id}', 1, 11),
-- 施工回報-完工
('worklog', 'status_changed', 'status', '完工', 'role', 'eng_manager', 'same', '完工待簽核：{case_title}', '工程人員回報已完工，請確認簽核', '/approvals.php', 1, 1);
