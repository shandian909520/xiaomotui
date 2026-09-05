-- 门店批量导入临时表
CREATE TABLE IF NOT EXISTS `xmt_store_import_tasks` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `merchant_id` int(11) unsigned NOT NULL COMMENT '商家ID',
  `import_type` enum('store','poi') NOT NULL COMMENT '导入类型(store=门店/poi=POI)',
  `total_count` int(11) NOT NULL DEFAULT 0 COMMENT '总导入数',
  `success_count` int(11) NOT NULL DEFAULT 0 COMMENT '成功数',
  `fail_count` int(11) NOT NULL DEFAULT 0 COMMENT '失败数',
  `status` enum('pending','processing','completed','failed') NOT NULL DEFAULT 'pending' COMMENT '状态',
  `fail_reason` text DEFAULT NULL COMMENT '失败原因汇总',
  `file_url` varchar(500) DEFAULT NULL COMMENT '导入文件URL',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_merchant` (`merchant_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='门店批量导入任务表';

-- ALTER 门店表增加字段（如果字段不存在则添加）
-- 服务设施、POI信息、装修配置、二维码/NFC
ALTER TABLE `xmt_stores`
  ADD COLUMN IF NOT EXISTS `service_facilities` json DEFAULT NULL COMMENT '服务设施(WiFi/停车/包厢等)',
  ADD COLUMN IF NOT EXISTS `poi_id` varchar(50) DEFAULT NULL COMMENT '平台POI ID',
  ADD COLUMN IF NOT EXISTS `poi_name` varchar(100) DEFAULT NULL COMMENT 'POI名称',
  ADD COLUMN IF NOT EXISTS `poi_platform` varchar(30) DEFAULT NULL COMMENT 'POI平台(抖音/快手/大众点评)',
  ADD COLUMN IF NOT EXISTS `decoration_config` json DEFAULT NULL COMMENT '自定义页面装修配置',
  ADD COLUMN IF NOT EXISTS `qr_code_url` varchar(500) DEFAULT NULL COMMENT '门店二维码URL',
  ADD COLUMN IF NOT EXISTS `nfc_config_path` varchar(500) DEFAULT NULL COMMENT 'NFC配置路径',
  ADD COLUMN IF NOT EXISTS `table_sticker_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '桌贴激活状态(0=未激活 1=已激活)';
