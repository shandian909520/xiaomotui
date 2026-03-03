-- 内容审核相关表结构
-- 创建日期: 2026-01-11

-- 1. 违规关键词表
DROP TABLE IF EXISTS `xmt_violation_keywords`;
CREATE TABLE IF NOT EXISTS `xmt_violation_keywords` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '关键词ID',
  `keyword` varchar(255) NOT NULL COMMENT '关键词',
  `category` varchar(50) NOT NULL DEFAULT 'OTHER' COMMENT '违规类型: PORN/POLITICS/VIOLENCE/AD/ILLEGAL/ABUSE/TERRORISM/SPAM/OTHER',
  `severity` varchar(20) NOT NULL DEFAULT 'MEDIUM' COMMENT '严重程度: HIGH/MEDIUM/LOW',
  `match_type` varchar(20) NOT NULL DEFAULT 'EXACT' COMMENT '匹配类型: EXACT(精确)/FUZZY(模糊)/REGEX(正则)',
  `pattern` varchar(500) DEFAULT NULL COMMENT '正则表达式(当match_type=REGEX时使用)',
  `enabled` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否启用: 0-禁用,1-启用',
  `hit_count` int(11) NOT NULL DEFAULT 0 COMMENT '命中次数',
  `last_hit_time` datetime DEFAULT NULL COMMENT '最后命中时间',
  `created_by` int(11) DEFAULT NULL COMMENT '创建人ID',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_keyword` (`keyword`),
  KEY `idx_category` (`category`),
  KEY `idx_enabled` (`enabled`),
  KEY `idx_match_type` (`match_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='违规关键词表';

