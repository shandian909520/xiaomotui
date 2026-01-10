-- 平台账号表
CREATE TABLE IF NOT EXISTS platform_accounts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '账号ID',
    user_id INT UNSIGNED NOT NULL COMMENT '用户ID',
    platform ENUM('DOUYIN','XIAOHONGSHU','WECHAT','WEIBO') NOT NULL COMMENT '平台类型',
    platform_uid VARCHAR(100) NOT NULL COMMENT '平台用户ID',
    platform_name VARCHAR(100) DEFAULT NULL COMMENT '平台昵称',
    access_token TEXT COMMENT '访问令牌',
    refresh_token TEXT COMMENT '刷新令牌',
    expires_time DATETIME DEFAULT NULL COMMENT '令牌过期时间',
    avatar VARCHAR(255) DEFAULT NULL COMMENT '头像',
    follower_count INT DEFAULT 0 COMMENT '粉丝数',
    status TINYINT(1) DEFAULT 1 COMMENT '状态 0失效 1正常',
    create_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    update_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',

    PRIMARY KEY (id),
    INDEX idx_user_id (user_id),
    INDEX idx_platform (platform),
    INDEX idx_status (status),
    UNIQUE KEY uk_user_platform (user_id, platform, platform_uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='平台账号表';