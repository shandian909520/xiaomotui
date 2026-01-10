-- 添加团购支持
-- 1. 扩展 trigger_mode 枚举，添加 GROUP_BUY 选项
ALTER TABLE `xmt_nfc_devices`
MODIFY COLUMN `trigger_mode` enum('VIDEO','COUPON','WIFI','CONTACT','MENU','GROUP_BUY') DEFAULT 'VIDEO' COMMENT '触发模式';

-- 2. 添加 group_buy_config 字段用于存储团购配置
ALTER TABLE `xmt_nfc_devices`
ADD COLUMN `group_buy_config` json DEFAULT NULL COMMENT '团购配置' AFTER `redirect_url`;

-- 3. 创建团购跳转记录表
CREATE TABLE IF NOT EXISTS `xmt_group_buy_redirects` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '记录ID',
  `device_id` int(11) unsigned NOT NULL COMMENT '设备ID',
  `merchant_id` int(11) unsigned NOT NULL COMMENT '商家ID',
  `user_id` int(11) unsigned DEFAULT NULL COMMENT '用户ID',
  `platform` varchar(20) NOT NULL COMMENT '平台类型 MEITUAN/DOUYIN/ELEME/CUSTOM',
  `deal_id` varchar(50) DEFAULT NULL COMMENT '团购ID',
  `redirect_url` varchar(500) NOT NULL COMMENT '完整跳转链接',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'IP地址',
  `user_agent` varchar(255) DEFAULT NULL COMMENT '用户代理',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `device_id` (`device_id`),
  KEY `merchant_id` (`merchant_id`),
  KEY `user_id` (`user_id`),
  KEY `platform` (`platform`),
  KEY `create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='团购跳转记录表';