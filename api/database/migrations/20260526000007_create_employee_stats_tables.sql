-- 员工任务统计表
CREATE TABLE IF NOT EXISTS `xmt_employee_stats` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `merchant_id` int(11) unsigned NOT NULL COMMENT '商家ID',
  `employee_id` int(11) unsigned NOT NULL COMMENT '员工ID',
  `store_id` int(11) unsigned DEFAULT NULL COMMENT '门店ID',
  `task_type` varchar(50) NOT NULL COMMENT '任务类型(douyin_publish/kuaishou_publish等)',
  `platform` varchar(30) DEFAULT NULL COMMENT '平台',
  `target_count` int(11) NOT NULL DEFAULT 0 COMMENT '目标数量',
  `completed_count` int(11) NOT NULL DEFAULT 0 COMMENT '完成数量',
  `exposure_count` int(11) NOT NULL DEFAULT 0 COMMENT '曝光量',
  `like_count` int(11) NOT NULL DEFAULT 0 COMMENT '点赞数',
  `publish_count` int(11) NOT NULL DEFAULT 0 COMMENT '发布数',
  `date` date NOT NULL COMMENT '统计日期',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_employee_date_type` (`employee_id`, `date`, `task_type`),
  KEY `idx_merchant` (`merchant_id`),
  KEY `idx_store` (`store_id`),
  KEY `idx_date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='员工任务统计表';

-- 员工排行榜快照表
CREATE TABLE IF NOT EXISTS `xmt_employee_rankings` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `merchant_id` int(11) unsigned NOT NULL COMMENT '商家ID',
  `period_type` enum('day','week','month','quarter','half_year','year') NOT NULL COMMENT '统计周期',
  `period_start` date NOT NULL COMMENT '周期开始',
  `period_end` date NOT NULL COMMENT '周期结束',
  `rank_type` enum('high_creator','high_interact','unpublished','publish_rank') NOT NULL COMMENT '排行类型',
  `employee_id` int(11) unsigned NOT NULL COMMENT '员工ID',
  `employee_name` varchar(50) DEFAULT NULL COMMENT '员工姓名',
  `score` int(11) NOT NULL DEFAULT 0 COMMENT '得分/数量',
  `rank_num` int(11) NOT NULL DEFAULT 0 COMMENT '排名',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_merchant_period` (`merchant_id`, `period_type`, `period_start`),
  KEY `idx_rank_type` (`rank_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='员工排行榜表';
