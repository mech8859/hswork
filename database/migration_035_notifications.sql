-- Migration 035: 通知系統
-- 2026-03-27

CREATE TABLE IF NOT EXISTS notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    type VARCHAR(50) NOT NULL COMMENT 'case_new, case_assigned, case_deal, completion_pending, accounting_pending',
    title VARCHAR(200) NOT NULL,
    message TEXT,
    link VARCHAR(500) DEFAULT NULL,
    related_type VARCHAR(50) DEFAULT NULL COMMENT 'case, quotation, approval',
    related_id INT UNSIGNED DEFAULT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    read_at DATETIME DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_unread (user_id, is_read, created_at),
    INDEX idx_type (type),
    INDEX idx_related (related_type, related_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 案件表加 call_attempts 欄位（電話不通計次用）
ALTER TABLE cases ADD COLUMN call_attempts TEXT DEFAULT NULL COMMENT 'JSON [{date, note}]';
