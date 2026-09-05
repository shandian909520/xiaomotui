-- 团购商品列表(对接现有 group_buy_redirects 跳转链路)
CREATE TABLE IF NOT EXISTS `xmt_groupbuy_items` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `merchant_id` int(11) unsigned NOT NULL COMMENT '商家ID',
  `device_id` int(11) unsigned DEFAULT NULL COMMENT '可选设备绑定(NULL 表示商家维度共享)',
  `platform` varchar(20) NOT NULL DEFAULT 'CUSTOM' COMMENT '平台 MEITUAN/DOUYIN/ELEME/CUSTOM',
  `title` varchar(120) NOT NULL DEFAULT '' COMMENT '商品名称',
  `image` varchar(255) DEFAULT NULL COMMENT '商品图片URL',
  `price` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '团购价',
  `original_price` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '原价',
  `sales` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '已售数量(展示用)',
  `jump_url` varchar(500) NOT NULL DEFAULT '' COMMENT '跳转链接',
  `sort` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '排序(降序)',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态 0下线 1上架',
  `start_time` datetime DEFAULT NULL COMMENT '上架开始时间(可空)',
  `end_time` datetime DEFAULT NULL COMMENT '下架时间(可空)',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_merchant_status_sort` (`merchant_id`, `status`, `sort`),
  KEY `idx_device_status` (`device_id`, `status`),
  KEY `idx_platform` (`platform`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='团购商品列表';
