-- 视频模板表
CREATE TABLE IF NOT EXISTS xmt_promo_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    merchant_id INT UNSIGNED NOT NULL COMMENT '商家ID',
    name VARCHAR(100) NOT NULL COMMENT '模板名称',
    description TEXT COMMENT '模板描述',
    material_ids JSON COMMENT '素材ID列表(有序)',
    config JSON COMMENT '合成配置(时长/转场/音乐等)',
    status TINYINT DEFAULT 1 COMMENT '状态 1正常 0禁用',
    create_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    update_time DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_merchant (merchant_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='视频模板表';

-- 视频变体表
CREATE TABLE IF NOT EXISTS xmt_promo_variants (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template_id INT UNSIGNED NOT NULL COMMENT '模板ID',
    merchant_id INT UNSIGNED NOT NULL COMMENT '商家ID',
    file_url VARCHAR(500) NOT NULL COMMENT '视频文件URL',
    file_size INT UNSIGNED COMMENT '文件大小',
    duration DECIMAL(6,2) COMMENT '时长(秒)',
    md5 VARCHAR(32) COMMENT '文件MD5',
    params_json JSON COMMENT '去重参数',
    use_count INT DEFAULT 0 COMMENT '使用次数',
    status TINYINT DEFAULT 1 COMMENT '状态 1可用 0禁用',
    create_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    update_time DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_template (template_id),
    INDEX idx_merchant (merchant_id),
    INDEX idx_status (status),
    UNIQUE KEY uk_md5 (md5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='视频变体表';
