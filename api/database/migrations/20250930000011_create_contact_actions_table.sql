-- 联系行为记录表
-- 删除旧表(如果存在)
DROP TABLE IF EXISTS `xmt_contact_actions`;

-- 创建联系行为记录表
CREATE TABLE `xmt_contact_actions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '记录ID',
  `device_id` int(11) unsigned NOT NULL COMMENT '设备ID',
  `merchant_id` int(11) unsigned NOT NULL COMMENT '商家ID',
  `user_id` int(11) unsigned DEFAULT NULL COMMENT '用户ID（游客为NULL）',
  `contact_type` varchar(20) NOT NULL COMMENT '联系方式类型 wework/wechat/phone',
  `trigger_time` datetime NOT NULL COMMENT '触发时间',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'IP地址',
  `user_agent` varchar(255) DEFAULT NULL COMMENT '用户代理',
  `extra_data` json DEFAULT NULL COMMENT '额外数据JSON',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_device_id` (`device_id`),
  KEY `idx_merchant_id` (`merchant_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_contact_type` (`contact_type`),
  KEY `idx_trigger_time` (`trigger_time`),
  KEY `idx_create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='联系行为记录表';

-- 创建复合索引优化查询
CREATE INDEX `idx_merchant_time` ON `xmt_contact_actions` (`merchant_id`, `trigger_time`);
CREATE INDEX `idx_device_time` ON `xmt_contact_actions` (`device_id`, `trigger_time`);