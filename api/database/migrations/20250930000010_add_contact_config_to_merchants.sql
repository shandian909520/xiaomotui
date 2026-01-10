-- 添加联系方式配置字段到商家表
ALTER TABLE `xmt_merchants`
ADD COLUMN `contact_config` json DEFAULT NULL COMMENT '联系方式配置JSON' AFTER `business_hours`,
ADD COLUMN `wechat_id` varchar(50) DEFAULT NULL COMMENT '微信号' AFTER `phone`,
ADD COLUMN `weibo_id` varchar(50) DEFAULT NULL COMMENT '微博号' AFTER `wechat_id`,
ADD COLUMN `douyin_id` varchar(50) DEFAULT NULL COMMENT '抖音号' AFTER `weibo_id`;

-- 创建索引
ALTER TABLE `xmt_merchants` ADD INDEX `idx_wechat_id` (`wechat_id`);