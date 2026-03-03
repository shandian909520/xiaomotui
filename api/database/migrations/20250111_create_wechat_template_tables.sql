-- 微信模板消息发送日志表
CREATE TABLE IF NOT EXISTS `xmt_wechat_template_logs` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '日志ID',
  `user_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '用户ID',
  `openid` varchar(100) NOT NULL DEFAULT '' COMMENT '微信OpenID',
  `platform` varchar(20) NOT NULL DEFAULT 'miniprogram' COMMENT '平台类型 miniprogram|official',
  `template_type` varchar(50) NOT NULL DEFAULT '' COMMENT '模板类型',
  `template_id` varchar(100) NOT NULL DEFAULT '' COMMENT '模板ID',
  `template_data` text COMMENT '模板数据JSON',
  `page` varchar(255) NOT NULL DEFAULT '' COMMENT '跳转页面',
  `related_data` text COMMENT '关联数据JSON',
  `status` varchar(20) NOT NULL DEFAULT 'sending' COMMENT '发送状态 sending|success|failed',
  `retry_count` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '重试次数',
  `error_code` varchar(50) DEFAULT NULL COMMENT '错误码',
  `error_message` varchar(500) DEFAULT NULL COMMENT '错误信息',
  `response_data` text COMMENT '响应数据JSON',
  `send_time` datetime DEFAULT NULL COMMENT '发送时间',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_openid` (`openid`),
  KEY `idx_platform` (`platform`),
  KEY `idx_template_type` (`template_type`),
  KEY `idx_status` (`status`),
  KEY `idx_create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='微信模板消息发送日志表';

-- 微信模板配置表
CREATE TABLE IF NOT EXISTS `xmt_wechat_templates` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '配置ID',
  `platform` varchar(20) NOT NULL DEFAULT 'miniprogram' COMMENT '平台类型 miniprogram|official',
  `template_key` varchar(50) NOT NULL DEFAULT '' COMMENT '模板键名',
  `template_id` varchar(100) NOT NULL DEFAULT '' COMMENT '模板ID',
  `template_name` varchar(100) NOT NULL DEFAULT '' COMMENT '模板名称',
  `content` text COMMENT '模板内容',
  `example` text COMMENT '模板示例',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态 0禁用 1启用',
  `remark` varchar(500) DEFAULT NULL COMMENT '备注',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_platform_template_key` (`platform`, `template_key`),
  KEY `idx_platform` (`platform`),
  KEY `idx_template_key` (`template_key`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='微信模板配置表';

-- 插入默认模板配置（仅作为示例，实际使用时需要替换为真实模板ID）
INSERT IGNORE INTO `xmt_wechat_templates` (`platform`, `template_key`, `template_id`, `template_name`, `content`, `example`, `status`, `remark`) VALUES
-- 小程序订阅消息模板
('miniprogram', 'content_generated', 'CONTENT_GENERATED_TEMPLATE', '内容生成完成通知', '内容名称：{{thing1.DATA}}\n内容类型：{{thing2.DATA}}\n生成时间：{{date3.DATA}}\n发布平台：{{thing4.DATA}}', '{"thing1":{"value":"视频内容生成完成"},"thing2":{"value":"视频内容"},"date3":{"value":"2024-01-01 12:00:00"},"thing4":{"value":"抖音"}}', 0, '内容生成完成后通知用户'),
('miniprogram', 'device_alert', 'DEVICE_ALERT_TEMPLATE', '设备告警通知', '设备名称：{{thing1.DATA}}\n设备编号：{{character_string2.DATA}}\n告警类型：{{thing3.DATA}}\n告警时间：{{time4.DATA}}', '{"thing1":{"value":"智能设备A1"},"character_string2":{"value":"DEV001"},"thing3":{"value":"离线告警"},"time4":{"value":"2024-01-01 12:00:00"}}', 0, '设备离线或异常时通知商家'),
('miniprogram', 'coupon_received', 'COUPON_RECEIVED_TEMPLATE', '优惠券领取通知', '优惠券名称：{{thing1.DATA}}\n优惠金额：{{amount2.DATA}}元\n有效期至：{{date3.DATA}}\n商家名称：{{thing4.DATA}}', '{"thing1":{"value":"满100减20券"},"amount2":{"value":"20"},"date3":{"value":"2024-12-31"},"thing4":{"value":"示例商家"}}', 0, '用户领取优惠券后通知'),
('miniprogram', 'merchant_audit', 'MERCHANT_AUDIT_TEMPLATE', '商家审核结果通知', '商家名称：{{thing1.DATA}}\n审核结果：{{phrase2.DATA}}\n审核说明：{{thing3.DATA}}\n审核时间：{{date4.DATA}}', '{"thing1":{"value":"示例商家"},"phrase2":{"value":"审核通过"},"thing3":{"value":"您的申请已通过审核"},"date4":{"value":"2024-01-01 12:00:00"}}', 0, '商家审核结果通知'),
('miniprogram', 'order_status', 'ORDER_STATUS_TEMPLATE', '订单状态变更通知', '订单编号：{{character_string1.DATA}}\n商品名称：{{thing2.DATA}}\n订单状态：{{thing3.DATA}}\n订单金额：{{amount4.DATA}}元', '{"character_string1":{"value":"ORDER20240101001"},"thing2":{"value":"示例商品"},"thing3":{"value":"待支付"},"amount4":{"value":"99.00"}}', 0, '订单状态变更时通知用户'),

-- 公众号模板消息模板
('official', 'content_generated', 'OFFICIAL_CONTENT_GENERATED_TEMPLATE', '内容生成完成通知', '内容名称：{{thing1.DATA}}\n内容类型：{{thing2.DATA}}\n生成时间：{{date3.DATA}}\n发布平台：{{thing4.DATA}}', '{"thing1":{"value":"视频内容生成完成"},"thing2":{"value":"视频内容"},"date3":{"value":"2024-01-01 12:00:00"},"thing4":{"value":"抖音"}}', 0, '内容生成完成后通知用户（公众号）'),
('official', 'device_alert', 'OFFICIAL_DEVICE_ALERT_TEMPLATE', '设备告警通知', '设备名称：{{thing1.DATA}}\n设备编号：{{character_string2.DATA}}\n告警类型：{{thing3.DATA}}\n告警时间：{{time4.DATA}}', '{"thing1":{"value":"智能设备A1"},"character_string2":{"value":"DEV001"},"thing3":{"value":"离线告警"},"time4":{"value":"2024-01-01 12:00:00"}}', 0, '设备离线或异常时通知商家（公众号）'),
('official', 'coupon_received', 'OFFICIAL_COUPON_RECEIVED_TEMPLATE', '优惠券领取通知', '优惠券名称：{{thing1.DATA}}\n优惠金额：{{amount2.DATA}}元\n有效期至：{{date3.DATA}}\n商家名称：{{thing4.DATA}}', '{"thing1":{"value":"满100减20券"},"amount2":{"value":"20"},"date3":{"value":"2024-12-31"},"thing4":{"value":"示例商家"}}', 0, '用户领取优惠券后通知（公众号）'),
('official', 'merchant_audit', 'OFFICIAL_MERCHANT_AUDIT_TEMPLATE', '商家审核结果通知', '商家名称：{{thing1.DATA}}\n审核结果：{{phrase2.DATA}}\n审核说明：{{thing3.DATA}}\n审核时间：{{date4.DATA}}', '{"thing1":{"value":"示例商家"},"phrase2":{"value":"审核通过"},"thing3":{"value":"您的申请已通过审核"},"date4":{"value":"2024-01-01 12:00:00"}}', 0, '商家审核结果通知（公众号）'),
('official', 'order_status', 'OFFICIAL_ORDER_STATUS_TEMPLATE', '订单状态变更通知', '订单编号：{{character_string1.DATA}}\n商品名称：{{thing2.DATA}}\n订单状态：{{thing3.DATA}}\n订单金额：{{amount4.DATA}}元', '{"character_string1":{"value":"ORDER20240101001"},"thing2":{"value":"示例商品"},"thing3":{"value":"待支付"},"amount4":{"value":"99.00"}}', 0, '订单状态变更时通知用户（公众号）');

-- 为用户表添加微信OpenID字段（如果不存在）
-- 注意:此功能已在用户表初始化时包含,此处仅作为文档说明