-- 2. 内容审核任务表
CREATE TABLE IF NOT EXISTS `xmt_content_moderation_tasks` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '任务ID',
  `task_id` varchar(64) NOT NULL COMMENT '任务唯一标识',
  `material_id` int(11) DEFAULT NULL COMMENT '素材ID',
  `content_type` varchar(20) NOT NULL COMMENT '内容类型: text/image/video/audio',
  `provider` varchar(20) NOT NULL COMMENT '服务商: baidu/aliyun/tencent',
  `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT '任务状态: pending/processing/completed/failed',
  `error_message` text COMMENT '错误信息',
  `result` text COMMENT '审核结果JSON',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `started_at` datetime DEFAULT NULL COMMENT '开始处理时间',
  `completed_at` datetime DEFAULT NULL COMMENT '完成时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_task_id` (`task_id`),
  KEY `idx_material_id` (`material_id`),
  KEY `idx_status` (`status`),
  KEY `idx_provider` (`provider`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='内容审核任务表';

-- 3. 内容审核结果表
CREATE TABLE IF NOT EXISTS `xmt_content_moderation_results` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '结果ID',
  `task_id` varchar(64) DEFAULT NULL COMMENT '任务ID',
  `material_id` int(11) DEFAULT NULL COMMENT '素材ID',
  `provider` varchar(20) NOT NULL COMMENT '服务商',
  `pass` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否通过: 0-不通过,1-通过',
  `score` int(11) NOT NULL DEFAULT 100 COMMENT '评分(0-100)',
  `confidence` decimal(3,2) NOT NULL DEFAULT 1.00 COMMENT '置信度(0-1)',
  `suggestion` varchar(20) NOT NULL DEFAULT 'pass' COMMENT '审核建议: pass/review/reject',
  `violations` text COMMENT '违规详情JSON',
  `check_time` datetime NOT NULL COMMENT '检查时间',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_task_id` (`task_id`),
  KEY `idx_material_id` (`material_id`),
  KEY `idx_provider` (`provider`),
  KEY `idx_check_time` (`check_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='内容审核结果表';

-- 4. 审核日志表
CREATE TABLE IF NOT EXISTS `xmt_content_moderation_logs` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '日志ID',
  `material_id` int(11) DEFAULT NULL COMMENT '素材ID',
  `content_type` varchar(20) NOT NULL COMMENT '内容类型',
  `provider` varchar(20) DEFAULT NULL COMMENT '服务商',
  `action` varchar(50) NOT NULL COMMENT '操作: check/async/cached/error',
  `request_data` text COMMENT '请求数据JSON',
  `response_data` text COMMENT '响应数据JSON',
  `execution_time` int(11) DEFAULT NULL COMMENT '执行时间(毫秒)',
  `error_message` text COMMENT '错误信息',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_material_id` (`material_id`),
  KEY `idx_provider` (`provider`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='内容审核日志表';

-- 5. 用户违规记录表
CREATE TABLE IF NOT EXISTS `xmt_user_violations` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '记录ID',
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `material_id` int(11) DEFAULT NULL COMMENT '素材ID',
  `violation_type` varchar(50) NOT NULL COMMENT '违规类型',
  `severity` varchar(20) NOT NULL COMMENT '严重程度',
  `description` text COMMENT '违规描述',
  `provider` varchar(20) DEFAULT NULL COMMENT '检测服务商',
  `confidence` decimal(3,2) DEFAULT NULL COMMENT '置信度',
  `handled` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否已处理: 0-未处理,1-已处理',
  `handled_at` datetime DEFAULT NULL COMMENT '处理时间',
  `handled_by` int(11) DEFAULT NULL COMMENT '处理人ID',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_material_id` (`material_id`),
  KEY `idx_violation_type` (`violation_type`),
  KEY `idx_severity` (`severity`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户违规记录表';

-- 6. 黑名单表
DROP TABLE IF EXISTS `xmt_content_moderation_blacklist`;
CREATE TABLE IF NOT EXISTS `xmt_content_moderation_blacklist` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '记录ID',
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `blacklist_type` varchar(50) NOT NULL COMMENT '黑名单类型: user/content/ip',
  `reason` text COMMENT '加入黑名单原因',
  `violation_count` int(11) NOT NULL DEFAULT 1 COMMENT '违规次数',
  `severity` varchar(20) NOT NULL DEFAULT 'MEDIUM' COMMENT '严重程度',
  `auto_add` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否自动添加: 0-手动,1-自动',
  `created_by` int(11) DEFAULT NULL COMMENT '创建人ID',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `expires_at` datetime DEFAULT NULL COMMENT '过期时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_type` (`blacklist_type`),
  KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='内容审核黑名单表';

-- 插入示例关键词数据
INSERT IGNORE INTO `xmt_violation_keywords` (`keyword`, `category`, `severity`, `match_type`) VALUES
('赌球', 'ILLEGAL', 'HIGH', 'EXACT'),
('博彩', 'ILLEGAL', 'HIGH', 'EXACT'),
('刷单', 'AD', 'MEDIUM', 'EXACT'),
('代刷', 'AD', 'MEDIUM', 'EXACT');

-- 修改素材表添加审核字段 (如果不存在)
SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'xmt_materials' AND COLUMN_NAME = 'moderation_status') > 0,
    'DO NULL',
    'ALTER TABLE `xmt_materials` ADD COLUMN `moderation_status` VARCHAR(20) DEFAULT \'PENDING\' COMMENT \'审核状态: PENDING/APPROVED/REJECTED\''
));
PREPARE stmt FROM @s;
EXECUTE stmt;

SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'xmt_materials' AND COLUMN_NAME = 'moderation_score') > 0,
    'DO NULL',
    'ALTER TABLE `xmt_materials` ADD COLUMN `moderation_score` INT(11) DEFAULT 100 COMMENT \'审核评分\''
));
PREPARE stmt FROM @s;
EXECUTE stmt;

SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'xmt_materials' AND COLUMN_NAME = 'moderation_time') > 0,
    'DO NULL',
    'ALTER TABLE `xmt_materials` ADD COLUMN `moderation_time` DATETIME DEFAULT NULL COMMENT \'审核时间\''
));
PREPARE stmt FROM @s;
EXECUTE stmt;

-- 添加索引 (如果不存在)
SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'xmt_materials' AND INDEX_NAME = 'idx_moderation_status') > 0,
    'DO NULL',
    'ALTER TABLE `xmt_materials` ADD INDEX `idx_moderation_status` (`moderation_status`)'
));
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
