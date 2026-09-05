-- 打卡点评行为埋点表(评价灵感草稿 + 跳转平台手动发布)
CREATE TABLE IF NOT EXISTS `xmt_review_actions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `device_id` int(11) unsigned NOT NULL COMMENT '设备ID',
  `merchant_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '商家ID',
  `platform` varchar(20) NOT NULL COMMENT '点评平台 DIANPING/MEITUAN/GAODE/BAIDU/DOUYIN',
  `action` varchar(30) NOT NULL COMMENT '行为类型 view/draft_copy/draft_used/jump/feedback',
  `user_hash` varchar(64) DEFAULT NULL COMMENT '匿名用户标识(md5(ip+ua))',
  `draft_index` int(11) DEFAULT NULL COMMENT '第几条草稿(用于A/B分析)',
  `ip` varchar(45) DEFAULT NULL COMMENT '客户端IP',
  `ua` varchar(255) DEFAULT NULL COMMENT 'User-Agent 摘要',
  `extra_data` LONGTEXT COMMENT '额外字段,JSON 序列化字符串（生产 MySQL 5.6 不支持 JSON 列类型,改用 LONGTEXT）',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_device` (`device_id`),
  KEY `idx_merchant_platform` (`merchant_id`, `platform`),
  KEY `idx_action_time` (`action`, `created_at`),
  KEY `idx_user_hash` (`user_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='打卡点评行为埋点(合规版:无自动发布)';
