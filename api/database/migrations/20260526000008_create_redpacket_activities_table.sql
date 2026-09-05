-- 红包活动表
CREATE TABLE IF NOT EXISTS `xmt_redpacket_activities` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `merchant_id` int(11) unsigned NOT NULL COMMENT '商家ID',
  `activity_name` varchar(100) NOT NULL COMMENT '活动名称',
  `budget_amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '预算金额',
  `consumed_amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '实际消耗金额',
  `store_count` int(11) NOT NULL DEFAULT 0 COMMENT '参与门店数量',
  `start_time` datetime NOT NULL COMMENT '活动开始时间',
  `end_time` datetime NOT NULL COMMENT '活动结束时间',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态(0=停用 1=进行中 2=已结束)',
  `rule_config` json DEFAULT NULL COMMENT '红包规则配置(金额范围/发放条件等)',
  `fee_rate` decimal(5,4) NOT NULL DEFAULT 0.0100 COMMENT '手续费率(默认1%)',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_merchant` (`merchant_id`),
  KEY `idx_status` (`status`),
  KEY `idx_time` (`start_time`, `end_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='红包活动表';

-- 红包活动门店关联表
CREATE TABLE IF NOT EXISTS `xmt_redpacket_activity_stores` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `activity_id` int(11) unsigned NOT NULL COMMENT '活动ID',
  `store_id` int(11) unsigned NOT NULL COMMENT '门店ID',
  `store_name` varchar(100) DEFAULT NULL COMMENT '门店名称',
  `consumed_amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '门店消耗金额',
  `send_count` int(11) NOT NULL DEFAULT 0 COMMENT '发放数量',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_activity_store` (`activity_id`, `store_id`),
  KEY `idx_store` (`store_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='红包活动门店关联表';
