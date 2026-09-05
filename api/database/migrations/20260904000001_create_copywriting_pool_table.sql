-- 文案池表（设备级预置多条文案 + "换一批"）
CREATE TABLE IF NOT EXISTS `xmt_copywriting_pool` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `device_id` int(11) unsigned NOT NULL COMMENT '设备ID(关联 xmt_nfc_devices.id)',
  `merchant_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '商家ID(冗余,便于商家维度查询)',
  `scene` varchar(20) NOT NULL DEFAULT 'publish' COMMENT '文案场景 publish/review/groupbuy 等',
  `content` text NOT NULL COMMENT '文案正文',
  `weight` int(11) unsigned NOT NULL DEFAULT 10 COMMENT '权重(越大越靠前,用于按权重轮播)',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态 0下线 1启用',
  `sort` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '人工排序',
  `used_count` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '历史返回次数',
  `last_used_time` datetime DEFAULT NULL COMMENT '最近返回时间',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_device_scene` (`device_id`, `scene`, `status`),
  KEY `idx_merchant` (`merchant_id`),
  KEY `idx_weight` (`weight`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='设备文案池(支持换一批/权重轮播)';
