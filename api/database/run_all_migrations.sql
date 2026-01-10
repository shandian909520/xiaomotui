-- ========================================
-- 小魔推数据库完整迁移脚本
-- 执行方式: mysql -u root -p xiaomotui < run_all_migrations.sql
-- ========================================

-- 1. 创建迁移记录表
DROP TABLE IF EXISTS `xmt_migration_log`;

CREATE TABLE `xmt_migration_log` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '记录ID',
  `migration_name` varchar(255) NOT NULL COMMENT '迁移文件名',
  `batch` int(11) NOT NULL COMMENT '批次号',
  `executed_at` datetime NOT NULL COMMENT '执行时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `migration_name` (`migration_name`),
  KEY `batch` (`batch`),
  KEY `executed_at` (`executed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='数据库迁移记录表';

-- 2. 创建用户表
DROP TABLE IF EXISTS `xmt_user`;

CREATE TABLE `xmt_user` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '用户ID',
  `openid` varchar(64) NOT NULL COMMENT '微信openid',
  `unionid` varchar(64) DEFAULT NULL COMMENT '微信unionid',
  `phone` varchar(20) DEFAULT NULL COMMENT '手机号',
  `nickname` varchar(50) DEFAULT NULL COMMENT '昵称',
  `avatar` varchar(255) DEFAULT NULL COMMENT '头像',
  `gender` tinyint(1) DEFAULT '0' COMMENT '性别 0未知 1男 2女',
  `member_level` enum('BASIC','VIP','PREMIUM') DEFAULT 'BASIC' COMMENT '会员等级',
  `points` int(11) DEFAULT '0' COMMENT '积分',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态 0禁用 1正常',
  `last_login_time` datetime DEFAULT NULL COMMENT '最后登录时间',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `openid` (`openid`),
  KEY `phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户表';

-- 3. 创建商家表
DROP TABLE IF EXISTS `xmt_merchants`;

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
  KEY `category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商家表';

-- 4. 创建NFC设备表
DROP TABLE IF EXISTS `xmt_nfc_devices`;

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
  KEY `merchant_id` (`merchant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='NFC设备表';

-- 5. 创建内容任务表
DROP TABLE IF EXISTS `xmt_content_tasks`;

CREATE TABLE `xmt_content_tasks` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '任务ID',
  `user_id` int(11) unsigned NOT NULL COMMENT '用户ID',
  `merchant_id` int(11) unsigned NOT NULL COMMENT '商家ID',
  `device_id` int(11) unsigned DEFAULT NULL COMMENT '设备ID',
  `template_id` int(11) unsigned DEFAULT NULL COMMENT '模板ID',
  `type` enum('VIDEO','TEXT','IMAGE') NOT NULL COMMENT '内容类型',
  `status` enum('PENDING','PROCESSING','COMPLETED','FAILED') DEFAULT 'PENDING' COMMENT '任务状态',
  `input_data` json DEFAULT NULL COMMENT '输入数据',
  `output_data` json DEFAULT NULL COMMENT '输出数据',
  `ai_provider` varchar(20) DEFAULT NULL COMMENT 'AI服务商',
  `generation_time` int(11) DEFAULT NULL COMMENT '生成耗时(秒)',
  `error_message` text COMMENT '错误信息',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  `complete_time` datetime DEFAULT NULL COMMENT '完成时间',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `merchant_id` (`merchant_id`),
  KEY `device_id` (`device_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='内容生成任务表';

-- 6. 创建内容模板表
DROP TABLE IF EXISTS `xmt_content_templates`;

CREATE TABLE `xmt_content_templates` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '模板ID',
  `merchant_id` int(11) unsigned DEFAULT NULL COMMENT '商家ID 为空表示系统模板',
  `name` varchar(100) NOT NULL COMMENT '模板名称',
  `type` enum('VIDEO','TEXT','IMAGE') NOT NULL COMMENT '模板类型',
  `category` varchar(50) NOT NULL COMMENT '模板分类',
  `style` varchar(50) DEFAULT NULL COMMENT '风格标签',
  `content` json NOT NULL COMMENT '模板内容配置',
  `preview_url` varchar(255) DEFAULT NULL COMMENT '预览图',
  `usage_count` int(11) DEFAULT '0' COMMENT '使用次数',
  `is_public` tinyint(1) DEFAULT '0' COMMENT '是否公开 0私有 1公开',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态 0禁用 1启用',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `merchant_id` (`merchant_id`),
  KEY `category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='内容模板表';

-- 7. 创建设备触发记录表
DROP TABLE IF EXISTS `xmt_device_triggers`;

CREATE TABLE `xmt_device_triggers` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '记录ID',
  `device_id` int(11) unsigned NOT NULL COMMENT '设备ID',
  `user_id` int(11) unsigned DEFAULT NULL COMMENT '用户ID',
  `trigger_type` varchar(20) NOT NULL COMMENT '触发类型',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'IP地址',
  `user_agent` varchar(255) DEFAULT NULL COMMENT '用户代理',
  `result` enum('SUCCESS','FAILED') DEFAULT 'SUCCESS' COMMENT '触发结果',
  `response_time` int(11) DEFAULT NULL COMMENT '响应时间(毫秒)',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `device_id` (`device_id`),
  KEY `user_id` (`user_id`),
  KEY `create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='设备触发记录表';

-- 8. 创建优惠券表
DROP TABLE IF EXISTS `xmt_coupons`;

CREATE TABLE `xmt_coupons` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '优惠券ID',
  `merchant_id` int(11) unsigned NOT NULL COMMENT '商家ID',
  `name` varchar(100) NOT NULL COMMENT '优惠券名称',
  `type` enum('DISCOUNT','FULL_REDUCE','FREE_SHIPPING') NOT NULL COMMENT '优惠券类型',
  `value` decimal(10,2) NOT NULL COMMENT '优惠金额',
  `min_amount` decimal(10,2) DEFAULT '0.00' COMMENT '最低消费金额',
  `total_count` int(11) NOT NULL COMMENT '总发放数量',
  `used_count` int(11) DEFAULT '0' COMMENT '已使用数量',
  `per_user_limit` int(11) DEFAULT '1' COMMENT '每人限领数量',
  `valid_days` int(11) DEFAULT '30' COMMENT '有效天数',
  `start_time` datetime NOT NULL COMMENT '开始时间',
  `end_time` datetime NOT NULL COMMENT '结束时间',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态 0禁用 1启用',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `merchant_id` (`merchant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='优惠券表';

-- 9. 创建用户优惠券表
DROP TABLE IF EXISTS `xmt_coupon_users`;

CREATE TABLE `xmt_coupon_users` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `user_id` int(11) unsigned NOT NULL COMMENT '用户ID',
  `coupon_id` int(11) unsigned NOT NULL COMMENT '优惠券ID',
  `code` varchar(32) NOT NULL COMMENT '优惠券码',
  `status` enum('UNUSED','USED','EXPIRED') DEFAULT 'UNUSED' COMMENT '使用状态',
  `source` varchar(50) DEFAULT NULL COMMENT '获取来源',
  `get_time` datetime NOT NULL COMMENT '获取时间',
  `use_time` datetime DEFAULT NULL COMMENT '使用时间',
  `expire_time` datetime NOT NULL COMMENT '过期时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `user_id` (`user_id`),
  KEY `coupon_id` (`coupon_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户优惠券表';

-- 记录迁移执行
INSERT INTO `xmt_migration_log` (`migration_name`, `batch`, `executed_at`) VALUES
('20250929000000_create_migration_log_table.sql', 1, NOW()),
('20250929215341_create_users_table.sql', 1, NOW()),
('20250929220835_create_merchants_table.sql', 1, NOW()),
('20250929221354_create_nfc_devices_table.sql', 1, NOW()),
('20250929222838_create_content_tasks_table.sql', 1, NOW()),
('20250929223848_create_content_templates_table.sql', 1, NOW()),
('20250930000001_create_device_triggers_table.sql', 1, NOW()),
('20250930000002_create_coupons_table.sql', 1, NOW()),
('20250930000003_create_coupon_users_table.sql', 1, NOW());

-- 显示创建的表
SELECT '数据库迁移完成！已创建以下表:' AS message;
SHOW TABLES LIKE 'xmt_%';

-- 显示表统计
SELECT
  COUNT(*) as total_tables,
  '个表已创建' as status
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME LIKE 'xmt_%';