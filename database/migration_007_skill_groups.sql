-- =============================================
-- Migration 007: 技能分類重新規劃
-- 新增 skill_group 欄位（系統安裝技能/通用能力/設備安裝技能）
-- 更新分類名稱、新增細分技能
-- =============================================

-- 1. 新增 skill_group 欄位
ALTER TABLE `skills` ADD COLUMN `skill_group` VARCHAR(50) DEFAULT '系統安裝技能' AFTER `category`;
ALTER TABLE `skills` ADD COLUMN `sort_order` INT UNSIGNED DEFAULT 0 AFTER `skill_group`;

-- 2. 設定現有技能的 skill_group
UPDATE `skills` SET `skill_group` = '系統安裝技能' WHERE `category` IN ('監控','門禁','電子鎖','對講','網路','電話','廣播','車辨');
UPDATE `skills` SET `skill_group` = '設備安裝技能', `category` = '管路施工' WHERE `category` = '管線';
UPDATE `skills` SET `skill_group` = '設備安裝技能' WHERE `category` = '特殊';

-- 3. 新增 對講 細分技能
INSERT INTO `skills` (`name`, `category`, `skill_group`) VALUES
('Hometake對講系統安裝查修', '對講', '系統安裝技能'),
('BBhome對講系統安裝查修', '對講', '系統安裝技能'),
('大華對講系統安裝查修', '對講', '系統安裝技能');

-- 4. 新增 監控 細分技能
INSERT INTO `skills` (`name`, `category`, `skill_group`) VALUES
('類比系統安裝維修', '監控', '系統安裝技能'),
('大華數位系統安裝維修', '監控', '系統安裝技能'),
('海康數位系統安裝維修', '監控', '系統安裝技能'),
('宇視數位系統安裝維修', '監控', '系統安裝技能'),
('TPLink系統維護安裝維修', '監控', '系統安裝技能');

-- 5. 新增 管路施工 細分技能
INSERT INTO `skills` (`name`, `category`, `skill_group`) VALUES
('壓條施工', '管路施工', '設備安裝技能'),
('RC穿牆', '管路施工', '設備安裝技能'),
('鋁製線槽配置', '管路施工', '設備安裝技能'),
('吊管工程', '管路施工', '設備安裝技能'),
('切地埋管', '管路施工', '設備安裝技能'),
('架空作業', '管路施工', '設備安裝技能');

-- 6. 新增 通用能力
INSERT INTO `skills` (`name`, `category`, `skill_group`) VALUES
('應對溝通協調能力', '通用能力', '通用能力'),
('施工速度', '通用能力', '通用能力'),
('查修能力', '通用能力', '通用能力'),
('領導統籌能力', '通用能力', '通用能力');

-- 7. 更新 管線 分類名稱為 管路施工
UPDATE `skills` SET `name` = 'PVC管路施工' WHERE `name` = 'PVC管線施工';
UPDATE `skills` SET `name` = 'EMT管路施工' WHERE `name` = 'EMT管線施工';
UPDATE `skills` SET `name` = 'RSG管路施工' WHERE `name` = 'RSG管線施工';
