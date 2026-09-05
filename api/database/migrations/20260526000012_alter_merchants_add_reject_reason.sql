-- 商家表添加拒绝原因字段
ALTER TABLE `xmt_merchants` ADD COLUMN `reject_reason` VARCHAR(500) DEFAULT NULL COMMENT '拒绝原因' AFTER `status`;
