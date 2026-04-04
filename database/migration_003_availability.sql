-- =============================================
-- Migration: 工程師配合度欄位
-- =============================================
ALTER TABLE `users`
  ADD COLUMN `holiday_availability` ENUM('high','medium','low') NOT NULL DEFAULT 'medium' COMMENT '假日施工配合度' AFTER `can_view_all_branches`,
  ADD COLUMN `night_availability` ENUM('high','medium','low') NOT NULL DEFAULT 'medium' COMMENT '夜間施工配合度' AFTER `holiday_availability`;
