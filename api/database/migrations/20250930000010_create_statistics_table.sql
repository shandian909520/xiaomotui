-- 统计数据表
CREATE TABLE IF NOT EXISTS `xmt_statistics` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '统计ID',
  `user_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '用户ID',
  `merchant_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '商家ID',
  `stat_type` varchar(50) NOT NULL DEFAULT '' COMMENT '统计类型',
  `stat_key` varchar(100) NOT NULL DEFAULT '' COMMENT '统计键',
  `stat_value` bigint NOT NULL DEFAULT '0' COMMENT '统计值',
  `stat_date` date NOT NULL COMMENT '统计日期',
  `extra_data` json DEFAULT NULL COMMENT '额外数据',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_stat` (`user_id`, `merchant_id`, `stat_type`, `stat_key`, `stat_date`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_merchant_id` (`merchant_id`),
  KEY `idx_stat_type` (`stat_type`),
  KEY `idx_stat_date` (`stat_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='统计数据表';
