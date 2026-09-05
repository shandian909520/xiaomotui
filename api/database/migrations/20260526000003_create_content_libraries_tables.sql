-- 内容库表
CREATE TABLE IF NOT EXISTS `xmt_content_libraries` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `merchant_id` int(11) unsigned NOT NULL COMMENT '商家ID',
  `library_type` enum('video','graphic','image','text','topic') NOT NULL COMMENT '库类型',
  `name` varchar(100) NOT NULL COMMENT '库名称',
  `max_use_count` int(11) NOT NULL DEFAULT 0 COMMENT '最多使用次数(0不限)',
  `total_count` int(11) NOT NULL DEFAULT 0 COMMENT '总内容数量',
  `used_count` int(11) NOT NULL DEFAULT 0 COMMENT '已使用次数',
  `remaining_count` int(11) NOT NULL DEFAULT 0 COMMENT '剩余使用次数',
  `warning_email` varchar(200) DEFAULT NULL COMMENT '预警提示邮箱',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态 0禁用 1启用',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_merchant_type` (`merchant_id`, `library_type`),
  KEY `idx_status` (`status`),
  KEY `idx_create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='内容库表';

-- 内容库条目表
CREATE TABLE IF NOT EXISTS `xmt_content_library_items` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `library_id` int(11) unsigned NOT NULL COMMENT '库ID',
  `item_type` enum('video','image','text','topic') NOT NULL COMMENT '条目类型',
  `title` varchar(200) DEFAULT NULL COMMENT '标题',
  `content` text DEFAULT NULL COMMENT '文本内容(文案/话题)',
  `file_url` varchar(500) DEFAULT NULL COMMENT '文件URL(视频/图片)',
  `thumbnail_url` varchar(500) DEFAULT NULL COMMENT '缩略图URL',
  `paired_item_id` int(11) unsigned DEFAULT NULL COMMENT '配对条目ID(图文库中图片配文案)',
  `metadata` json DEFAULT NULL COMMENT '元数据',
  `use_count` int(11) NOT NULL DEFAULT 0 COMMENT '使用次数',
  `source` enum('local','import','ai') NOT NULL DEFAULT 'local' COMMENT '来源',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态 0禁用 1启用',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_library` (`library_id`),
  KEY `idx_type` (`item_type`),
  KEY `idx_source` (`source`),
  KEY `idx_create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='内容库条目表';
