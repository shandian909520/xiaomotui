-- 用餐会话表
-- 删除旧的用餐会话表(如果存在)
DROP TABLE IF EXISTS `xmt_dining_sessions`;

-- 创建用餐会话表
CREATE TABLE `xmt_dining_sessions` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '会话ID',
  `merchant_id` int(11) unsigned NOT NULL COMMENT '所属商家ID',
  `table_id` int(11) unsigned NOT NULL COMMENT '桌台ID',
  `device_id` int(11) unsigned DEFAULT NULL COMMENT 'NFC设备ID',
  `session_code` varchar(32) NOT NULL COMMENT '会话编码',
  `status` enum('ACTIVE','COMPLETED','CANCELLED') DEFAULT 'ACTIVE' COMMENT '会话状态',
  `guest_count` tinyint(3) DEFAULT '1' COMMENT '用餐人数',
  `start_time` datetime NOT NULL COMMENT '开始时间',
  `end_time` datetime DEFAULT NULL COMMENT '结束时间',
  `duration` int(11) DEFAULT NULL COMMENT '用餐时长(分钟)',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_session_code` (`session_code`),
  KEY `idx_merchant_table` (`merchant_id`, `table_id`),
  KEY `idx_table_status` (`table_id`, `status`),
  KEY `idx_status` (`status`),
  KEY `idx_start_time` (`start_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用餐会话表';