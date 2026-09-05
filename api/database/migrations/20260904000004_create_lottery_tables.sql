-- 大转盘抽奖模块(3 张表:活动 / 奖品 / 中奖记录)

CREATE TABLE IF NOT EXISTS `xmt_lottery_activities` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '活动ID',
  `merchant_id` int(11) unsigned NOT NULL COMMENT '商家ID',
  `device_id` int(11) unsigned DEFAULT NULL COMMENT '设备ID(NULL 表示商家通用)',
  `name` varchar(100) NOT NULL DEFAULT '' COMMENT '活动名称',
  `start_at` datetime NOT NULL COMMENT '活动开始时间',
  `end_at` datetime NOT NULL COMMENT '活动结束时间',
  `daily_limit` int(11) unsigned NOT NULL DEFAULT 1 COMMENT '每用户每天抽奖次数(对接NFC触发记录)',
  `total_limit` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '活动总抽奖次数(0=不限)',
  `cost_points` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '单次消耗积分(0=免费)',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态 0停用 1启用',
  `description` varchar(500) DEFAULT NULL COMMENT '活动描述/规则',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_device_status` (`device_id`, `status`),
  KEY `idx_merchant_status` (`merchant_id`, `status`),
  KEY `idx_time_window` (`start_at`, `end_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='大转盘活动';

CREATE TABLE IF NOT EXISTS `xmt_lottery_prizes` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '奖品ID',
  `activity_id` int(11) unsigned NOT NULL COMMENT '活动ID',
  `name` varchar(80) NOT NULL DEFAULT '' COMMENT '奖品名称',
  `image` varchar(255) DEFAULT NULL COMMENT '奖品图标',
  `probability` decimal(7,4) NOT NULL DEFAULT 0.0000 COMMENT '中奖概率(0.0001~1.0000,百分比形式)',
  `stock` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '剩余库存',
  `total_stock` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '总库存(0=不限)',
  `prize_type` varchar(20) NOT NULL DEFAULT 'NONE' COMMENT '类型 COUPON/THANKS/CUSTOM/POINTS',
  `coupon_id` int(11) unsigned DEFAULT NULL COMMENT '奖品类型为COUPON时,关联 xmt_coupons.id',
  `extra_data` json DEFAULT NULL COMMENT '扩展数据(POINTS额度/CUSTOM兑换码 等)',
  `sort` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '转盘展示顺序',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态 0停用 1启用',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_activity_sort` (`activity_id`, `sort`),
  KEY `idx_activity_status` (`activity_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='大转盘奖项';

CREATE TABLE IF NOT EXISTS `xmt_lottery_records` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '记录ID',
  `activity_id` int(11) unsigned NOT NULL COMMENT '活动ID',
  `device_id` int(11) unsigned NOT NULL COMMENT '设备ID',
  `user_hash` varchar(64) NOT NULL DEFAULT '' COMMENT '匿名用户标识',
  `prize_id` int(11) unsigned DEFAULT NULL COMMENT '中奖奖品ID(未中奖=NULL)',
  `prize_name` varchar(80) DEFAULT NULL COMMENT '中奖快照名',
  `prize_type` varchar(20) DEFAULT NULL COMMENT '奖品类型快照',
  `coupon_user_id` int(11) unsigned DEFAULT NULL COMMENT '发放的卡券记录ID(prize_type=COUPON)',
  `status` varchar(20) NOT NULL DEFAULT 'PENDING' COMMENT 'PENDING/CLAIMED/EXPIRED/REFUNDED',
  `claimed_at` datetime DEFAULT NULL COMMENT '兑奖时间',
  `claim_code` varchar(32) DEFAULT NULL COMMENT '核销码(可选)',
  `ip` varchar(45) DEFAULT NULL,
  `ua` varchar(255) DEFAULT NULL,
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_activity_user` (`activity_id`, `user_hash`),
  KEY `idx_device_time` (`device_id`, `create_time`),
  KEY `idx_prize` (`prize_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='抽奖记录';
