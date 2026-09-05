-- 商家权益表
CREATE TABLE IF NOT EXISTS `xmt_merchant_benefits` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `merchant_id` int(11) unsigned NOT NULL COMMENT '商家ID',
  `version_type` enum('basic','standard','chain') NOT NULL DEFAULT 'basic' COMMENT '版本类型:basic基础版,standard标准版,chain连锁版',
  `store_quota` int(11) NOT NULL DEFAULT 0 COMMENT '门店总额度',
  `store_used` int(11) NOT NULL DEFAULT 0 COMMENT '已使用门店数',
  `clip_power` int(11) NOT NULL DEFAULT 0 COMMENT '剪辑魔力额度',
  `storage` bigint NOT NULL DEFAULT 0 COMMENT '存储空间(字节)',
  `redpacket_balance` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '红包余额(元)',
  `expire_time` datetime DEFAULT NULL COMMENT '权益到期时间',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_merchant` (`merchant_id`),
  KEY `idx_version_type` (`version_type`),
  KEY `idx_expire_time` (`expire_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='商家权益表';

-- 卡密表
CREATE TABLE IF NOT EXISTS `xmt_card_keys` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `card_key` varchar(64) NOT NULL COMMENT '卡密',
  `type` enum('store','clip_power','storage','redpacket','version_upgrade','combo') NOT NULL COMMENT '卡密类型',
  `benefit_payload` json DEFAULT NULL COMMENT '权益内容JSON {"store_quota":10,"clip_power":100,...}',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态 0未使用 1已使用 2已过期 3已禁用',
  `merchant_id` int(11) unsigned DEFAULT NULL COMMENT '使用者(商家ID)',
  `used_at` datetime DEFAULT NULL COMMENT '使用时间',
  `expire_at` datetime DEFAULT NULL COMMENT '过期时间',
  `created_by` int(11) unsigned DEFAULT 0 COMMENT '创建者ID',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_card_key` (`card_key`),
  KEY `idx_status` (`status`),
  KEY `idx_merchant_id` (`merchant_id`),
  KEY `idx_expire_at` (`expire_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='卡密表';
