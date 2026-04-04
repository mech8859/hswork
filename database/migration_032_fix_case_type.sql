-- Migration 032: 修正 case_type 欄位
-- 1. ENUM 改 VARCHAR（支援更多案別類型）
-- 2. 從 title 解析正確的案別

-- Step 1: ENUM → VARCHAR
ALTER TABLE cases MODIFY COLUMN case_type VARCHAR(30) NOT NULL DEFAULT 'new_install' COMMENT '案件類型';

-- Step 2: 從 title 後綴解析案別
UPDATE cases SET case_type = 'addition'   WHERE title LIKE '%-老客戶追加' OR title LIKE '%－老客戶追加';
UPDATE cases SET case_type = 'old_repair' WHERE title LIKE '%-舊客戶維修案' OR title LIKE '%－舊客戶維修案';
UPDATE cases SET case_type = 'new_repair' WHERE title LIKE '%-新客戶維修案' OR title LIKE '%－新客戶維修案';
UPDATE cases SET case_type = 'maintenance' WHERE title LIKE '%-維護保養' OR title LIKE '%－維護保養';

-- Step 3: 沒有後綴的、或原本是 other/空的，設為 new_install
UPDATE cases SET case_type = 'new_install' WHERE case_type = '' OR case_type = 'other' OR case_type IS NULL;
