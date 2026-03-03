CREATE TABLE IF NOT EXISTS `xmt_admin` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL COMMENT '用户名',
  `password` varchar(255) NOT NULL COMMENT '密码',
  `nickname` varchar(50) DEFAULT NULL COMMENT '昵称',
  `avatar` varchar(255) DEFAULT NULL COMMENT '头像',
  `email` varchar(100) DEFAULT NULL COMMENT '邮箱',
  `mobile` varchar(20) DEFAULT NULL COMMENT '手机号',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态: 0=禁用, 1=启用',
  `login_time` int(10) unsigned DEFAULT NULL COMMENT '最后登录时间',
  `login_ip` varchar(50) DEFAULT NULL COMMENT '最后登录IP',
  `create_time` int(10) unsigned NOT NULL COMMENT '创建时间',
  `update_time` int(10) unsigned NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='管理员表';

INSERT IGNORE INTO `xmt_admin` (`id`, `username`, `password`, `nickname`, `status`, `create_time`, `update_time`) VALUES
(1, 'admin', '$2y$10$YourHashedPasswordHere', '超级管理员', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());
-- Note: Password should be hashed. For 'admin123', the hash is typically generated via password_hash('admin123', PASSWORD_DEFAULT).
-- I will update the hash in the next step or use a PHP script to insert it correctly.
