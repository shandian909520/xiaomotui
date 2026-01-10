-- 用户优惠券表
CREATE TABLE IF NOT EXISTS coupon_users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT '记录ID',
    coupon_id INT UNSIGNED NOT NULL COMMENT '优惠券ID',
    user_id INT UNSIGNED NOT NULL COMMENT '用户ID',
    coupon_code VARCHAR(50) NOT NULL COMMENT '优惠券代码',
    use_status TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '使用状态 0未使用 1已使用 2已过期',
    used_time TIMESTAMP NULL DEFAULT NULL COMMENT '使用时间',
    order_id BIGINT UNSIGNED DEFAULT NULL COMMENT '关联订单ID',
    received_source VARCHAR(50) DEFAULT '' COMMENT '领取来源',
    device_id INT UNSIGNED DEFAULT NULL COMMENT '关联设备ID（NFC设备领取时）',
    create_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    update_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',

    UNIQUE KEY uk_coupon_code (coupon_code),
    INDEX idx_coupon_id (coupon_id),
    INDEX idx_user_id (user_id),
    INDEX idx_use_status (use_status),
    INDEX idx_used_time (used_time),
    INDEX idx_device_id (device_id),
    INDEX idx_received_source (received_source),
    INDEX idx_create_time (create_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户优惠券表';