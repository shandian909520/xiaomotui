-- 推广活动表
CREATE TABLE IF NOT EXISTS xmt_promo_campaigns (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    merchant_id INT UNSIGNED NOT NULL COMMENT '商家ID',
    name VARCHAR(100) NOT NULL COMMENT '活动名称',
    description TEXT COMMENT '活动描述',
    variant_ids JSON COMMENT '关联的变体ID列表',
    copywriting TEXT COMMENT '推广文案',
    tags JSON COMMENT '话题标签',
    reward_coupon_id INT UNSIGNED DEFAULT NULL COMMENT '奖励优惠券ID',
    platforms JSON COMMENT '目标平台 ["douyin","kuaishou"]',
    status TINYINT DEFAULT 1 COMMENT '状态 1启用 0禁用',
    start_time DATETIME DEFAULT NULL COMMENT '开始时间',
    end_time DATETIME DEFAULT NULL COMMENT '结束时间',
    create_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    update_time DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_merchant (merchant_id),
    INDEX idx_status (status),
    INDEX idx_time (start_time, end_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='推广活动表';

-- 活动设备关联表
CREATE TABLE IF NOT EXISTS xmt_promo_campaign_devices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT UNSIGNED NOT NULL COMMENT '活动ID',
    device_id INT UNSIGNED NOT NULL COMMENT '设备ID',
    create_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_device (device_id),
    INDEX idx_campaign (campaign_id),
    INDEX idx_device (device_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='活动设备关联表';

-- 推广分发记录表
CREATE TABLE IF NOT EXISTS xmt_promo_distributions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT UNSIGNED NOT NULL COMMENT '活动ID',
    device_id INT UNSIGNED NOT NULL COMMENT '设备ID',
    variant_id INT UNSIGNED NOT NULL COMMENT '变体ID',
    user_openid VARCHAR(64) DEFAULT NULL COMMENT '用户OpenID',
    platform VARCHAR(20) DEFAULT NULL COMMENT '发布平台',
    status ENUM('pending','downloaded','published','rewarded') DEFAULT 'pending',
    reward_coupon_user_id INT UNSIGNED DEFAULT NULL COMMENT '发放的优惠券记录ID',
    client_ip VARCHAR(45) DEFAULT NULL,
    create_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    update_time DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_campaign (campaign_id),
    INDEX idx_device (device_id),
    INDEX idx_user (user_openid),
    INDEX idx_status (status),
    INDEX idx_create_time (create_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='推广分发记录表';
