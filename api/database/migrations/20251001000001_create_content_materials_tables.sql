-- AI内容素材库管理系统表结构
-- 包含6个核心表：素材主表、分类表、标签表、标签关联表、使用记录表、审核记录表

-- ============================================
-- 1. 素材分类表
-- ============================================
DROP TABLE IF EXISTS `xmt_content_material_categories`;

CREATE TABLE `xmt_content_material_categories` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '分类ID',
  `parent_id` int(11) unsigned DEFAULT NULL COMMENT '父分类ID',
  `name` varchar(100) NOT NULL COMMENT '分类名称',
  `type` enum('VIDEO','AUDIO','IMAGE','TEXT','TRANSITION') NOT NULL COMMENT '素材类型',
  `description` varchar(500) DEFAULT NULL COMMENT '分类描述',
  `icon` varchar(255) DEFAULT NULL COMMENT '分类图标',
  `sort_order` int(11) DEFAULT '0' COMMENT '排序',
  `material_count` int(11) unsigned DEFAULT '0' COMMENT '素材数量',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态 0禁用 1启用',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_parent` (`parent_id`),
  KEY `idx_type` (`type`),
  KEY `idx_status` (`status`),
  KEY `idx_sort` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='素材分类表';

-- ============================================
-- 2. 素材标签表
-- ============================================
DROP TABLE IF EXISTS `xmt_content_material_tags`;

