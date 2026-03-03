-- 发布任务表
CREATE TABLE IF NOT EXISTS xmt_publish_tasks (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT '发布任务ID',
    content_task_id INT UNSIGNED NOT NULL COMMENT '内容任务ID',
    user_id INT UNSIGNED NOT NULL COMMENT '用户ID',
    platforms JSON NOT NULL COMMENT '发布平台配置',
    status ENUM('PENDING', 'PUBLISHING', 'COMPLETED', 'PARTIAL', 'FAILED') NOT NULL DEFAULT 'PENDING' COMMENT '发布状态',
    results JSON DEFAULT NULL COMMENT '发布结果',
    scheduled_time TIMESTAMP NULL DEFAULT NULL COMMENT '定时发布时间',
    publish_time TIMESTAMP NULL DEFAULT NULL COMMENT '实际发布时间',
    create_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    update_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',

    INDEX idx_content_task_id (content_task_id),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_scheduled_time (scheduled_time),
    INDEX idx_create_time (create_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='发布任务表';