-- 桌台表
-- 删除旧的桌台表(如果存在)
DROP TABLE IF EXISTS `xmt_tables`;

-- 创建桌台表
CREATE TABLE `xmt_tables` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '桌台ID',
  `merchant_id` int(11) unsigned NOT NULL COMMENT '所属商家ID',
  `table_number` varchar(20) NOT NULL COMMENT '桌号',
  `capacity` tinyint(3) NOT NULL DEFAULT '4' COMMENT '容纳人数',
  `area` varchar(50) DEFAULT NULL COMMENT '区域',
  `qr_code` varchar(255) DEFAULT NULL COMMENT '二维码',
  `status` enum('AVAILABLE','OCCUPIED','CLEANING') DEFAULT 'AVAILABLE' COMMENT '桌台状态',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_merchant_table` (`merchant_id`, `table_number`),
  KEY `idx_merchant_status` (`merchant_id`, `status`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='桌台表';