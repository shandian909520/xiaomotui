-- 短信日志表
CREATE TABLE IF NOT EXISTS `xmt_sms_logs` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '日志ID',
  `phone` varchar(20) NOT NULL COMMENT '手机号',
  `code` varchar(10) DEFAULT NULL COMMENT '验证码',
  `content` varchar(500) DEFAULT NULL COMMENT '短信内容',
  `template` varchar(100) DEFAULT NULL COMMENT '短信模板',
  `provider` varchar(20) NOT NULL DEFAULT 'aliyun' COMMENT '服务商: aliyun/tencent',
  `success` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否发送成功',
  `error_code` varchar(50) DEFAULT NULL COMMENT '错误码',
  `error_message` varchar(500) DEFAULT NULL COMMENT '错误信息',
  `request_id` varchar(100) DEFAULT NULL COMMENT '请求ID',
  `send_time` datetime DEFAULT NULL COMMENT '发送时间',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_phone` (`phone`),
  KEY `idx_provider` (`provider`),
  KEY `idx_success` (`success`),
  KEY `idx_create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='短信日志表';
