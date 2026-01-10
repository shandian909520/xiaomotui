-- 创建内容反馈表
CREATE TABLE IF NOT EXISTS `xmt_content_feedbacks` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT '反馈ID',
  `task_id` BIGINT UNSIGNED NOT NULL COMMENT '任务ID',
  `user_id` INT UNSIGNED NOT NULL COMMENT '用户ID',
  `merchant_id` INT UNSIGNED NULL COMMENT '商家ID',
  `feedback_type` ENUM('like', 'dislike') NOT NULL COMMENT '反馈类型',
  `reasons` JSON NULL COMMENT '不满意原因列表',
  `other_reason` TEXT NULL COMMENT '其他原因描述',
  `submit_time` DATETIME NOT NULL COMMENT '提交时间',
  `create_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',

  INDEX `idx_task_id` (`task_id`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_merchant_id` (`merchant_id`),
  INDEX `idx_feedback_type` (`feedback_type`),
  INDEX `idx_create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='内容反馈表';
