-- NFC设备表
-- 删除旧的NFC设备表(如果存在)
DROP TABLE IF EXISTS `xmt_nfc_devices`;

-- 创建NFC设备表
CREATE TABLE `xmt_nfc_devices` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '设备ID',
  `merchant_id` int(11) unsigned NOT NULL COMMENT '所属商家ID',
  `device_code` varchar(32) NOT NULL COMMENT '设备编码',
  `device_name` varchar(100) NOT NULL COMMENT '设备名称',
  `location` varchar(100) DEFAULT NULL COMMENT '设备位置',
  `type` enum('TABLE','WALL','COUNTER','ENTRANCE') DEFAULT 'TABLE' COMMENT '设备类型',
  `trigger_mode` enum('VIDEO','COUPON','WIFI','CONTACT','MENU') DEFAULT 'VIDEO' COMMENT '触发模式',
  `template_id` int(11) DEFAULT NULL COMMENT '内容模板ID',
  `redirect_url` varchar(255) DEFAULT NULL COMMENT '跳转链接',
  `wifi_ssid` varchar(50) DEFAULT NULL COMMENT 'WiFi名称',
  `wifi_password` varchar(50) DEFAULT NULL COMMENT 'WiFi密码',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态 0离线 1在线 2维护',
  `battery_level` tinyint(3) DEFAULT NULL COMMENT '电池电量',
  `last_heartbeat` datetime DEFAULT NULL COMMENT '最后心跳时间',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `device_code` (`device_code`),
  KEY `merchant_id` (`merchant_id`),
  KEY `status` (`status`),
  KEY `type` (`type`),
  KEY `trigger_mode` (`trigger_mode`),
  KEY `idx_heartbeat` (`last_heartbeat`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='NFC设备表';