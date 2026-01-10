-- 素材分类表
CREATE TABLE IF NOT EXISTS `material_categories` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '分类ID',
  `parent_id` int(11) DEFAULT 0 COMMENT '父分类ID',
  `name` varchar(100) NOT NULL COMMENT '分类名称',
  `type` enum('VIDEO','AUDIO','TRANSITION','TEXT_TEMPLATE','IMAGE','MUSIC') NOT NULL COMMENT '素材类型',
  `description` text COMMENT '分类描述',
  `sort` int DEFAULT 0 COMMENT '排序',
  `status` tinyint(1) DEFAULT 1 COMMENT '状态 0禁用 1启用',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_parent_id` (`parent_id`),
  KEY `idx_type` (`type`),
  KEY `idx_status` (`status`),
  KEY `idx_sort` (`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='素材分类表';