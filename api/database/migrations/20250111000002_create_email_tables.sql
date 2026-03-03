-- 邮件日志表
CREATE TABLE IF NOT EXISTS `xmt_email_logs` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '日志ID',
  `from_email` varchar(255) NOT NULL DEFAULT '' COMMENT '发件人邮箱',
  `to_email` varchar(500) NOT NULL COMMENT '收件人邮箱（多个用逗号分隔）',
  `cc_email` varchar(500) DEFAULT NULL COMMENT '抄送邮箱',
  `bcc_email` varchar(500) DEFAULT NULL COMMENT '密送邮箱',
  `subject` varchar(500) NOT NULL COMMENT '邮件主题',
  `body` text COMMENT '邮件正文（HTML）',
  `alt_body` text COMMENT '邮件正文（纯文本）',
  `is_html` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否为HTML邮件',
  `success` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否发送成功',
  `error_message` text COMMENT '错误信息',
  `has_attachment` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否有附件',
  `attachment_count` int(11) NOT NULL DEFAULT 0 COMMENT '附件数量',
  `attachments` text COMMENT '附件信息（JSON）',
  `template` varchar(50) DEFAULT NULL COMMENT '使用的模板',
  `send_time` datetime DEFAULT NULL COMMENT '发送时间',
  `duration` int(11) NOT NULL DEFAULT 0 COMMENT '发送耗时（毫秒）',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_to_email` (`to_email`),
  KEY `idx_success` (`success`),
  KEY `idx_send_time` (`send_time`),
  KEY `idx_template` (`template`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='邮件日志表';

-- 邮件失败记录表
CREATE TABLE IF NOT EXISTS `xmt_email_failures` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '失败ID',
  `to_email` varchar(500) NOT NULL COMMENT '收件人邮箱',
  `subject` varchar(500) NOT NULL COMMENT '邮件主题',
  `error_message` text COMMENT '错误信息',
  `attempts` int(11) NOT NULL DEFAULT 0 COMMENT '重试次数',
  `failed_time` datetime NOT NULL COMMENT '最终失败时间',
  `email_data` text COMMENT '邮件数据（JSON）',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_to_email` (`to_email`),
  KEY `idx_failed_time` (`failed_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='邮件失败记录表';
