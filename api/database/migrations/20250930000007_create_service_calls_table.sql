-- 服务呼叫表
-- 删除旧的服务呼叫表(如果存在)
DROP TABLE IF EXISTS `xmt_service_calls`;

-- 创建服务呼叫表
CREATE TABLE `xmt_service_calls` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '呼叫ID',
  `session_id` int(11) unsigned NOT NULL COMMENT '会话ID',
  `user_id` int(11) unsigned NOT NULL COMMENT '用户ID',
  `merchant_id` int(11) unsigned NOT NULL COMMENT '商家ID',
  `table_id` int(11) unsigned NOT NULL COMMENT '桌台ID',
  `call_type` enum('ORDER','WATER','BILL','OTHER') NOT NULL DEFAULT 'OTHER' COMMENT '呼叫类型',
  `description` varchar(255) DEFAULT NULL COMMENT '描述',
  `priority` enum('LOW','NORMAL','HIGH','URGENT') DEFAULT 'NORMAL' COMMENT '优先级',
  `status` enum('PENDING','PROCESSING','COMPLETED','CANCELLED') DEFAULT 'PENDING' COMMENT '呼叫状态',
  `staff_id` int(11) unsigned DEFAULT NULL COMMENT '处理员工ID',
  `response_time` int(11) DEFAULT NULL COMMENT '响应时间(秒)',
  `complete_time` datetime DEFAULT NULL COMMENT '完成时间',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_session` (`session_id`),
  KEY `idx_merchant_status` (`merchant_id`, `status`),
  KEY `idx_table` (`table_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='服务呼叫表';