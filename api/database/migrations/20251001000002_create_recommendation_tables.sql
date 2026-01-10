-- 创建素材推荐相关表
-- 素材评分表
DROP TABLE IF EXISTS `xmt_material_ratings`;
CREATE TABLE `xmt_material_ratings` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '评分ID',
  `user_id` int(11) unsigned NOT NULL COMMENT '用户ID',
  `template_id` int(11) unsigned NOT NULL COMMENT '模板ID',
  `content_task_id` int(11) unsigned DEFAULT NULL COMMENT '内容任务ID',
  `rating` tinyint(1) NOT NULL COMMENT '评分 1-5',
  `feedback` text COLLATE utf8mb4_unicode_ci COMMENT '反馈内容',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_template` (`user_id`, `template_id`),
  KEY `idx_template` (`template_id`),
  KEY `idx_content_task` (`content_task_id`),
  KEY `idx_rating` (`rating`),
  KEY `idx_create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='素材评分表';

-- 素材使用记录表
DROP TABLE IF EXISTS `xmt_material_usage_logs`;
CREATE TABLE `xmt_material_usage_logs` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '记录ID',
  `user_id` int(11) unsigned NOT NULL COMMENT '用户ID',
  `merchant_id` int(11) unsigned NOT NULL COMMENT '商家ID',
  `template_id` int(11) unsigned NOT NULL COMMENT '模板ID',
  `content_task_id` int(11) unsigned DEFAULT NULL COMMENT '内容任务ID',
  `usage_context` json COMMENT '使用上下文',
  `result` enum('SUCCESS','FAILED') DEFAULT 'SUCCESS' COMMENT '使用结果',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_merchant` (`user_id`, `merchant_id`),
  KEY `idx_template` (`template_id`),
  KEY `idx_content_task` (`content_task_id`),
  KEY `idx_result` (`result`),
  KEY `idx_create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='素材使用记录表';

-- 素材效果统计表
DROP TABLE IF EXISTS `xmt_material_performance`;
CREATE TABLE `xmt_material_performance` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '统计ID',
  `template_id` int(11) unsigned NOT NULL COMMENT '模板ID',
  `date` date NOT NULL COMMENT '统计日期',
  `usage_count` int(11) DEFAULT '0' COMMENT '使用次数',
  `success_count` int(11) DEFAULT '0' COMMENT '成功次数',
  `avg_rating` decimal(3,2) DEFAULT '0.00' COMMENT '平均评分',
  `view_count` int(11) DEFAULT '0' COMMENT '浏览量',
  `share_count` int(11) DEFAULT '0' COMMENT '分享量',
  `conversion_rate` decimal(5,2) DEFAULT '0.00' COMMENT '转化率',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_template_date` (`template_id`, `date`),
  KEY `idx_template` (`template_id`),
  KEY `idx_date` (`date`),
  KEY `idx_usage_count` (`usage_count`),
  KEY `idx_avg_rating` (`avg_rating`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='素材效果统计表';

-- 推荐结果缓存表
DROP TABLE IF EXISTS `xmt_recommendation_cache`;
CREATE TABLE `xmt_recommendation_cache` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '缓存ID',
  `cache_key` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '缓存键',
  `merchant_id` int(11) unsigned DEFAULT NULL COMMENT '商家ID',
  `user_id` int(11) unsigned DEFAULT NULL COMMENT '用户ID',
  `context` json COMMENT '推荐上下文',
  `recommendations` json NOT NULL COMMENT '推荐结果',
  `algorithm` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '推荐算法',
  `expire_time` datetime NOT NULL COMMENT '过期时间',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_cache_key` (`cache_key`),
  KEY `idx_merchant` (`merchant_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_expire` (`expire_time`),
  KEY `idx_algorithm` (`algorithm`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='推荐结果缓存表';