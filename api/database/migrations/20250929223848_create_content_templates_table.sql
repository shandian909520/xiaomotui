-- 内容模板表
-- 删除旧的内容模板表(如果存在)
DROP TABLE IF EXISTS `xmt_content_templates`;

-- 创建内容模板表
CREATE TABLE `xmt_content_templates` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '模板ID',
  `merchant_id` int(11) unsigned DEFAULT NULL COMMENT '商家ID 为空表示系统模板',
  `name` varchar(100) NOT NULL COMMENT '模板名称',
  `type` enum('VIDEO','TEXT','IMAGE') NOT NULL COMMENT '模板类型',
  `category` varchar(50) NOT NULL COMMENT '模板分类',
  `style` varchar(50) DEFAULT NULL COMMENT '风格标签',
  `content` json NOT NULL COMMENT '模板内容配置',
  `preview_url` varchar(255) DEFAULT NULL COMMENT '预览图',
  `usage_count` int(11) DEFAULT '0' COMMENT '使用次数',
  `is_public` tinyint(1) DEFAULT '0' COMMENT '是否公开 0私有 1公开',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态 0禁用 1启用',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `merchant_id` (`merchant_id`),
  KEY `category` (`category`),
  KEY `type` (`type`),
  KEY `style` (`style`),
  KEY `is_public` (`is_public`),
  KEY `status` (`status`),
  KEY `idx_usage` (`usage_count`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='内容模板表';