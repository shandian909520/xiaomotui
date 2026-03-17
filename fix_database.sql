-- 小魔推数据库修复脚本
-- 用于创建缺失的必要数据表
-- 使用方法：mysql -u root -p pengpeng < fix_database.sql

-- 1. 创建 IP 黑名单表
CREATE TABLE IF NOT EXISTS `xmt_ip_blacklist` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键 ID',
    `ip` varchar(45) NOT NULL COMMENT 'IP 地址（支持 IPv6）',
    `status` enum('active','inactive') NOT NULL DEFAULT 'active' COMMENT '状态：active-激活，inactive-未激活',
    `reason` varchar(255) DEFAULT NULL COMMENT '封禁原因',
    `blocked_at` int(11) unsigned DEFAULT NULL COMMENT '封禁时间戳',
    `blocked_until` int(11) unsigned DEFAULT NULL COMMENT '解封时间戳（0 表示永久）',
    `created_at` int(11) unsigned NOT NULL COMMENT '创建时间戳',
    `updated_at` int(11) unsigned NOT NULL COMMENT '更新时间戳',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_ip` (`ip`),
    KEY `idx_status` (`status`),
    KEY `idx_blocked_until` (`blocked_until`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='IP 黑名单表';

-- 2. 创建迁移日志表（用于记录已执行的迁移）
CREATE TABLE IF NOT EXISTS `xmt_migration_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `migration_file` VARCHAR(255) NOT NULL UNIQUE COMMENT '迁移文件名',
    `executed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '执行时间',
    `checksum` VARCHAR(64) DEFAULT NULL COMMENT '文件校验和',
    INDEX `idx_migration_file` (`migration_file`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='数据库迁移日志表';

-- 3. 创建用户表（如果不存在）
CREATE TABLE IF NOT EXISTS `xmt_users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `openid` VARCHAR(64) NOT NULL COMMENT '微信 OpenID',
    `nickname` VARCHAR(64) DEFAULT NULL COMMENT '昵称',
    `avatar` VARCHAR(255) DEFAULT NULL COMMENT '头像 URL',
    `phone` VARCHAR(20) DEFAULT NULL COMMENT '手机号',
    `unionid` VARCHAR(64) DEFAULT NULL COMMENT '微信 UnionID',
    `status` TINYINT DEFAULT 1 COMMENT '状态',
    `create_time` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `update_time` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_openid` (`openid`),
    KEY `idx_unionid` (`unionid`),
    KEY `idx_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户表';

-- 4. 创建商家表（如果不存在）
CREATE TABLE IF NOT EXISTS `xmt_merchants` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL COMMENT '商家名称',
    `logo` VARCHAR(255) DEFAULT NULL COMMENT '商家 Logo',
    `description` TEXT DEFAULT NULL COMMENT '商家简介',
    `contact_name` VARCHAR(50) DEFAULT NULL COMMENT '联系人姓名',
    `contact_phone` VARCHAR(20) DEFAULT NULL COMMENT '联系电话',
    `status` TINYINT DEFAULT 1 COMMENT '状态',
    `create_time` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `update_time` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商家表';

-- 5. 创建管理员表（如果不存在）
CREATE TABLE IF NOT EXISTS `xmt_admins` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL COMMENT '用户名',
    `password` VARCHAR(255) NOT NULL COMMENT '密码',
    `role` VARCHAR(20) DEFAULT 'merchant' COMMENT '角色',
    `merchant_id` INT UNSIGNED DEFAULT NULL COMMENT '关联商家 ID',
    `status` TINYINT DEFAULT 1 COMMENT '状态',
    `last_login_at` DATETIME DEFAULT NULL COMMENT '最后登录时间',
    `last_login_ip` VARCHAR(45) DEFAULT NULL COMMENT '最后登录 IP',
    `create_time` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `update_time` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_username` (`username`),
    KEY `idx_merchant_id` (`merchant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='管理员表';

-- 6. 插入默认管理员账号（如果不存在）
INSERT IGNORE INTO `xmt_admins` (`username`, `password`, `role`, `status`, `create_time`)
VALUES ('admin', '$2y$10$9AEsgNpWP1yVNKVukpGyY.jhoH5ax8l9sbqNLPo2N/iEIL6IWlbtS', 'admin', 1, NOW());
