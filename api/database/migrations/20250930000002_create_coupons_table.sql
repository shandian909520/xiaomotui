-- 优惠券表
CREATE TABLE IF NOT EXISTS `coupons` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '优惠券ID',
    `merchant_id` INT(11) UNSIGNED NOT NULL COMMENT '商家ID',
    `name` VARCHAR(100) NOT NULL COMMENT '优惠券名称',
    `type` ENUM('DISCOUNT', 'FULL_REDUCE', 'FREE_SHIPPING') NOT NULL COMMENT '优惠券类型',
    `value` DECIMAL(10,2) NOT NULL COMMENT '优惠金额',
    `min_amount` DECIMAL(10,2) DEFAULT 0.00 COMMENT '最低消费金额',
    `total_count` INT(11) NOT NULL COMMENT '总发放数量',
    `used_count` INT(11) DEFAULT 0 COMMENT '已使用数量',
    `per_user_limit` INT(11) DEFAULT 1 COMMENT '每人限领数量',
    `valid_days` INT(11) DEFAULT 30 COMMENT '有效天数',
    `start_time` DATETIME NOT NULL COMMENT '开始时间',
    `end_time` DATETIME NOT NULL COMMENT '结束时间',
    `status` TINYINT(1) DEFAULT 1 COMMENT '状态 0禁用 1启用',
    `create_time` DATETIME NOT NULL COMMENT '创建时间',
    `update_time` DATETIME NOT NULL COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `merchant_id` (`merchant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='优惠券表';
