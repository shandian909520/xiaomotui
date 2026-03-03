-- 推广素材表
CREATE TABLE IF NOT EXISTS xmt_promo_materials (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    merchant_id INT UNSIGNED NOT NULL COMMENT '商家ID',
    type ENUM('image', 'video', 'music') NOT NULL COMMENT '素材类型',
    name VARCHAR(200) NOT NULL COMMENT '素材名称',
    file_url VARCHAR(500) NOT NULL COMMENT '文件URL',
    thumbnail_url VARCHAR(500) DEFAULT NULL COMMENT '缩略图URL',
    duration DECIMAL(6,2) DEFAULT NULL COMMENT '时长(秒)',
    file_size INT UNSIGNED DEFAULT NULL COMMENT '文件大小(字节)',
    width INT UNSIGNED DEFAULT NULL COMMENT '宽度(像素)',
    height INT UNSIGNED DEFAULT NULL COMMENT '高度(像素)',
    sort_order INT DEFAULT 0 COMMENT '排序',
    status TINYINT DEFAULT 1 COMMENT '状态 1正常 0禁用',
    create_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    update_time DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_merchant_type (merchant_id, type),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='推广素材表';
