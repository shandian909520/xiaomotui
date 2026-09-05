-- 话题监控表
CREATE TABLE IF NOT EXISTS `xmt_topic_monitors` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `merchant_id` int(11) unsigned NOT NULL COMMENT '商家ID',
  `platform` enum('douyin','kuaishou') NOT NULL COMMENT '平台(抖音/快手)',
  `topic_keyword` varchar(200) NOT NULL COMMENT '话题关键词',
  `topic_url` varchar(500) DEFAULT NULL COMMENT '话题链接',
  `total_play_count` bigint(20) NOT NULL DEFAULT 0 COMMENT '总播放量',
  `total_post_count` int(11) NOT NULL DEFAULT 0 COMMENT '总投稿量',
  `yesterday_play_count` int(11) NOT NULL DEFAULT 0 COMMENT '昨日新增播放量',
  `yesterday_post_count` int(11) NOT NULL DEFAULT 0 COMMENT '昨日新增投稿量',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态(0=已取消 1=监控中)',
  `last_sync_time` datetime DEFAULT NULL COMMENT '最近同步时间',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_merchant_platform_keyword` (`merchant_id`, `platform`, `topic_keyword`),
  KEY `idx_status` (`status`),
  KEY `idx_platform` (`platform`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='话题监控表';

-- 话题监控每日快照表
CREATE TABLE IF NOT EXISTS `xmt_topic_monitor_daily` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `monitor_id` int(11) unsigned NOT NULL COMMENT '监控ID',
  `date` date NOT NULL COMMENT '统计日期',
  `play_count` int(11) NOT NULL DEFAULT 0 COMMENT '当日播放量',
  `post_count` int(11) NOT NULL DEFAULT 0 COMMENT '当日投稿量',
  `cumulative_play_count` bigint(20) NOT NULL DEFAULT 0 COMMENT '累计播放量',
  `cumulative_post_count` int(11) NOT NULL DEFAULT 0 COMMENT '累计投稿量',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_monitor_date` (`monitor_id`, `date`),
  KEY `idx_date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='话题监控每日快照表';
