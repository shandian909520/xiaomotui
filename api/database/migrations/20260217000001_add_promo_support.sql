-- 推广功能支持迁移
-- 1. NFC设备表新增推广配置字段
-- 2. 新建推广发布记录表

-- NFC设备表新增推广配置字段
ALTER TABLE xmt_nfc_devices
ADD COLUMN promo_video_id INT UNSIGNED DEFAULT NULL COMMENT '推广视频ID(关联content_templates)',
ADD COLUMN promo_copywriting TEXT DEFAULT NULL COMMENT '推广文案',
ADD COLUMN promo_tags JSON DEFAULT NULL COMMENT '推广话题标签',
ADD COLUMN promo_reward_coupon_id INT UNSIGNED DEFAULT NULL COMMENT '推广奖励优惠券ID';

-- 推广发布记录表
CREATE TABLE IF NOT EXISTS xmt_promo_publishes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    trigger_id INT UNSIGNED NOT NULL COMMENT '触发记录ID',
    device_id INT UNSIGNED NOT NULL COMMENT '设备ID',
    merchant_id INT UNSIGNED NOT NULL COMMENT '商家ID',
    user_id INT UNSIGNED DEFAULT NULL COMMENT '用户ID',
    user_openid VARCHAR(64) DEFAULT NULL COMMENT '用户OpenID',
    platform VARCHAR(20) NOT NULL COMMENT '发布平台 douyin/kuaishou',
    status ENUM('claimed','verified','expired') DEFAULT 'claimed' COMMENT '状态',
    coupon_user_id INT UNSIGNED DEFAULT NULL COMMENT '发放的优惠券记录ID',
    client_ip VARCHAR(45) DEFAULT NULL,
    create_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    update_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_trigger_platform (trigger_id, platform),
    INDEX idx_device (device_id),
    INDEX idx_merchant (merchant_id),
    INDEX idx_user (user_openid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='推广发布记录表';
