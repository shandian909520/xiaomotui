-- +----------------------------------------------------------------------
-- | IP黑名单表
-- +----------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `ip_blacklist` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
    `ip` varchar(45) NOT NULL COMMENT 'IP地址（支持IPv6）',
    `status` enum('active','inactive') NOT NULL DEFAULT 'active' COMMENT '状态：active-激活，inactive-未激活',
    `reason` varchar(255) DEFAULT NULL COMMENT '封禁原因',
    `blocked_at` int(11) unsigned DEFAULT NULL COMMENT '封禁时间戳',
    `blocked_until` int(11) unsigned DEFAULT NULL COMMENT '解封时间戳（0表示永久）',
    `created_at` int(11) unsigned NOT NULL COMMENT '创建时间戳',
    `updated_at` int(11) unsigned NOT NULL COMMENT '更新时间戳',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_ip` (`ip`),
    KEY `idx_status` (`status`),
    KEY `idx_blocked_until` (`blocked_until`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='IP黑名单表';
