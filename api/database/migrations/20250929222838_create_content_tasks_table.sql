-- 内容生成任务表
-- 删除旧的内容任务表(如果存在)
DROP TABLE IF EXISTS `xmt_content_tasks`;

-- 创建内容生成任务表
CREATE TABLE `xmt_content_tasks` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '任务ID',
  `user_id` int(11) unsigned NOT NULL COMMENT '用户ID',
  `merchant_id` int(11) unsigned NOT NULL COMMENT '商家ID',
  `device_id` int(11) unsigned DEFAULT NULL COMMENT '设备ID',
  `template_id` int(11) unsigned DEFAULT NULL COMMENT '模板ID',
  `type` enum('VIDEO','TEXT','IMAGE') NOT NULL COMMENT '内容类型',
  `status` enum('PENDING','PROCESSING','COMPLETED','FAILED') DEFAULT 'PENDING' COMMENT '任务状态',
  `input_data` json DEFAULT NULL COMMENT '输入数据',
  `output_data` json DEFAULT NULL COMMENT '输出数据',
  `ai_provider` varchar(20) DEFAULT NULL COMMENT 'AI服务商',
  `generation_time` int(11) DEFAULT NULL COMMENT '生成耗时(秒)',
  `error_message` text COMMENT '错误信息',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  `complete_time` datetime DEFAULT NULL COMMENT '完成时间',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `merchant_id` (`merchant_id`),
  KEY `device_id` (`device_id`),
  KEY `template_id` (`template_id`),
  KEY `status` (`status`),
  KEY `type` (`type`),
  KEY `ai_provider` (`ai_provider`),
  KEY `idx_create_time` (`create_time`),
  KEY `idx_complete_time` (`complete_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='内容生成任务表';