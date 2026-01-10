-- 违规内容处理系统表结构
-- 包含3个核心表：违规记录表、申诉记录表、商家通知表

-- ============================================
-- 1. 内容违规记录表
-- ============================================
DROP TABLE IF EXISTS `xmt_content_violations`;

CREATE TABLE `xmt_content_violations` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '违规记录ID',
  `material_id` int(11) unsigned NOT NULL COMMENT '素材ID',
  `merchant_id` int(11) unsigned NOT NULL COMMENT '商家ID',
  `violation_type` enum('SENSITIVE','ILLEGAL','PORN','VIOLENCE','AD','FRAUD','SPAM','COPYRIGHT','OTHER') NOT NULL COMMENT '违规类型',
  `severity` enum('HIGH','MEDIUM','LOW') NOT NULL COMMENT '严重程度',
  `title` varchar(200) NOT NULL COMMENT '违规标题',
  `description` text NOT NULL COMMENT '违规描述',
  `details` json DEFAULT NULL COMMENT '违规详情(关键词、检测结果等)',
  `detection_method` enum('AUTO','MANUAL','REPORT') NOT NULL COMMENT '检测方式',
  `detector_id` int(11) unsigned DEFAULT NULL COMMENT '检测人ID(手动检测时)',
  `reporter_id` int(11) unsigned DEFAULT NULL COMMENT '举报人ID(举报时)',
  `report_reason` text DEFAULT NULL COMMENT '举报原因',
  `action_taken` enum('DISABLED','WARNING','DELETED','NONE') NOT NULL DEFAULT 'DISABLED' COMMENT '处理动作',
  `status` enum('PENDING','CONFIRMED','APPEALED','RESOLVED','DISMISSED') DEFAULT 'PENDING' COMMENT '状态',
  `appeal_id` int(11) unsigned DEFAULT NULL COMMENT '申诉ID',
  `evidence_urls` json DEFAULT NULL COMMENT '证据截图URL列表',
  `auto_disable` tinyint(1) DEFAULT '1' COMMENT '是否自动下架 0否 1是',
  `notification_sent` tinyint(1) DEFAULT '0' COMMENT '是否已通知 0否 1是',
  `notification_time` datetime DEFAULT NULL COMMENT '通知时间',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `resolve_time` datetime DEFAULT NULL COMMENT '处理完成时间',
  `resolver_id` int(11) unsigned DEFAULT NULL COMMENT '处理人ID',
  `resolve_comment` text DEFAULT NULL COMMENT '处理备注',
  PRIMARY KEY (`id`),
  KEY `idx_material` (`material_id`),
  KEY `idx_merchant` (`merchant_id`),
  KEY `idx_status` (`status`),
  KEY `idx_violation_type` (`violation_type`),
  KEY `idx_severity` (`severity`),
  KEY `idx_detection_method` (`detection_method`),
  KEY `idx_create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='内容违规记录表';

-- ============================================
-- 2. 违规申诉记录表
-- ============================================
DROP TABLE IF EXISTS `xmt_violation_appeals`;

CREATE TABLE `xmt_violation_appeals` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '申诉ID',
  `violation_id` int(11) unsigned NOT NULL COMMENT '违规记录ID',
  `merchant_id` int(11) unsigned NOT NULL COMMENT '商家ID',
  `material_id` int(11) unsigned NOT NULL COMMENT '素材ID',
  `reason` text NOT NULL COMMENT '申诉理由',
  `evidence` json DEFAULT NULL COMMENT '申诉证据(文档、截图等)',
  `contact_phone` varchar(20) DEFAULT NULL COMMENT '联系电话',
  `contact_email` varchar(100) DEFAULT NULL COMMENT '联系邮箱',
  `status` enum('PENDING','REVIEWING','APPROVED','REJECTED','CANCELLED') DEFAULT 'PENDING' COMMENT '申诉状态',
  `reviewer_id` int(11) unsigned DEFAULT NULL COMMENT '审核人ID',
  `review_comment` text DEFAULT NULL COMMENT '审核意见',
  `review_result` json DEFAULT NULL COMMENT '审核结果详情',
  `priority` tinyint(1) DEFAULT '0' COMMENT '优先级 0普通 1高',
  `submit_time` datetime NOT NULL COMMENT '提交时间',
  `review_time` datetime DEFAULT NULL COMMENT '审核时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_violation` (`violation_id`),
  KEY `idx_merchant` (`merchant_id`),
  KEY `idx_material` (`material_id`),
  KEY `idx_status` (`status`),
  KEY `idx_submit_time` (`submit_time`),
  KEY `idx_priority` (`priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='违规申诉记录表';

-- ============================================
-- 3. 商家通知队列表
-- ============================================
DROP TABLE IF EXISTS `xmt_merchant_notifications`;

CREATE TABLE `xmt_merchant_notifications` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '通知ID',
  `merchant_id` int(11) unsigned NOT NULL COMMENT '商家ID',
  `type` enum('VIOLATION','APPEAL_RESULT','WARNING','MATERIAL_DISABLED','INFO','SYSTEM') NOT NULL COMMENT '通知类型',
  `title` varchar(200) NOT NULL COMMENT '通知标题',
  `content` text NOT NULL COMMENT '通知内容',
  `content_html` text DEFAULT NULL COMMENT '通知内容(HTML)',
  `related_id` int(11) unsigned DEFAULT NULL COMMENT '关联ID',
  `related_type` varchar(50) DEFAULT NULL COMMENT '关联类型(violation/appeal/material)',
  `related_data` json DEFAULT NULL COMMENT '关联数据',
  `channels` json NOT NULL COMMENT '通知渠道["system","email","sms","wechat"]',
  `priority` enum('HIGH','NORMAL','LOW') DEFAULT 'NORMAL' COMMENT '优先级',
  `status` enum('PENDING','SENDING','SENT','FAILED','READ') DEFAULT 'PENDING' COMMENT '状态',
  `send_result` json DEFAULT NULL COMMENT '发送结果详情',
  `send_time` datetime DEFAULT NULL COMMENT '发送时间',
  `read_time` datetime DEFAULT NULL COMMENT '阅读时间',
  `expire_time` datetime DEFAULT NULL COMMENT '过期时间',
  `retry_count` tinyint(2) DEFAULT '0' COMMENT '重试次数',
  `max_retry` tinyint(2) DEFAULT '3' COMMENT '最大重试次数',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_merchant` (`merchant_id`),
  KEY `idx_type` (`type`),
  KEY `idx_status` (`status`),
  KEY `idx_priority` (`priority`),
  KEY `idx_create_time` (`create_time`),
  KEY `idx_related` (`related_type`, `related_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商家通知表';

-- ============================================
-- 4. 违规关键词库表(可选，用于文本审核)
-- ============================================
DROP TABLE IF EXISTS `xmt_violation_keywords`;

CREATE TABLE `xmt_violation_keywords` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `keyword` varchar(100) NOT NULL COMMENT '关键词',
  `category` enum('SENSITIVE','ILLEGAL','PORN','VIOLENCE','AD','FRAUD','SPAM') NOT NULL COMMENT '类别',
  `severity` enum('HIGH','MEDIUM','LOW') DEFAULT 'MEDIUM' COMMENT '严重程度',
  `match_type` enum('EXACT','FUZZY','REGEX') DEFAULT 'EXACT' COMMENT '匹配方式',
  `pattern` varchar(255) DEFAULT NULL COMMENT '正则表达式(REGEX类型)',
  `enabled` tinyint(1) DEFAULT '1' COMMENT '是否启用 0禁用 1启用',
  `hit_count` int(11) unsigned DEFAULT '0' COMMENT '命中次数',
  `last_hit_time` datetime DEFAULT NULL COMMENT '最后命中时间',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_keyword_category` (`keyword`, `category`),
  KEY `idx_category` (`category`),
  KEY `idx_enabled` (`enabled`),
  KEY `idx_hit_count` (`hit_count`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='违规关键词库表';

-- ============================================
-- 5. 商家黑名单表
-- ============================================
DROP TABLE IF EXISTS `xmt_merchant_blacklist`;

CREATE TABLE `xmt_merchant_blacklist` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `merchant_id` int(11) unsigned NOT NULL COMMENT '商家ID',
  `reason` text NOT NULL COMMENT '加入黑名单原因',
  `violation_count` int(11) unsigned DEFAULT '0' COMMENT '违规次数',
  `severity_level` enum('HIGH','MEDIUM','LOW') DEFAULT 'MEDIUM' COMMENT '严重程度',
  `restrictions` json DEFAULT NULL COMMENT '限制措施',
  `status` enum('ACTIVE','LIFTED','EXPIRED') DEFAULT 'ACTIVE' COMMENT '状态',
  `start_time` datetime NOT NULL COMMENT '开始时间',
  `expire_time` datetime DEFAULT NULL COMMENT '到期时间(NULL为永久)',
  `operator_id` int(11) unsigned NOT NULL COMMENT '操作人ID',
  `lift_time` datetime DEFAULT NULL COMMENT '解除时间',
  `lift_reason` text DEFAULT NULL COMMENT '解除原因',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_merchant` (`merchant_id`),
  KEY `idx_status` (`status`),
  KEY `idx_start_time` (`start_time`),
  KEY `idx_expire_time` (`expire_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商家黑名单表';