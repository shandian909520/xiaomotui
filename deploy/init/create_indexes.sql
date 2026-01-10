-- 数据库索引创建脚本
-- 用于优化查询性能

-- 用户表索引
ALTER TABLE `xmt_user` ADD INDEX `idx_phone` (`phone`);
ALTER TABLE `xmt_user` ADD INDEX `idx_openid` (`openid`);
ALTER TABLE `xmt_user` ADD INDEX `idx_unionid` (`unionid`);
ALTER TABLE `xmt_user` ADD INDEX `idx_status` (`status`);
ALTER TABLE `xmt_user` ADD INDEX `idx_created_at` (`created_at`);

-- 商家表索引
ALTER TABLE `xmt_merchants` ADD INDEX `idx_name` (`name`);
ALTER TABLE `xmt_merchants` ADD INDEX `idx_status` (`status`);
ALTER TABLE `xmt_merchants` ADD INDEX `idx_created_at` (`created_at`);

-- NFC设备表索引
ALTER TABLE `xmt_nfc_devices` ADD INDEX `idx_device_id` (`device_id`);
ALTER TABLE `xmt_nfc_devices` ADD INDEX `idx_merchant_id` (`merchant_id`);
ALTER TABLE `xmt_nfc_devices` ADD INDEX `idx_status` (`status`);
ALTER TABLE `xmt_nfc_devices` ADD INDEX `idx_location` (`location`);

-- 内容任务表索引
ALTER TABLE `xmt_content_tasks` ADD INDEX `idx_merchant_id` (`merchant_id`);
ALTER TABLE `xmt_content_tasks` ADD INDEX `idx_template_id` (`template_id`);
ALTER TABLE `xmt_content_tasks` ADD INDEX `idx_status` (`status`);
ALTER TABLE `xmt_content_tasks` ADD INDEX `idx_created_at` (`created_at`);

-- 内容模板表索引
ALTER TABLE `xmt_content_templates` ADD INDEX `idx_name` (`name`);
ALTER TABLE `xmt_content_templates` ADD INDEX `idx_category` (`category`);
ALTER TABLE `xmt_content_templates` ADD INDEX `idx_status` (`status`);
