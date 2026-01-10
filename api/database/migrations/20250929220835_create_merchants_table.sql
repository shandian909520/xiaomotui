-- 商家表
-- 删除旧的商家表(如果存在)
DROP TABLE IF EXISTS `xmt_merchants`;

-- 创建商家表
CREATE TABLE `xmt_merchants` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '商家ID',
  `user_id` int(11) unsigned NOT NULL COMMENT '关联用户ID',
  `name` varchar(100) NOT NULL COMMENT '商家名称',
  `category` varchar(50) NOT NULL COMMENT '商家类别',
  `address` varchar(255) NOT NULL COMMENT '地址',
  `longitude` decimal(10,7) DEFAULT NULL COMMENT '经度',
  `latitude` decimal(10,7) DEFAULT NULL COMMENT '纬度',
  `phone` varchar(20) DEFAULT NULL COMMENT '联系电话',
  `description` text COMMENT '商家描述',
  `logo` varchar(255) DEFAULT NULL COMMENT '商家logo',
  `business_hours` json DEFAULT NULL COMMENT '营业时间',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态 0禁用 1正常 2审核中',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `category` (`category`),
  KEY `status` (`status`),
  KEY `idx_location` (`longitude`, `latitude`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商家表';