-- 報價單新增 updated_by 欄位（追蹤最後編輯人）
ALTER TABLE quotations
    ADD COLUMN updated_by INT UNSIGNED DEFAULT NULL COMMENT '最後編輯人 user.id' AFTER created_by;