CREATE TABLE `xmt_content_material_tags` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '标签ID',
  `name` varchar(50) NOT NULL COMMENT '标签名称',
  `type` enum('VIDEO','AUDIO','IMAGE','TEXT','TRANSITION','ALL') DEFAULT 'ALL' COMMENT '适用类型',
  `usage_count` int(11) unsigned DEFAULT '0' COMMENT '使用次数',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_name_type` (`name`, `type`),
  KEY `idx_type` (`type`),
  KEY `idx_usage_count` (`usage_count`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='素材标签表';

-- ============================================
-- 3. 内容素材主表
-- ============================================
DROP TABLE IF EXISTS `xmt_content_materials`;

CREATE TABLE `xmt_content_materials` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '素材ID',
  `name` varchar(200) NOT NULL COMMENT '素材名称',
  `type` enum('VIDEO','AUDIO','IMAGE','TEXT','TRANSITION') NOT NULL COMMENT '素材类型',
  `category_id` int(11) unsigned DEFAULT NULL COMMENT '分类ID',
  `file_url` varchar(500) DEFAULT NULL COMMENT '文件URL',
  `file_size` int(11) unsigned DEFAULT NULL COMMENT '文件大小(字节)',
  `duration` int(11) unsigned DEFAULT NULL COMMENT '时长(秒,视频/音频)',
  `width` int(11) unsigned DEFAULT NULL COMMENT '宽度(像素,图片/视频)',
  `height` int(11) unsigned DEFAULT NULL COMMENT '高度(像素,图片/视频)',
  `thumbnail_url` varchar(500) DEFAULT NULL COMMENT '缩略图URL',
  `content` text COMMENT '文本内容(文案模板)',
  `metadata` json DEFAULT NULL COMMENT '元数据(标签、属性等)',
  `style` varchar(50) DEFAULT NULL COMMENT '风格标签',
  `scene` varchar(50) DEFAULT NULL COMMENT '适用场景',
  `quality_score` decimal(3,2) DEFAULT '0.00' COMMENT '质量评分(0-10)',
  `usage_count` int(11) unsigned DEFAULT '0' COMMENT '使用次数',
  `success_count` int(11) unsigned DEFAULT '0' COMMENT '成功使用次数',
  `recommendation_weight` decimal(5,2) DEFAULT '1.00' COMMENT '推荐权重',
  `review_status` enum('PENDING','APPROVED','REJECTED') DEFAULT 'PENDING' COMMENT '审核状态',
  `review_time` datetime DEFAULT NULL COMMENT '审核时间',
  `reviewer_id` int(11) unsigned DEFAULT NULL COMMENT '审核人ID',
  `rejection_reason` varchar(500) DEFAULT NULL COMMENT '拒绝原因',
  `is_public` tinyint(1) DEFAULT '1' COMMENT '是否公开 0私有 1公开',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态 0禁用 1启用',
  `creator_id` int(11) unsigned DEFAULT NULL COMMENT '创建者ID',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_type` (`type`),
  KEY `idx_category` (`category_id`),
  KEY `idx_style` (`style`),
  KEY `idx_scene` (`scene`),
  KEY `idx_review_status` (`review_status`),
  KEY `idx_status` (`status`),
  KEY `idx_create_time` (`create_time`),
  KEY `idx_quality_score` (`quality_score`),
  KEY `idx_usage_count` (`usage_count`),
  KEY `idx_recommendation_weight` (`recommendation_weight`),
  KEY `idx_creator` (`creator_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='内容素材表';

-- ============================================
-- 4. 素材标签关联表
-- ============================================
DROP TABLE IF EXISTS `xmt_content_material_tag_relations`;

CREATE TABLE `xmt_content_material_tag_relations` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `material_id` int(11) unsigned NOT NULL COMMENT '素材ID',
  `tag_id` int(11) unsigned NOT NULL COMMENT '标签ID',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_material_tag` (`material_id`, `tag_id`),
  KEY `idx_material` (`material_id`),
  KEY `idx_tag` (`tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='素材标签关联表';

-- ============================================
-- 5. 素材使用记录表
-- ============================================
DROP TABLE IF EXISTS `xmt_content_material_usage`;

CREATE TABLE `xmt_content_material_usage` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `material_id` int(11) unsigned NOT NULL COMMENT '素材ID',
  `template_id` int(11) unsigned DEFAULT NULL COMMENT '模板ID',
  `content_task_id` int(11) unsigned DEFAULT NULL COMMENT '内容任务ID',
  `user_id` int(11) unsigned DEFAULT NULL COMMENT '用户ID',
  `merchant_id` int(11) unsigned DEFAULT NULL COMMENT '商家ID',
  `usage_context` json DEFAULT NULL COMMENT '使用上下文',
  `performance_score` decimal(3,2) DEFAULT NULL COMMENT '表现评分(0-10)',
  `user_feedback` tinyint(1) DEFAULT NULL COMMENT '用户反馈 1好评 0差评',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_material` (`material_id`),
  KEY `idx_template` (`template_id`),
  KEY `idx_task` (`content_task_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_merchant` (`merchant_id`),
  KEY `idx_create_time` (`create_time`),
  KEY `idx_performance_score` (`performance_score`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='素材使用记录表';

-- ============================================
-- 6. 素材审核记录表
-- ============================================
DROP TABLE IF EXISTS `xmt_content_material_reviews`;

CREATE TABLE `xmt_content_material_reviews` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '记录ID',
  `material_id` int(11) unsigned NOT NULL COMMENT '素材ID',
  `reviewer_id` int(11) unsigned NOT NULL COMMENT '审核人ID',
  `review_type` enum('AUTO','MANUAL') NOT NULL COMMENT '审核类型',
  `result` enum('APPROVED','REJECTED','FLAGGED') NOT NULL COMMENT '审核结果',
  `score` decimal(3,2) DEFAULT NULL COMMENT '评分(0-10)',
  `issues` json DEFAULT NULL COMMENT '问题列表',
  `comments` text COMMENT '审核意见',
  `review_time` datetime NOT NULL COMMENT '审核时间',
  PRIMARY KEY (`id`),
  KEY `idx_material` (`material_id`),
  KEY `idx_reviewer` (`reviewer_id`),
  KEY `idx_review_time` (`review_time`),
  KEY `idx_review_type` (`review_type`),
  KEY `idx_result` (`result`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='素材审核记录表';