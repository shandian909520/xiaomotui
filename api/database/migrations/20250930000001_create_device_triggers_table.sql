-- 设备触发记录表
CREATE TABLE IF NOT EXISTS device_triggers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT '记录ID',
    device_id INT UNSIGNED NOT NULL COMMENT '设备ID',
    device_code VARCHAR(32) NOT NULL COMMENT '设备编码',
    user_id INT UNSIGNED DEFAULT NULL COMMENT '用户ID',
    user_openid VARCHAR(64) NOT NULL COMMENT '用户OpenID',
    trigger_mode ENUM('VIDEO', 'COUPON', 'WIFI', 'CONTACT', 'MENU') NOT NULL COMMENT '触发模式',
    response_type VARCHAR(20) NOT NULL COMMENT '响应类型',
    response_data JSON DEFAULT NULL COMMENT '响应数据',
    response_time INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '响应时间(毫秒)',
    client_ip VARCHAR(45) DEFAULT '' COMMENT '客户端IP',
    user_agent VARCHAR(255) DEFAULT '' COMMENT '用户代理',
    success TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '是否成功 1成功 0失败',
    error_message VARCHAR(500) DEFAULT '' COMMENT '错误信息',
    create_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',

    INDEX idx_device_id (device_id),
    INDEX idx_device_code (device_code),
    INDEX idx_user_id (user_id),
    INDEX idx_user_openid (user_openid),
    INDEX idx_trigger_mode (trigger_mode),
    INDEX idx_success (success),
    INDEX idx_create_time (create_time),
    INDEX idx_response_time (response_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='设备触发记录表';