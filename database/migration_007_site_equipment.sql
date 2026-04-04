-- 新增現場環境設備欄位
ALTER TABLE `case_site_conditions`
  ADD COLUMN `ladder_size` VARCHAR(10) DEFAULT NULL COMMENT '拉梯尺寸(米)' AFTER `has_ladder_needed`,
  ADD COLUMN `high_ceiling_height` VARCHAR(20) DEFAULT NULL COMMENT '挑高場所高度(米)' AFTER `ladder_size`,
  ADD COLUMN `needs_scissor_lift` TINYINT(1) DEFAULT 0 COMMENT '需要自走車' AFTER `high_ceiling_height`;
