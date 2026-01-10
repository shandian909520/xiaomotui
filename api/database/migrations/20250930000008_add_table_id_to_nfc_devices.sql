-- 为NFC设备表添加桌台ID字段
-- 用于支持NFC设备与桌台的绑定关系

ALTER TABLE `xmt_nfc_devices`
ADD COLUMN `table_id` int(11) unsigned DEFAULT NULL COMMENT '绑定的桌台ID' AFTER `merchant_id`,
ADD KEY `idx_table_id` (`table_id`);