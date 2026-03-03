-- 为内容模板表添加视频库特有字段
-- 执行时间: 2025-01-25

-- 添加视频相关字段
ALTER TABLE `xmt_content_templates`
ADD COLUMN `video_url` varchar(500) DEFAULT NULL COMMENT '视频文件URL' AFTER `preview_url`,
ADD COLUMN `video_duration` int(11) DEFAULT NULL COMMENT '视频时长(秒)' AFTER `video_url`,
ADD COLUMN `video_resolution` varchar(20) DEFAULT NULL COMMENT '视频分辨率 如1920x1080' AFTER `video_duration`,
ADD COLUMN `video_size` bigint(20) DEFAULT NULL COMMENT '视频文件大小(字节)' AFTER `video_resolution`,
ADD COLUMN `video_format` varchar(10) DEFAULT NULL COMMENT '视频格式 mp4/avi/mov等' AFTER `video_size`,
ADD COLUMN `thumbnail_time` int(11) DEFAULT NULL COMMENT '缩略图截取时间点(秒)' AFTER `video_format`,
ADD COLUMN `aspect_ratio` varchar(10) DEFAULT '16:9' COMMENT '宽高比 16:9/9:16/1:1等' AFTER `thumbnail_time`,
ADD COLUMN `is_template` tinyint(1) DEFAULT '1' COMMENT '是否作为模板 0否 1是' AFTER `aspect_ratio`,
ADD COLUMN `template_tags` json DEFAULT NULL COMMENT '模板标签 用于分类和搜索' AFTER `is_template`,
ADD COLUMN `difficulty` enum('easy','medium','hard') DEFAULT 'easy' COMMENT '制作难度 简单/中等/困难' AFTER `template_tags`,
ADD COLUMN `industry` varchar(50) DEFAULT NULL COMMENT '适用行业 餐饮/零售/教育等' AFTER `difficulty`;

-- 添加索引以提高查询性能
ALTER TABLE `xmt_content_templates`
ADD INDEX `idx_video_type` (`type`, `is_template`),
ADD INDEX `idx_industry` (`industry`),
ADD INDEX `idx_difficulty` (`difficulty`);

-- 为视频模板添加一些默认数据(可选)
INSERT INTO `xmt_content_templates`
(`name`, `type`, `category`, `style`, `content`, `video_url`, `preview_url`, `video_duration`, `video_resolution`, `video_format`, `aspect_ratio`, `is_template`, `template_tags`, `difficulty`, `industry`, `usage_count`, `is_public`, `status`, `create_time`, `update_time`)
VALUES
('餐饮促销视频模板', 'VIDEO', '促销', '现代', '{"scenes": [{"text": "欢迎光临", "duration": 3}, {"text": "特价优惠", "duration": 2}, {"text": "欢迎下次光临", "duration": 3}]}', NULL, '/uploads/templates/promotion_preview.jpg', 8, '1920x1080', 'mp4', '16:9', 1, '["餐饮", "促销", "特价"]', 'easy', '餐饮', 0, 1, 1, NOW(), NOW()),
('产品介绍视频模板', 'VIDEO', '自定义', '简约', '{"scenes": [{"text": "产品展示", "duration": 5}, {"text": "核心优势", "duration": 3}, {"text": "联系我们", "duration": 2}]}', NULL, '/uploads/templates/product_preview.jpg', 10, '1920x1080', 'mp4', '16:9', 1, '["产品", "介绍", "营销"]', 'medium', '零售', 0, 1, 1, NOW(), NOW());
