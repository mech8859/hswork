-- migration_136_case_close_lock.sql
-- 案件已完工結案後上鎖機制
-- 加 5 欄：is_locked, locked_by, locked_at, unlocked_at, unlocked_by
-- 觸發點：tryAutoCloseCase + approvals L3 通過 → 自動 is_locked=1
-- 解鎖：boss / vice_president 可在編輯頁解鎖；存檔後自動重鎖；30 分鐘懶式 timeout 重鎖

ALTER TABLE cases
    ADD COLUMN is_locked TINYINT(1) NOT NULL DEFAULT 0 COMMENT '結案上鎖標記',
    ADD COLUMN locked_by INT UNSIGNED NULL DEFAULT NULL COMMENT '上鎖人 user_id',
    ADD COLUMN locked_at DATETIME NULL DEFAULT NULL COMMENT '上鎖時間',
    ADD COLUMN unlocked_at DATETIME NULL DEFAULT NULL COMMENT '解鎖時間（30 分鐘 timeout 用）',
    ADD COLUMN unlocked_by INT UNSIGNED NULL DEFAULT NULL COMMENT '解鎖人 user_id';

-- 加索引方便查詢「目前處於解鎖狀態」的案件
CREATE INDEX idx_cases_locked ON cases (is_locked, status);
