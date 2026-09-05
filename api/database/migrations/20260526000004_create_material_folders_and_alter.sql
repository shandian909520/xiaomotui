-- 素材文件夹表
CREATE TABLE IF NOT EXISTS `xmt_material_folders` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `merchant_id` int(11) unsigned NOT NULL COMMENT '商家ID',
  `parent_id` int(11) unsigned DEFAULT 0 COMMENT '父文件夹ID(0为根目录)',
  `name` varchar(100) NOT NULL COMMENT '文件夹名称',
  `sort` int(11) NOT NULL DEFAULT 0 COMMENT '排序权重',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `delete_time` datetime DEFAULT NULL COMMENT '软删除时间',
  PRIMARY KEY (`id`),
  KEY `idx_merchant_parent` (`merchant_id`, `parent_id`),
  KEY `idx_sort` (`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='素材文件夹表';

-- 素材表增加文件夹和分类字段
ALTER TABLE `xmt_materials`
  ADD COLUMN `folder_id` int(11) unsigned DEFAULT 0 COMMENT '文件夹ID' AFTER `category_id`,
  ADD COLUMN `material_type` enum('video','image','audio','voiceover','guide') DEFAULT 'video' COMMENT '素材分类' AFTER `folder_id`,
  ADD COLUMN `merchant_id` int(11) unsigned DEFAULT NULL COMMENT '商家ID' AFTER `id`,
  ADD COLUMN `is_ai` tinyint(1) DEFAULT 0 COMMENT '是否AI生成' AFTER `material_type`,
  ADD COLUMN `is_deleted` tinyint(1) DEFAULT 0 COMMENT '是否在回收站' AFTER `is_ai`,
  ADD COLUMN `delete_time` datetime DEFAULT NULL COMMENT '删除时间' AFTER `is_deleted`;

ALTER TABLE `xmt_materials`
  ADD KEY `idx_folder` (`folder_id`),
  ADD KEY `idx_material_type` (`material_type`),
  ADD KEY `idx_merchant_id` (`merchant_id`),
  ADD KEY `idx_is_ai` (`is_ai`),
  ADD KEY `idx_is_deleted` (`is_deleted`);
