-- 用餐会话用户关联表
-- 删除旧的用餐会话用户关联表(如果存在)
DROP TABLE IF EXISTS `xmt_session_users`;

-- 创建用餐会话用户关联表
CREATE TABLE `xmt_session_users` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '关联ID',
  `session_id` int(11) unsigned NOT NULL COMMENT '会话ID',
  `user_id` int(11) unsigned NOT NULL COMMENT '用户ID',
  `is_host` tinyint(1) DEFAULT '0' COMMENT '是否为主用户',
  `join_time` datetime NOT NULL COMMENT '加入时间',
  `leave_time` datetime DEFAULT NULL COMMENT '离开时间',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_session_user` (`session_id`, `user_id`),
  KEY `idx_session` (`session_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_join_time` (`join_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用餐会话用户关联表';